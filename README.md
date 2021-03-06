Installation
=====
I needed to create an ACL which I could use on a site wide search on a high traffic web app. I have a complex data structure where users have a many to many relationship with groups (or product specific defined as circles) and circles can have one to many events. Users can only create or view events and users that belong a circle, circles are created dynamically so I needed something a little more advanced than symfony-acl as I needed to filter domain level entities on a sql query level. Most of the credit goes to Matthieu Napoli for the inital concept, my intention is to share and evolve it with the Symfony community as it really is such an excellent concept. It's only available @dev currently.

Feel free to fork/contribute.  

Code is on github: https://github.com/edweld/symfony-alternative-acl

An example implmentation can also be found https://github.com/edweld/symfony-acl-with-query-helper-example

1. Add to composer

```
composer require "edweld/aclbundle"
```

2. Register the bundle in app/AppKernel.php

```
<?php
// app/AppKernel.php
public function registerBundles()
{
    $bundles = array(
        // ...
        new Edweld\AclBundle\EdweldAclBundle(),
    ); 
}
```

3. Add Doctrine mappings in your config

```
# app/config/config.yml
doctrine:
    # ...
    orm:
        auto_generate_proxy_classes: '%kernel.debug%'
        entity_managers:
            default:
                mappings:
                    app:
                        type: annotation
                        prefix: Alumnet\CoreBundle
                        dir: "%kernel.root_dir%/../src/AppBundle/Entity/"
                        is_bundle: false
                    edweld_acl:
                        type: annotation
                        prefix: Edweld\AclBundle\Entity\
                        dir: "%kernel.root_dir%/../vendor/edweld/aclbundle/src/Entity/"
                        is_bundle: false
```

4. Map your security identity entity to the Acl Doctrine Security interface, the security entity is the user entity you use in authentication, in my case AppBundle\Entity\User.php This enables the bundle to use an interface to define the security.

```
// app/config/config.yml
  orm:
      resolve_target_entities:
          Edweld\AclBundle\Entity\SecurityIdentityInterface: AppBundle\Entity\User
```
We then map our security interface to our user security identity

```
// appBundle/Entity/User.php

use Edweld\AclBundle\Entity\SecurityIdentityInterface;

class User implements SecurityIdentityInterface{
//..
```

5. Add the following to you security configuration

```
// app/config/security.yml
  security:
    acl:
      connection: default  
```

Using the service container
----------------------------
Use the service container in a service, command or controller.

Available actions are currently 

 * VIEW
 * CREATE
 * EDIT
 * DELETE
 * UNDELETE
 * ALLOW
 

```
// AppBundle/FooController.php
use Edweld\AclBundle\Entity\Actions;

$this->getService('edweld_acl.acl')->isAllowed($this->getUser(), Actions::DELETE, $object);

```
Create Role Entities
--------------------
Create An Read only Doctrine Entity for each Role for object level permissions, this means we can cascade actions, for each permission setting, if you have multiple permissions consider creating a Base class and extending specific permissions.

Create as many different permission group Entities as you want.

```
<?php
namespace AppBundle\Entity;

use Edweld\AclBundle\Entity\Role;
use Edweld\AclBundle\Entity\Actions;
use Edweld\AclBundle\ACL;
use AppBundle\Entity\Circle;
use AppBundle\Entity\User;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(readOnly=true)
 */

class CircleEditorRole extends Role 
{
	/**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Circle", inversedBy="objectRoles")
     */
    protected $circleObject;

    public function __construct(User $user, Circle $circleObject)
    {
        $this->circleObject = $circleObject;

        parent::__construct($user);
    }

    public function createAuthorizations(ACL $acl)
    {
        $acl->allow(
            $this,
            new Actions([ 
                Actions::VIEW,
                Actions::CREATE,
                Actions::EDIT, 
            ]),
            $this->circleObject
        );
    }
}
```

Link domain enities to Roles for cascading deletion

```
// Entity/Circle.php
use Edweld\AclBundle\Entity\EntityResource;

//..

class Circle implements EntityResource 

//...

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\CircleBaseRole", mappedBy="circle", cascade={"remove"})
     */
    protected $objectRoles;
```
Configure roles entities in application config

