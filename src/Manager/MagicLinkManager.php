<?php

declare(strict_types=1);

namespace Mosl\MagicLinkBundle\Manager;

use DateTimeImmutable;
use Mosl\MagicLinkBundle\Entity\MagicLink;
use Mosl\MagicLinkBundle\Exception\MagicLinkConsumedException;
use Mosl\MagicLinkBundle\Exception\MagicLinkException;
use Mosl\MagicLinkBundle\Exception\MagicLinkExpiredException;
use Mosl\MagicLinkBundle\Exception\MagicLinkNotFoundException;
use Mosl\MagicLinkBundle\Exception\MagicLinkPurposeMismatchException;
use Mosl\MagicLinkBundle\Store\MagicLinkStoreInterface;

/**
 * Entry point of the bundle. Issue a link, then validate+consume it on the
 * request that carries the plaintext token.
 *
 * The returned MagicLink entity carries its plaintext token in memory (see
 * getToken()). The application builds the URL, sends it, and discards it —
 * the entity's own unit of work persists only the hash.
 */
final class MagicLinkManager
{
    public const DEFAULT_TTL = 86400;

    public function __construct(
        private readonly MagicLinkStoreInterface $store,
        private readonly int $defaultTtl = self::DEFAULT_TTL,
    ) {
    }

    /**
     * Issue a new single-use link.
     *
     * @param array<string, mixed> $payload arbitrary data carried by the link
     */
    public function issue(
        string $purpose,
        ?string $subject = null,
        array $payload = [],
        ?int $ttl = null,
    ): MagicLink {
        if ($ttl !== null && $ttl < 1) {
            throw new \InvalidArgumentException(sprintf('TTL must be a positive number of seconds, got %d.', $ttl));
        }

        $token = bin2hex(random_bytes(32));

        $now = new DateTimeImmutable();
        $link = (new MagicLink())
            ->setPlainToken($token)
            ->setTokenHash(hash('sha256', $token))
            ->setPurpose($purpose)
            ->setSubject($subject)
            ->setPayload($payload)
            ->setCreatedAt($now)
            ->setExpiresAt($now->modify(sprintf('+%d seconds', $ttl ?? $this->defaultTtl)));

        $this->store->save($link);

        return $link;
    }

    /**
     * Validate a token against its purpose without consuming it. Useful when
     * the consuming request needs to inspect the link before deciding. Raises
     * MagicLinkException (any subclass) when the link is unusable.
     */
    public function validate(string $plainToken, string $purpose): MagicLink
    {
        $link = $this->findUsable($plainToken, $purpose);

        return $link;
    }

    /**
     * Validate and atomically consume a token in one step. Exactly one request
     * ever succeeds for a given token — subsequent calls raise
     * MagicLinkConsumedException. Same failure surface as validate().
     */
    public function consume(string $plainToken, string $purpose): MagicLink
    {
        $link = $this->findUsable($plainToken, $purpose);
        $now = new DateTimeImmutable();

        if (! $this->store->consume($plainToken, $now)) {
            // Lost the race against another request for the same token.
            throw new MagicLinkConsumedException();
        }

        $link->setConsumedAt($now);

        return $link;
    }

    /**
     * Invalidate every still-usable token for a subject+purpose. Called when a
     * new link is issued in place of old ones (token rotation).
     */
    public function revokeFor(string $purpose, ?string $subject): void
    {
        $this->store->revokeFor($purpose, $subject);
    }

    private function findUsable(string $plainToken, string $purpose): MagicLink
    {
        $link = $this->store->findByToken($plainToken);

        if ($link === null) {
            throw new MagicLinkNotFoundException();
        }

        if ($link->getPurpose() !== $purpose) {
            throw new MagicLinkPurposeMismatchException($purpose, $link->getPurpose());
        }

        if ($link->isConsumed()) {
            throw new MagicLinkConsumedException();
        }

        if ($link->isExpired()) {
            throw new MagicLinkExpiredException();
        }

        return $link;
    }
}
