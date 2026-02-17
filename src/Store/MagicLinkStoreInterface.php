<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\Store;

use DateTimeImmutable;
use Ossm\MagicLinkBundle\Entity\MagicLink;

/**
 * Storage contract for magic links. Kept behind an interface so applications
 * can swap the default Doctrine implementation for a cache or NoSQL backend.
 */
interface MagicLinkStoreInterface
{
    public function save(MagicLink $magicLink): void;

    /**
     * Look up a link by its plaintext token. Returns null when the token is
     * unknown — callers decide whether to render a generic error.
     */
    public function findByToken(string $plainToken): ?MagicLink;

    /**
     * Atomically mark a token as consumed at $now. Returns true only when the
     * token existed, had not yet been consumed, and had not expired — the
     * single-use guarantee. False means the token is unusable.
     */
    public function consume(string $plainToken, DateTimeImmutable $now): bool;

    /**
     * Invalidate every still-usable token for a subject+purpose, e.g. when a
     * new link is issued and the old ones must stop working immediately.
     */
    public function revokeFor(string $purpose, ?string $subject): void;
}
