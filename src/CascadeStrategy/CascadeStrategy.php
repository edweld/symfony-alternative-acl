<?php

namespace Edweld\AclBundle\CascadeStrategy;

use Edweld\AclBundle\Entity\Authorization;
use Edweld\AclBundle\Entity\ResourceInterface;
use Edweld\AclBundle\Entity\EntityResource;

/**
 * Strategy that defines the cascade of authorizations between resources.
 * @author Ed Weld <edward.weld@mobile-5.com>
 */
interface CascadeStrategy
{
    /**
     * @param Authorization     $authorization
     * @param ResourceInterface $resource
     * @return Authorization[]
     */
    public function cascadeAuthorization(Authorization $authorization, ResourceInterface $resource);

    /**
     * @param ResourceInterface $resource
     * @return Authorization[]
     */
    public function processNewResource(ResourceInterface $resource);
}