```
// app/config/config.yml
edweld_acl: 
    identities:
        - { 'role':'circleViewer', 'class':'AppBundle\Role\CircleViewerRole' }
        - { 'role':'circleEditor', 'class':'AppBundle\Role\CircleEditorRole' }
        - { 'role':'eventEditor', 'class':'AppBundle\Role\EventEditorRole' }
        - { 'role':'eventViewer', 'class':'AppBundle\Role\EventViewerRole' }
        - { 'role':'userViewer', 'class':'AppBundle\Role\UserViewerRole' }
```

Grant and revoke access using service container

```
use AppBundle\Entity\CircleEditorRole;

$this->getService('edweld_acl.acl')->grant($this->getUser(), new CircleEditorRole($this->getUser(), $circleEntity));

```

And then use the QueryHelper to add permissions to queries

```
namespace AppBundle\Repository;

use Edweld\AclBundle\Doctrine\ACLQueryHelper;
use Edweld\AclBundle\Entity\Actions;
//..

public function findAllWithAcl($user)
    {
        $qb = $this->createQueryBuilder('circle');
        ACLQueryHelper::joinACL($qb, $user, Actions::VIEW);
        $q = $qb->getQuery();
        return $q->getResult();
    }

```

For my final implementation I wrapped everything up in a service within my application

```
<?php

namespace AppBundle\Service;

use Edweld\AclBundle\Model\ContainerTrait;

use AppBundle\Role\EventEditorRole;
use AppBundle\Role\EventViewerRole;
use AppBundle\Role\UserViewerRole;
use AppBundle\Role\CircleViewerRole;
use Edweld\AclBundle\Entity\Actions;

/**
 * 
 * @author Ed Weld <edward.weld@mobile-5.com>
 */

class AclService {

    use ContainerTrait;

    public function getAcl()
    {
        return $this->getService('edweld_acl_service');
    }

    public function isAllowed($action, $object)
    {
        switch($action)
        {
            case 'delete' :
                return $this->getService('edweld_acl_service')->isAllowed($this->getUser(), Actions::DELETE, $object);
                break;
            case 'edit' :
                return $this->getService('edweld_acl_service')->isAllowed($this->getUser(), Actions::EDIT, $object);
                break;
            case "view":
                var_dump('IS VIEW');
                return $this->getService('edweld_acl_service')->isAllowed($this->getUser(), Actions::VIEW, $object);
                break;
        }
        
    }

    /**
     * Adds users from a specific circle to view an event
     */
    public function addAclCircleToEvent($event, $circle)
    {
        $users = $circle->getUsers();
        $owner = $this->getUser();

        $this->getService('edweld_acl_service')->grant($owner, new EventEditorRole($owner, $event));

        foreach($users as $user){
        	$this->getService('edweld_acl_service')->grant($user, new EventViewerRole($user, $event));
        }
    }
    
    /**
     * Adds new user to user view permissions of a circle
     * And adds user to all circle events
     */
    public function addAclUserToCircle($userObject, $circle)
    {
        $users = $circle->getUsers();

        foreach($users as $user){
            $this->getService('edweld_acl_service')->grant($user, new UserViewerRole($user, $userObject));
            $this->getService('edweld_acl_service')->grant($userObject, new UserViewerRole($userObject, $user));
        }
        foreach($circle->getEvents() as $event)
        {
            $this->getService('edweld_acl_service')->grant($userObject, new EventViewerRole($userObject, $event));
        }
        $this->getService('edweld_acl_service')->grant($userObject, new CircleViewerRole($userObject, $circle));

    }

    /*
     * Allow all user's circle users to view an event
     */

    public function addAclAllCirclesToEvent($event)
    {
        $owner = $this->getUser();
        $this->getService('edweld_acl_service')->grant($owner, new EventEditorRole($owner, $event));

        foreach($owner->getCircles() as $circle)
        {
            foreach($circle->getUsers() as $user)
            {
                $this->getService('edweld_acl_service')->grant($user, new EventViewerRole($user, $event));
            }
        }
    }
    /*
     * Allow a specific list of users to view an event
     */
    public function addAclUserArrayToEvent($event, $users){

        $owner = $this->getUser();
        $this->getService('edweld_acl_service')->grant($user, new EventEditorRole($user, $event));

        foreach($users as $user)
        {
            $this->getService('edweld_acl_service')->grant($user, new EventViewerRole($user, $event));
        }
    }

    public function removeUserFromCircle($user, $circle)
    {
        
    }

    public function removeCircle($circle)
    {
    	
    }
}
```

