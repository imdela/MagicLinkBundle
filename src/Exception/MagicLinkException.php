<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\Exception;

/**
 * Base class for every magic-link failure. Catching this is enough to render a
 * generic "link invalid or expired" response without leaking why.
 */
class MagicLinkException extends \RuntimeException
{
}
