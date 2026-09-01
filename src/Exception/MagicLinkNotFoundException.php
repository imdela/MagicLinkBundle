<?php

declare(strict_types=1);

namespace Mosl\MagicLinkBundle\Exception;

final class MagicLinkNotFoundException extends MagicLinkException
{
    public function __construct()
    {
        parent::__construct('Unknown magic link token.');
    }
}
