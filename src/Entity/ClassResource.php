<?php

namespace Edweld\AclBundle\Entity;

use Doctrine\ORM\EntityManager;
use Edweld\AclBundle\Entity\ResourceInterface;
use Edweld\AclBundle\Entity\CascadingResource;

/**
 * Class resource.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edweld@gmail.com>
 */
final class ClassResource implements ResourceInterface, CascadingResource
{
    /**
     * @var string
     */
    private $class;

    /**
     * @param string $class Class name.
     */
    public function __construct($class)
    {
        $this->class = $class;
    }

    /**
     * Returns the name of the class.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    public function getParentResources(EntityManager $entityManager)
    {
        return [];
    }

    public function getSubResources(EntityManager $entityManager)
    {
        $repository = $entityManager->getRepository($this->class);

        return $repository->findAll();
    }
}
