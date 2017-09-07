<?php

namespace Edweld\AclBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
/**
 * Security identity.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edweld@gmail.com>
 */


interface SecurityIdentityInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @return Role[]
     */
    public function getPermissions();

    /**
     * @param Role $role
     */
    public function addPermission( $role);

    /**
     * @param Role $role
     */
    public function removePermission( $role);
}
