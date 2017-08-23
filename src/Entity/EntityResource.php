<?php

namespace Mobile5\AclBundle\Entity;

use Edweld\AclBundle\Entity\ResourceInterface;

/**
 * Entity being a resource.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 * @author Ed Weld <edward.weld@mobile-5.com>
 */
interface EntityResource extends ResourceInterface
{
    /**
     * @return mixed
     */
    public function getId();
}
