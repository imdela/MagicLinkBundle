<?php

declare(strict_types=1);

namespace Mosl\MagicLinkBundle\Tests\Unit\Store;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration as OrmConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Mosl\MagicLinkBundle\Entity\MagicLink;
use Mosl\MagicLinkBundle\Store\DoctrineMagicLinkStore;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real Doctrine store against an in-memory SQLite database.
 * Skipped when the pdo_sqlite extension is unavailable (e.g. on a host PHP
 * without it) — CI always runs it.
 */
final class DoctrineMagicLinkStoreTest extends TestCase
{
    private EntityManagerInterface $em;

    private DoctrineMagicLinkStore $store;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for this test.');
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $config = new OrmConfiguration();
        $config->setMetadataDriverImpl(new AttributeDriver([dirname(__DIR__, 2) . '/src/Entity']));
        if (PHP_VERSION_ID >= 80400) {
            // PHP's native lazy objects replace Symfony var-exporter's ghost
            // proxies from Doctrine ORM 3.6 onward on PHP >= 8.4.
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir());
            $config->setProxyNamespace('DoctrineProxies');
            $config->setAutoGenerateProxyClasses(true);
        }
        $this->em = new EntityManager($connection, $config);
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema([$this->em->getClassMetadata(MagicLink::class)]);

        $this->store = new DoctrineMagicLinkStore($this->em);
    }

    public function testPersistsOnlyTheTokenHash(): void
    {
        $this->persistLink('plain-token-123');

        $row = $this->em->getConnection()
            ->executeQuery('SELECT token_hash, consumed_at FROM mosl_magic_link')
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame(hash('sha256', 'plain-token-123'), $row['token_hash']);
        self::assertNull($row['consumed_at']);
    }

    public function testFindByTokenResolvesByPlaintext(): void
    {
        $this->persistLink('find-me');

        $link = $this->store->findByToken('find-me');

        self::assertNotNull($link);
        self::assertSame(hash('sha256', 'find-me'), $link->getTokenHash());
        // The plaintext is transient and must never come back from storage.
        self::assertNull($link->getToken());
    }

    public function testFindByTokenUnknownReturnsNull(): void
    {
        $this->persistLink('known-token');

        self::assertNull($this->store->findByToken('unknown-token'));
    }

    public function testFindByTokenEmptyTokenReturnsNull(): void
    {
        self::assertNull($this->store->findByToken(''));
    }

    public function testConsumeIsSingleUse(): void
    {
        $this->persistLink('one-time');

        self::assertTrue($this->store->consume('one-time', new DateTimeImmutable()));
        self::assertFalse($this->store->consume('one-time', new DateTimeImmutable()));
    }

    public function testConsumeUnknownTokenReturnsFalse(): void
    {
        self::assertFalse($this->store->consume('never-issued', new DateTimeImmutable()));
    }

    public function testConsumeExpiredTokenReturnsFalse(): void
    {
        $this->persistLink('old-token', null, (new DateTimeImmutable())->modify('-1 minute'));

        self::assertFalse($this->store->consume('old-token', new DateTimeImmutable()));
    }

    public function testConsumeIsPersisted(): void
    {
        $this->persistLink('persist-me');
        $this->store->consume('persist-me', new DateTimeImmutable());
        $this->em->clear();

        $row = $this->em->getConnection()
            ->executeQuery(
                'SELECT consumed_at FROM mosl_magic_link WHERE token_hash = :hash',
                [
                    'hash' => hash('sha256', 'persist-me'),
                ]
            )
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertNotNull($row['consumed_at']);
    }

    public function testRevokeForMarksOnlyMatchingLinksConsumed(): void
    {
        $this->persistLink('revoke-a', 'applicant-1');
        $this->persistLink('revoke-b', 'applicant-1');
        $this->persistLink('revoke-other', 'applicant-2');

        $this->store->revokeFor('portal', 'applicant-1');

        self::assertTrue($this->store->findByToken('revoke-a')?->isConsumed());
        self::assertTrue($this->store->findByToken('revoke-b')?->isConsumed());
        self::assertFalse($this->store->findByToken('revoke-other')?->isConsumed());
    }

    public function testRevokeForWithNullSubjectOnlyMarksNullSubjectLinksConsumed(): void
    {
        $this->persistLink('null-subject');
        $this->persistLink('with-subject', 'applicant-1');

        $this->store->revokeFor('portal', null);

        self::assertTrue($this->store->findByToken('null-subject')?->isConsumed());
        self::assertFalse($this->store->findByToken('with-subject')?->isConsumed());
    }

    private function persistLink(
        string $plainToken,
        ?string $subject = null,
        ?DateTimeImmutable $expiresAt = null
    ): void {
        $now = new DateTimeImmutable();
        $link = (new MagicLink())
            ->setTokenHash(hash('sha256', $plainToken))
            ->setPurpose('portal')
            ->setSubject($subject)
            ->setCreatedAt($now)
            ->setExpiresAt($expiresAt ?? $now->modify('+1 day'));
        $this->store->save($link);
        $this->em->flush();
        $this->em->clear();
    }
}
