<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\Tests\Fake;

use DateTimeImmutable;
use Ossm\MagicLinkBundle\Entity\MagicLink;
use Ossm\MagicLinkBundle\Store\MagicLinkStoreInterface;

/**
 * In-memory stand-in for the Doctrine store. It reproduces the production
 * semantics that the manager depends on (hash lookup, single-use, expiry) so
 * the manager's decision logic can be unit-tested without a database.
 */
class InMemoryMagicLinkStore implements MagicLinkStoreInterface
{
    /**
     * @var array<string, MagicLink> keyed by token hash
     */
    private array $byHash = [];

    public function save(MagicLink $magicLink): void
    {
        $this->byHash[$magicLink->getTokenHash()] = $magicLink;
    }

    public function findByToken(string $plainToken): ?MagicLink
    {
        if ($plainToken === '') {
            return null;
        }

        return $this->byHash[hash('sha256', $plainToken)] ?? null;
    }

    public function consume(string $plainToken, DateTimeImmutable $now): bool
    {
        $link = $this->findByToken($plainToken);

        if ($link === null || $link->isConsumed() || $link->isExpired()) {
            return false;
        }

        $link->setConsumedAt($now);

        return true;
    }

    public function revokeFor(string $purpose, ?string $subject): void
    {
        foreach ($this->byHash as $link) {
            if ($link->getPurpose() === $purpose
                && $link->getSubject() === $subject
                && ! $link->isConsumed()
            ) {
                $link->setConsumedAt(new DateTimeImmutable());
            }
        }
    }
}
