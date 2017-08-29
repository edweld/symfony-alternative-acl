<?php

namespace Edweld\AclBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Edweld\AclBundle\ACL;
use Edweld\AclBundle\Entity\Authorization;
use Symfony\Component\Security\Core\Role\RoleInterface;

use AppBundle\Entity\User;

/**
 * Role.
 *
 * @ORM\Entity(repositoryClass="Edweld\AclBundle\Repository\RoleRepository")
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\Table(name="ACL_Role")
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edward.weld@mobile-5.com>
 */
abstract class Role implements RoleInterface
{
    /**
     * @var int
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @var SecurityIdentityInterface
     * @ORM\ManyToOne(targetEntity="Edweld\aclBundle\Entity\SecurityIdentityInterface", inversedBy="roles")
     */
    protected $securityIdentity;

    /**
     * @var Authorization[]|Collection
     * @ORM\OneToMany(targetEntity="Edweld\AclBundle\Entity\Authorization", mappedBy="role", fetch="EXTRA_LAZY")
     */
    protected $authorizations;

    public function __construct( $identity)
    {
        $this->authorizations = new ArrayCollection();
        $this->securityIdentity = $identity;
    }

    /**
     * @param ACL $acl
     */
    abstract public function createAuthorizations(ACL $acl);

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return SecurityIdentityInterface
     */
    public function getSecurityIdentity()
    {
        return $this->securityIdentity;
    }

    public function getRole()
    {
        
    }
}
