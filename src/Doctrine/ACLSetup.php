<?php

namespace Edweld\AclBundle\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use AppBundle\Entity\User;

/**
 * Configures the entity manager.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edweld@gmail.com>
 */
class ACLSetup
{
    /**
     * @var ACLMetadataLoader
     */
    private $metadataLoader;

    /**
     * @var string
     */
    private $securityIdentityClass;

    public function __construct()
    {
        $this->metadataLoader = new ACLMetadataLoader();
    }

    public function setUpEntityManager(EntityManager $entityManager, callable $aclLocator)
    {
        if ($this->securityIdentityClass === null) {
            throw new \RuntimeException(
                'The security identity class must be configured: call ->setSecurityIdentityClass("...")'
            );
        }

        $evm = $entityManager->getEventManager();

        // Configure which entity implements the SecurityIdentityInterface
        $rtel = new ResolveTargetEntityListener();
        $rtel->addResolveTargetEntity('AppBundle\\Entity\\User', $this->securityIdentityClass, []);
        $evm->addEventListener(Events::loadClassMetadata, $rtel);

        // Register the metadata loader
        $evm->addEventListener(Events::loadClassMetadata, $this->metadataLoader);

        // Register the listener that looks for new resources
        $evm->addEventSubscriber(new EntityResourcesListener($aclLocator));
    }

    /**
     * Register which class is the security identity. Must be called exactly once.
     *
     * @param string $class
     *
     * @throws \InvalidArgumentException The given class doesn't implement SecurityIdentityInterface
     */
    public function setSecurityIdentityClass($class)
    {

        $this->securityIdentityClass = $class;
    }

    /**
     * Dynamically register a role subclass in the discriminator map for the Doctrine mapping.
     *
     * @param string $class
     * @param string $shortName
     *
     * @throws \InvalidArgumentException The given class doesn't extend Edweld\AclBundle\Entity\Role
     */
    public function registerRoleClass($class, $shortName)
    {
        $this->metadataLoader->registerRoleClass($class, $shortName);
    }

    /**
     * Registers an alternative "Actions" class to use in the authorization entity.
     *
     * This allows to write your own actions.
     *
     * @param string $class
     *
     * @throws \InvalidArgumentException The given class doesn't extend Edweld\AclBundle\Entity\Actions
     */
    public function setActionsClass($class)
    {
        $this->metadataLoader->setActionsClass($class);
    }
}
