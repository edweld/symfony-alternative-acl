<?php

namespace Edweld\AclBundle\Repository;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityRepository;
use Edweld\AclBundle\Entity\Role;
use Edweld\AclBundle\Entity\ClassResource;
use Edweld\AclBundle\Entity\EntityResource;
use Edweld\AclBundle\Entity\ResourceInterface;

/**
 * Authorizations repository.
 *
 * @author Valentin Claras <dev.myclabs.acl@valentin.claras.fr>
 * @author Ed Weld <edweld@gmail.com>
 */
class RoleRepository extends EntityRepository
{
    /**
     * Returns Roles that are directly linked to the given resource.
     *
     * @param ResourceInterface $resource
     * @return Role[]
     */
    public function findRolesDirectlyLinkedToResource(ResourceInterface $resource)
    {
        $qb = $this->createQueryBuilder('role');

        // Join
        $qb->join('role.authorizations', 'a');

        // Root authorizations means they are attached to the given resource
        $qb->andWhere('a.parentAuthorization IS NULL');

        if ($resource instanceof EntityResource) {
            $qb->andWhere('a.entityClass = :entityClass');
            $qb->andWhere('a.entityId = :entityId');
            $qb->setParameter('entityClass', ClassUtils::getClass($resource));
            $qb->setParameter('entityId', $resource->getId());
        }
        if ($resource instanceof ClassResource) {
            $qb->andWhere('a.entityClass = :entityClass');
            $qb->andWhere('a.entityId IS NULL');
            $qb->setParameter('entityClass', $resource->getClass());
        }

        return $qb->getQuery()->getResult();
    }
}
