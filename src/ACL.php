<?php

namespace Edweld\AclBundle;

use Doctrine\ORM\EntityManager;
use Edweld\AclBundle\CascadeStrategy\CascadeStrategy;
use Edweld\AclBundle\CascadeStrategy\SimpleCascadeStrategy;
use Edweld\AclBundle\Entity\Actions;
use Edweld\AclBundle\Entity\Authorization;
use Edweld\AclBundle\Entity\ClassResource;
use Edweld\AclBundle\Entity\EntityResource;
use Edweld\AclBundle\Entity\ResourceInterface;
use Edweld\AclBundle\Entity\SecurityIdentityInterface;
use Edweld\AclBundle\Entity\Role;
use Edweld\AclBundle\Repository\AuthorizationRepository;
use Edweld\AclBundle\Repository\RoleRepository;
use Edweld\AclBundle\Model\ContainerTrait;

use Edweld\AclBundle\Doctrine\ACLSetup;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Manages ACL.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edweld@gmail.com>
 */
class ACL
{

    use ContainerTrait;
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var cascadeStrategy
     */
    private $cascadeStrategy;

    /**
     * @param EntityManager        $entityManager
     * @param CascadeStrategy|null $cascadeStrategy The strategy to use for cascading authorizations.
     */
    public function setContainer (ContainerInterface $container)
    {

        $this->container = $container;
    
        $this->entityManager = $this->getDoctrine()->getManager();
        $this->cascadeStrategy = new SimpleCascadeStrategy( $this->entityManager );

        $aclSetup = new ACLSetup($this->entityManager);

        $acl = new ACL($this->entityManager);
        
        $aclSetup->setSecurityIdentityClass('Edweld\AclBundle\Entity\SecurityIdentityInterface');

        $config = $this->container->getParameter('edweld_acl');
        foreach($config['identities'] as $identity){
            $aclSetup->registerRoleClass($identity['class'], $identity['role']);        
        } 
        $acl = new ACL($this->entityManager);
    }

    /**
     * Checks if the identity is allowed to do the action on the resource.
     *
     * @param SecurityIdentityInterface $identity
     * @param string                    $action
     * @param ResourceInterface         $resource
     *
     * @throws \RuntimeException The entity is not persisted (ID must be not null).
     * @return boolean Is allowed, or not.
     */
    public function isAllowed( $identity, $action, ResourceInterface $resource)
    {
        /** @var AuthorizationRepository $repo */
        $repo = $this->entityManager->getRepository('Edweld\AclBundle\Entity\Authorization');
        if ($resource instanceof EntityResource) {
            return $repo->isAllowedOnEntity($identity, $action, $resource);
        } elseif ($resource instanceof ClassResource) {
            return $repo->isAllowedOnEntityClass($identity, $action, $resource->getClass());
        }

        throw new \RuntimeException('Unknown type of resource: ' . get_class($resource));
    }

    /**
     * Give an authorization from a role to a resource.
     *
     * This method should only be called in roles.
     *
     * @param Role              $role
     * @param Actions           $actions
     * @param ResourceInterface $resource
     * @param bool              $cascade  Should the authorization cascade to sub-resources?
     */
    public function allow(Role $role, Actions $actions, ResourceInterface $resource, $cascade = true)
    {
        $authorization = Authorization::create($role, $actions, $resource, $cascade);
        if ($cascade) {
            $cascadedAuthorizations = $this->cascadeStrategy->cascadeAuthorization($authorization, $resource);

            $authorizations = array_merge([$authorization], $cascadedAuthorizations);
        } else {
            $authorizations = [ $authorization ];
        }

        /** @var AuthorizationRepository $repository */
        $repository = $this->entityManager->getRepository('Edweld\AclBundle\Entity\Authorization');

        $repository->insertBulk($authorizations);
    }

    /**
     * Grant a role to a user.
     *
     * The role will be flushed in database.
     * The authorizations related to this role will be automatically created.
     *
     * @param SecurityIdentityInterface $identity
     * @param Role                      $role
     */
    public function grant( $identity, Role $role)
    {
        $identity->addRole($role);
        $this->entityManager->persist($role);
        $this->entityManager->flush($role);

        $role->createAuthorizations($this);
    }

    /**
     * Remove a role from a user.
     *
     * The role deletion will be flushed in database.
     * The authorizations will be automatically removed.
     *
     * @param SecurityIdentityInterface $identity
     * @param Role                      $role
     */
    public function revoke( $identity, Role $role)
    {
        $identity->removeRole($role);
        $this->entityManager->remove($role);

        // Authorizations are deleted in cascade in database
        $this->entityManager->flush($role);
    }

    /**
     * @deprecated Deprecated in favor of revoke(). Will be removed in next major version.
     * @see revoke()
     */
    public function unGrant( $identity, Role $role)
    {
        $this->revoke($identity, $role);
    }

    /**
     * Process a new resource that has been persisted.
     *
     * Called by the EntityResourcesListener.
     *
     * @param EntityResource $resource
     */
    public function processNewResource(EntityResource $resource)
    {
        $cascadedAuthorizations = $this->cascadeStrategy->processNewResource($resource);

        /** @var AuthorizationRepository $repository */
        $repository = $this->entityManager->getRepository('Edweld\AclBundle\Entity\Authorization');

        $repository->insertBulk($cascadedAuthorizations);
    }

    /**
     * Process a resource that has been deleted.
     *
     * Called by the EntityResourcesListener.
     *
     * @param EntityResource $resource
     */
    public function processDeletedResource(EntityResource $resource)
    {
        /** @var AuthorizationRepository $repository */
        $repository = $this->entityManager->getRepository('Edweld\AclBundle\Entity\Authorization');

        $repository->removeAuthorizationsForResource($resource);
    }

    /**
     * Clears and rebuilds all the authorization for a given resource.
     *
     * @param EntityResource $resource
     */
    public function rebuildAuthorizationsForResource(EntityResource $resource)
    {
        /** @var RoleRepository $roleRepository */
        $roleRepository = $this->entityManager->getRepository('Edweld\AclBundle\Entity\Role');
        // Get all Role applied directly on the Resource.
        $rolesDirectlyLinkedToResource = $roleRepository->findRolesDirectlyLinkedToResource($resource);

        // Deletion of all the old authorizations.
        $this->processDeletedResource($resource);

        // Creation of all the parent authorizations.
        $this->processNewResource($resource);

        // Create Authorizations from Roles attached directly to the resource.
        foreach ($rolesDirectlyLinkedToResource as $role) {
            $role->createAuthorizations($this);
        }
    }

    /**
     * Clears and rebuilds all the authorizations from the roles.
     */
    public function rebuildAuthorizations()
    {
        $roleRepository = $this->entityManager->getRepository('Edweld\AclBundle\Entity\Role');

        // Clear
        $this->entityManager->createQuery('DELETE Edweld\AclBundle\Entity\Authorization')->execute();
        $this->entityManager->clear('Edweld\AclBundle\Entity\Authorization');

        // Regenerate
        foreach ($roleRepository->findAll() as $role) {
            /** @var Role $role */
            $role->createAuthorizations($this);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
