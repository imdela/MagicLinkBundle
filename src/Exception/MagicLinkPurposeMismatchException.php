<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\Exception;

final class MagicLinkPurposeMismatchException extends MagicLinkException
{
    public function __construct(string $expected, string $actual)
    {
        parent::__construct(sprintf(
            'Magic link purpose "%s" does not match the expected purpose "%s".',
            $actual,
            $expected
        ));
    }
}
