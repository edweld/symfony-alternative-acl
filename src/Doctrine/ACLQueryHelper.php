<?php

namespace Edweld\AclBundle\Doctrine;

use Doctrine\ORM\QueryBuilder;
use Edweld\AclBundle\Entity\Authorization;

/**
 * Helper for the Doctrine query builder to use ACL.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edward.weld@mobile-5.com>
 */
class ACLQueryHelper
{
    /**
     * Joins with the authorizations and filters the results to keep only those authorized.
     *
     * @param QueryBuilder              $qb
     * @param SecurityIdentityInterface $identity
     * @param string                    $action
     * @param string|null               $entityClass Class name of the entity that is the resource in the query.
     *                                               If omitted, it will be guessed from the SELECT.
     * @param string|null               $entityAlias Alias of the entity that is the resource in the query.
     *                                               If omitted, it will be guessed from the SELECT.
     *
     * @throws \RuntimeException The query builder has no "select" part
     */
    public static function joinACL(
        QueryBuilder $qb,
        $identity,
        $action,
        $entityClass = null,
        $entityAlias = null
    ) {
        if ($entityClass === null) {
            $rootEntities = $qb->getRootEntities();
            if (! isset($rootEntities[0])) {
                throw new \RuntimeException('The query builder has no "select" part');
            }
            $entityClass = $rootEntities[0];
        }
        if ($entityAlias === null) {
            $rootAliases = $qb->getRootAliases();
            if (! isset($rootAliases[0])) {
                throw new \RuntimeException('The query builder has no "select" part');
            }
            $entityAlias = $rootAliases[0];
        }

        $qb->innerJoin(
            'Mobile5\AclBundle\Entity\Authorization',
            'authorization',
            'WITH',
            $entityAlias . '.id = authorization.entityId'
        );
        $qb->andWhere('authorization.entityClass = :acl_entity_class');
        $qb->andWhere('authorization.securityIdentity = :acl_identity');
        $qb->andWhere('authorization.actions.' . $action . ' = true');

        $qb->setParameter('acl_identity', $identity);
        $qb->setParameter('acl_entity_class', $entityClass);
    }
}
