<?php

declare(strict_types=1);

namespace Mosl\MagicLinkBundle\Store;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Mosl\MagicLinkBundle\Entity\MagicLink;

/**
 * Doctrine-backed store.
 *
 * Security notes:
 *  - Only the SHA-256 hash of a token is ever written to the database, so a
 *    leaked table cannot be replayed.
 *  - Consumption is a single atomic UPDATE guarded by
 *    `consumed_at IS NULL AND expires_at > :now`. Two concurrent requests for
 *    the same token race on this statement and exactly one wins.
 *
 * Persistence discipline: save() does not flush — it is the caller's job (the
 * unit-of-work owner) to decide when the transaction commits.
 */
final class DoctrineMagicLinkStore implements MagicLinkStoreInterface
{
    /**
     * @var EntityRepository<MagicLink>
     */
    private readonly EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        $this->repository = $em->getRepository(MagicLink::class);
    }

    public function save(MagicLink $magicLink): void
    {
        $this->em->persist($magicLink);
    }

    public function findByToken(string $plainToken): ?MagicLink
    {
        if ($plainToken === '') {
            return null;
        }

        return $this->repository->findOneBy([
            'tokenHash' => $this->hash($plainToken),
        ]);
    }

    public function consume(string $plainToken, DateTimeImmutable $now): bool
    {
        if ($plainToken === '') {
            return false;
        }

        $affected = $this->em->createQueryBuilder()
            ->update(MagicLink::class, 'm')
            ->set('m.consumedAt', ':now')
            ->where('m.tokenHash = :tokenHash')
            ->andWhere('m.consumedAt IS NULL')
            ->andWhere('m.expiresAt > :now')
            ->setParameter('tokenHash', $this->hash($plainToken))
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    public function revokeFor(string $purpose, ?string $subject): void
    {
        $qb = $this->em->createQueryBuilder()
            ->update(MagicLink::class, 'm')
            ->set('m.consumedAt', ':now')
            ->where('m.purpose = :purpose')
            ->andWhere('m.consumedAt IS NULL');

        if ($subject === null) {
            $qb->andWhere('m.subject IS NULL');
        } else {
            $qb->andWhere('m.subject = :subject')
                ->setParameter('subject', $subject);
        }

        $qb
            ->setParameter('purpose', $purpose)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
