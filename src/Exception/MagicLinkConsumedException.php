<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\Exception;

final class MagicLinkConsumedException extends MagicLinkException
{
    public function __construct()
    {
        parent::__construct('Magic link has already been consumed.');
    }
}
