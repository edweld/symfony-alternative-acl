<?php

namespace Edweld\AclBundle\Entity;

use Doctrine\ORM\EntityManager;
use Edweld\AclBundle\Entity\ResourceInterface;

/**
 * Resource that cascade authorizations.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edweld@gmail.com>
 */
interface CascadingResource extends ResourceInterface
{
    /**
     * @param EntityManager $entityManager
     * @return CascadingResource[]
     */
    public function getParentResources(EntityManager $entityManager);

    /**
     * @param EntityManager $entityManager
     * @return CascadingResource[]
     */
    public function getSubResources(EntityManager $entityManager);
}
