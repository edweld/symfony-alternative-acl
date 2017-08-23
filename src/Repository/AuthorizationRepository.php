<?php

namespace Edweld\AclBundle\Repository;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityRepository;
use Edweld\AclBundle\Entity\Authorization;
use Edweld\AclBundle\Entity\ClassResource;
use Edweld\AclBundle\Entity\EntityResource;
use Edweld\AclBundle\Entity\ResourceInterface;
use Edweld\AclBundle\Entity\SecurityIdentityInterface;

/**
 * Authorizations repository.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edward.weld@mobile-5.com>
 */
class AuthorizationRepository extends EntityRepository
{
    /**
     * Insert authorizations directly in database without using the entity manager.
     *
     * This is much more optimized than using the entity manager.
     * This methods inserts in batch of 1000 inserts, each batch being in a transaction. It is to
     * avoid locking the authorizations table for too long, which could impact other web requests.
     *
     * @param Authorization[] $authorizations
     * @throws \RuntimeException Parent authorizations in the array must appear before their children.
     */
    public function insertBulk(array $authorizations)
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->beginTransaction();

        $tableName = $this->getClassMetadata()->getTableName();

        $i = 0;

        foreach ($authorizations as $authorization) {
            // Check parent authorization is persisted
            $parent = $authorization->getParentAuthorization();
            if ($parent !== null && $parent->getId() === null) {
                throw new \RuntimeException(
                    'An authorization has a parent with no ID. Parent authorizations should appear before their'
                    . ' children in the authorizations array so that they can be persisted first (to have an ID)'
                );
            }

            $data = [
                'role_id'                => $authorization->getRole()->getId(),
                'securityIdentity_id'    => $authorization->getSecurityIdentity()->getId(),
                'parentAuthorization_id' => $parent ? $parent->getId() : null,
                'entity_class'           => $authorization->getEntityClass(),
                'entity_id'              => $authorization->getEntityId(),
                'cascadable'             => (int) $authorization->isCascadable(),
            ];

            foreach ($authorization->getActions()->toArray() as $action => $value) {
                $data['actions_' . $action] = (int) $value;
            }

            $connection->insert($tableName, $data);

            // Set authorization ID (used if parent of other authorizations to be inserted)
            $authorization->setId($connection->lastInsertId());

            // Commit every 1000 inserts to avoid locking the table too long
            if (($i % 1000) === 0) {
                $connection->commit();
                $connection->beginTransaction();
            }

            $i++;
        }

        $connection->commit();
    }

    /**
     * Checks if the identity is allowed to do the action on the entity by searching for at least 1 authorization.
     *
     * @param SecurityIdentityInterface $identity
     * @param string                    $action
     * @param EntityResource            $entity
     *
     * @throws \RuntimeException The entity is not persisted (ID must be not null).
     * @return boolean Is allowed, or not.
     */
    public function isAllowedOnEntity(SecurityIdentityInterface $identity, $action, EntityResource $entity)
    {
        $entityClass = ClassUtils::getClass($entity);

        if ($entity->getId() === null) {
            throw new \RuntimeException(sprintf(
                'The entity resource %s must be persisted (id not null) to be able to test the permissions',
                $entityClass
            ));
        }

        $dql = "SELECT count(authorization)
                FROM Mobile5\\AclBundle\\Entity\\Authorization authorization
                WHERE authorization.entityId = :entityId
                    AND authorization.entityClass = :entityClass
                    AND authorization.securityIdentity = :securityIdentity
                    AND authorization.actions.$action = true";

        $query = $this->_em->createQuery($dql);
        $query->setParameter('entityId', $entity->getId());
        $query->setParameter('entityClass', $entityClass);
        $query->setParameter('securityIdentity', $identity);

        return ($query->getSingleScalarResult() > 0);
    }

    /**
     * Checks if the identity is allowed to do the action on the entity class by searching for at least 1 authorization.
     *
     * @param SecurityIdentityInterface $identity
     * @param string                    $action
     * @param string                    $entityClass
     *
     * @return boolean Is allowed, or not.
     */
    public function isAllowedOnEntityClass(SecurityIdentityInterface $identity, $action, $entityClass)
    {
        $dql = "SELECT count(authorization)
                FROM Mobile5\\AclBundle\\Entity\\Authorization authorization
                WHERE authorization.entityClass = :entityClass
                    AND authorization.securityIdentity = :securityIdentity
                    AND authorization.actions.$action = true";

        $query = $this->_em->createQuery($dql);
        $query->setParameter('entityClass', $entityClass);
        $query->setParameter('securityIdentity', $identity);

        return ($query->getSingleScalarResult() > 0);
    }

    /**
     * Returns authorization for the given resource that are cascadable to sub-resources,
     * i.e. they are "cascadable" and have no parent authorization (we only want "root" authorizations).
     *
     * @param ResourceInterface $resource
     * @return Authorization[]
     */
    public function findCascadableAuthorizationsForResource(ResourceInterface $resource)
    {
        $qb = $this->createQueryBuilder('a');

        // Cascadable
        $qb->where('a.cascadable = true');

        // Root authorizations means no parent
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

    /**
     * Remove all the authorizations that apply to the given resource.
     *
     * @param ResourceInterface $resource
     * @throws \RuntimeException If the resource is an entity, it must be persisted.
     */
    public function removeAuthorizationsForResource(ResourceInterface $resource)
    {
        $qb = $this->_em->createQueryBuilder();

        $qb->delete($this->getEntityName(), 'a');

        if ($resource instanceof EntityResource) {
            if ($resource->getId() === null) {
                throw new \RuntimeException(sprintf(
                    'The entity resource %s must be persisted (id not null) to be able to remove the authorizations',
                    ClassUtils::getClass($resource)
                ));
            }

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

        $qb->getQuery()->execute();
    }
}
