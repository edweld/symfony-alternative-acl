<?php

namespace Edweld\AclBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class EdweldAclBundle extends Bundle
{
	//@see https://symfony.com/doc/current/doctrine/mapping_model_classes.html 
	public function build(ContainerBuilder $container)
	{

		parent::build($container);

	}
}
