<?php

namespace Edweld\AclBundle\ResourceGraph;

use Edweld\AclBundle\Entity\ResourceInterface;

/**
 * Traverses a resource graph.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edward.weld@mobile-5.com>
 */
interface ResourceGraphTraverser
{
    /**
     * Returns all the parent resources of the given resource recursively.
     *
     * @param ResourceInterface $resource
     *
     * @return ResourceInterface[]
     */
    public function getAllParentResources(ResourceInterface $resource);

    /**
     * Returns all the sub-resources of the given resource recursively.
     *
     * @param ResourceInterface $resource
     *
     * @return ResourceInterface[]
     */
    public function getAllSubResources(ResourceInterface $resource);
}
