<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\Exception;

final class MagicLinkPurposeMismatchException extends MagicLinkException
{
    public function __construct(
        private readonly string $expectedPurpose,
        private readonly string $actualPurpose,
    ) {
        // The message intentionally omits both purposes: this exception is
        // thrown for a token that IS valid for some other purpose, and
        // surfacing either value to a caller that logs or displays it would
        // leak what that other purpose is to whoever holds the token.
        parent::__construct('Magic link purpose does not match the expected purpose.');
    }

    /**
     * Programmatic access to the purposes involved, for callers that need to
     * branch on them internally. Never surface these values to the requester.
     */
    public function getExpectedPurpose(): string
    {
        return $this->expectedPurpose;
    }

    public function getActualPurpose(): string
    {
        return $this->actualPurpose;
    }
}
