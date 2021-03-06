<?php

namespace Edweld\AclBundle\CascadeStrategy;

use Doctrine\ORM\EntityManager;
use Edweld\AclBundle\Entity\Authorization;
use Edweld\AclBundle\Entity\ResourceInterface;
use Edweld\AclBundle\Repository\AuthorizationRepository;
use Edweld\AclBundle\ResourceGraph\CascadingResourceGraphTraverser;
use Edweld\AclBundle\ResourceGraph\ResourceGraphTraverser;
use Edweld\AclBundle\ResourceGraph\ResourceGraphTraverserDispatcher;

/**
 * Simple cascade: authorizations are cascaded from a resource to its sub-resources.
 * @author Ed Weld <edweld@gmail.com>
 *
 */
class SimpleCascadeStrategy implements CascadeStrategy
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var ResourceGraphTraverserDispatcher
     */
    private $resourceGraphTraverser;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;

        $this->resourceGraphTraverser = new ResourceGraphTraverserDispatcher();
        // Default traverser for CascadingResource
        $this->resourceGraphTraverser->setResourceGraphTraverser(
            'Edweld\AclBundle\Model\CascadingResource',
            new CascadingResourceGraphTraverser($entityManager, $this->resourceGraphTraverser)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function cascadeAuthorization(Authorization $authorization, ResourceInterface $resource)
    {
        $subResources = $this->resourceGraphTraverser->getAllSubResources($resource);

        // Cascade authorizations
        $authorizations = [];
        foreach ($subResources as $subResource) {
            $authorizations[] = $authorization->createChildAuthorization($subResource);
        }

        return $authorizations;
    }

    /**
     * {@inheritdoc}
     */
    public function processNewResource(ResourceInterface $resource)
    {
        /** @var AuthorizationRepository $repository */
        $repository = $this->entityManager->getRepository('Edweld\AclBundle\Entity\Authorization');

        $parentResources = $this->resourceGraphTraverser->getAllParentResources($resource);

        // Find root authorizations on the parent resources
        $authorizationsToCascade = [];
        foreach ($parentResources as $parentResource) {
            $authorizationsToCascade = array_merge(
                $authorizationsToCascade,
                $repository->findCascadableAuthorizationsForResource($parentResource)
            );
        }

        // Cascade them
        $authorizations = [];
        foreach ($authorizationsToCascade as $authorizationToCascade) {
            /** @var Authorization $authorizationToCascade */
            $authorizations[] = $authorizationToCascade->createChildAuthorization($resource);
        }

        return $authorizations;
    }

    /**
     * @param string                 $entityClass
     * @param ResourceGraphTraverser $resourceGraphTraverser
     */
    public function setResourceGraphTraverser($entityClass, $resourceGraphTraverser)
    {
        $this->resourceGraphTraverser->setResourceGraphTraverser($entityClass, $resourceGraphTraverser);
    }
}
