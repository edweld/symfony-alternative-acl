<?php

namespace Edweld\AclBundle\Entity;

use Edweld\AclBundle\Entity\ResourceInterface;

/**
 * Entity being a resource.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edweld@gmail.com>
 */
interface EntityResource extends ResourceInterface
{
    /**
     * @return mixed
     */
    public function getId();
}
