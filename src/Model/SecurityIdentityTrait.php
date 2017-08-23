<?php

namespace Edweld\AclBundle\Model;

use Doctrine\Common\Collections\Collection;
use Edweld\AclBundle\Entity\Role;

/**
 * Security identity trait.
 *
 * This trait needs a $roles attribute.
 *
 * @property Role[]|Collection $roles
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edweld@gmail.com>
 */
trait SecurityIdentityTrait
{
    /**
     * @return Role[]
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param Role $role
     */
    public function addRole(Role $role)
    {
        $this->roles[] = $role;
    }

    /**
     * @param Role $role
     */
    public function removeRole(Role $role)
    {
        $this->roles->removeElement($role);
    }
}
