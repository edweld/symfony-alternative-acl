<?php

namespace Edweld\AclBundle\Model;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Ed Weld <edweld@gmail.com>
 */

trait ContainerTrait
{
    private $container;

    public function setContainer (ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function getDoctrine()
    {
        if (!$this->container->has('doctrine')) {
            throw new \LogicException('The DoctrineBundle is not registered in your application.');
        }

        return $this->container->get('doctrine');
    }

    protected function getUser()
    {
        if (!$this->container->has('security.token_storage')) {
            throw new \LogicException('The SecurityBundle is not registered in your application.');
        }

        if (null === $token = $this->container->get('security.token_storage')->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    protected function getParameter ($name)
    {
        return $this->container->getParameter($name);
    }

    protected function getService($serviceName)
    {
        return $this->container->get($serviceName);
    }
}
