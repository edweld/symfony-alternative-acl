<?php

namespace Edweld\AclBundle\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Edweld\AclBundle\Entity\Actions;
use Edweld\AclBundle\Entity\Role;
use Edweld\AclBundle\Entity\Authorization;
use Edweld\AclBundle\Entity\SecurityIdentityInterface;

/**
 * Loads metadata relative to ACL in Doctrine.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edweld@gmail.com>
 */
class ACLMetadataLoader
{
    /**
     * Discriminator map for roles.
     * @var string[]
     */
    private $roles = [];

    /**
     * @var string
     */
    private $actionsClass;

    /**
     * Dynamically register a role subclass in the discriminator map for the Doctrine mapping.
     *
     * @param string $class
     * @param string $shortName
     *
     * @throws \InvalidArgumentException The given class doesn't extend Edweld\AclBundle\Model\Role
     */
    public function registerRoleClass($class, $shortName)
    {
        if (! is_subclass_of($class, 'Edweld\AclBundle\Entity\Role')) {
            throw new \InvalidArgumentException(sprintf('%s doesn\'t extend Edweld\AclBundle\Entity\Role', $class));
        }

        $this->roles[$shortName] = $class;
    }

    /**
     * Registers an alternative "Actions" class to use in the authorization entity.
     *
     * This allows to write your own actions.
     *
     * @param string $class
     *
     * @throws \InvalidArgumentException The given class doesn't extend Edweld\AclBundle\Model\Actions
     */
    public function setActionsClass($class)
    {
        if (! is_subclass_of($class, 'Edweld\AclBundle\Entity\Actions')) {
            throw new \InvalidArgumentException('The given class doesn\'t extend Edweld\AclBundle\Entity\Actions');
        }

        $this->actionsClass = $class;
    }

    /**
     * Overrides the discriminator maps for class table inheritance for roles and authorizations.
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $eventArgs->getClassMetadata();

        if ($metadata->getName() === 'Edweld\AclBundle\Entity\Role') {
            $metadata->setDiscriminatorMap($this->roles);
        }

        if (($this->actionsClass !== null) && ($metadata->getName() === 'Edweld\AclBundle\Entity\Authorization')) {
            $this->remapActions($metadata, $eventArgs->getEntityManager()->getMetadataFactory());
        }
    }

    private function remapActions(ClassMetadata $metadata, ClassMetadataFactory $metadataFactory)
    {
        $fieldName = 'actions';

        unset($metadata->fieldMappings[$fieldName]);
        unset($metadata->embeddedClasses[$fieldName]);

        // Re-map the embeddable
        $mapping = [
            'fieldName'    => $fieldName,
            'class'        => $this->actionsClass,
            'columnPrefix' => null,
        ];
        $metadata->mapEmbedded($mapping);

        // Remove the existing inlined fields
        foreach ($metadata->fieldMappings as $name => $fieldMapping) {
            if (isset($fieldMapping['declaredField']) && $fieldMapping['declaredField'] === $fieldName) {
                unset($metadata->fieldMappings[$name]);
                unset($metadata->fieldNames[$fieldMapping['columnName']]);
            }
        }

        // Re-inline the embeddable
        $embeddableMetadata = $metadataFactory->getMetadataFor($this->actionsClass);
        $metadata->inlineEmbeddable($fieldName, $embeddableMetadata);
    }
}
