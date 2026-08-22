<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\Tests\Unit\Manager;

use DateTimeImmutable;
use Ossm\MagicLinkBundle\Entity\MagicLink;
use Ossm\MagicLinkBundle\Exception\MagicLinkConsumedException;
use Ossm\MagicLinkBundle\Exception\MagicLinkException;
use Ossm\MagicLinkBundle\Exception\MagicLinkExpiredException;
use Ossm\MagicLinkBundle\Exception\MagicLinkNotFoundException;
use Ossm\MagicLinkBundle\Exception\MagicLinkPurposeMismatchException;
use Ossm\MagicLinkBundle\Manager\MagicLinkManager;
use Ossm\MagicLinkBundle\Tests\Fake\InMemoryMagicLinkStore;
use PHPUnit\Framework\TestCase;

final class MagicLinkManagerTest extends TestCase
{
    private const PURPOSE = 'candidate_portal';

    private InMemoryMagicLinkStore $store;

    private MagicLinkManager $manager;

    protected function setUp(): void
    {
        $this->store = new InMemoryMagicLinkStore();
        $this->manager = new MagicLinkManager($this->store, 3600);
    }

    public function testIssueGeneratesA64CharTokenAndPersistsOnlyItsHash(): void
    {
        $link = $this->manager->issue(self::PURPOSE, 'applicant-1');

        self::assertNotNull($link->getToken());
        self::assertSame(64, strlen((string) $link->getToken()));
        self::assertSame(hash('sha256', (string) $link->getToken()), $link->getTokenHash());
        // The link must be findable through the store by its plaintext token.
        self::assertNotNull($this->store->findByToken((string) $link->getToken()));
    }

    public function testIssueCarriesPurposeSubjectAndPayload(): void
    {
        $link = $this->manager->issue(self::PURPOSE, 'applicant-1', [
            'role' => 'candidate',
        ]);

        self::assertSame(self::PURPOSE, $link->getPurpose());
        self::assertSame('applicant-1', $link->getSubject());
        self::assertSame([
            'role' => 'candidate',
        ], $link->getPayload());
    }

    public function testIssueAppliesCustomTtl(): void
    {
        $now = new DateTimeImmutable();
        $link = $this->manager->issue(self::PURPOSE, null, [], 120);

        self::assertGreaterThan($now->getTimestamp(), $link->getExpiresAt()->getTimestamp());
        self::assertLessThanOrEqual(
            $now->modify('+121 seconds')
                ->getTimestamp(),
            $link->getExpiresAt()
                ->getTimestamp()
        );
    }

    public function testIssueRejectsZeroTtl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->issue(self::PURPOSE, null, [], 0);
    }

    public function testIssueRejectsNegativeTtl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->issue(self::PURPOSE, null, [], -60);
    }

    public function testIssueCarriesNestedArrayPayload(): void
    {
        $payload = [
            'role' => 'candidate',
            'meta' => [
                'source' => 'referral',
                'scores' => [1, 2, 3],
            ],
        ];

        $link = $this->manager->issue(self::PURPOSE, 'applicant-1', $payload);

        self::assertSame($payload, $link->getPayload());
        self::assertSame(
            $payload,
            $this->manager->validate((string) $link->getToken(), self::PURPOSE)->getPayload()
        );
    }

    public function testIssueFallsBackToConfiguredDefaultTtl(): void
    {
        $manager = new MagicLinkManager($this->store); // DEFAULT_TTL = 86400
        $now = new DateTimeImmutable();

        $link = $manager->issue(self::PURPOSE);

        self::assertGreaterThan(
            $now->modify('+86399 seconds')
                ->getTimestamp(),
            $link->getExpiresAt()
                ->getTimestamp()
        );
        self::assertLessThanOrEqual(
            $now->modify('+86401 seconds')
                ->getTimestamp(),
            $link->getExpiresAt()
                ->getTimestamp()
        );
    }

    public function testValidateReturnsTheLinkForAValidToken(): void
    {
        $link = $this->manager->issue(self::PURPOSE, 'applicant-1', [
            'role' => 'candidate',
        ]);

        $found = $this->manager->validate((string) $link->getToken(), self::PURPOSE);

        self::assertSame($link->getTokenHash(), $found->getTokenHash());
        self::assertSame('applicant-1', $found->getSubject());
        self::assertSame([
            'role' => 'candidate',
        ], $found->getPayload());
    }

    public function testValidateUnknownTokenThrowsNotFoundException(): void
    {
        $this->expectException(MagicLinkNotFoundException::class);

        $this->manager->validate(str_repeat('f', 64), self::PURPOSE);
    }

    public function testValidateWrongPurposeThrowsPurposeMismatchException(): void
    {
        $link = $this->manager->issue(self::PURPOSE);

        $this->expectException(MagicLinkPurposeMismatchException::class);

        $this->manager->validate((string) $link->getToken(), 'signup_confirmation');
    }

    public function testPurposeMismatchExceptionDoesNotLeakEitherPurposeInItsMessage(): void
    {
        $link = $this->manager->issue(self::PURPOSE);

        try {
            $this->manager->validate((string) $link->getToken(), 'signup_confirmation');
            self::fail('Expected a MagicLinkPurposeMismatchException to be thrown.');
        } catch (MagicLinkPurposeMismatchException $e) {
            // A caller that logs or displays getMessage() must not learn what
            // other purpose this token is actually valid for.
            self::assertStringNotContainsString(self::PURPOSE, $e->getMessage());
            self::assertStringNotContainsString('signup_confirmation', $e->getMessage());
            self::assertSame('signup_confirmation', $e->getExpectedPurpose());
            self::assertSame(self::PURPOSE, $e->getActualPurpose());
        }
    }

    public function testValidateConsumedTokenThrowsConsumedException(): void
    {
        $link = $this->manager->issue(self::PURPOSE);
        $this->manager->consume((string) $link->getToken(), self::PURPOSE);

        $this->expectException(MagicLinkConsumedException::class);

        $this->manager->validate((string) $link->getToken(), self::PURPOSE);
    }

    public function testValidateExpiredTokenThrowsExpiredException(): void
    {
        $now = new DateTimeImmutable();
        $link = (new MagicLink())
            ->setTokenHash(hash('sha256', 'expired-token'))
            ->setPurpose(self::PURPOSE)
            ->setCreatedAt($now)
            ->setExpiresAt($now->modify('-1 second'));
        $this->store->save($link);

        $this->expectException(MagicLinkExpiredException::class);

        $this->manager->validate('expired-token', self::PURPOSE);
    }

    public function testConsumeMarksTheLinkConsumedOnce(): void
    {
        $link = $this->manager->issue(self::PURPOSE);
        $token = (string) $link->getToken();

        $consumed = $this->manager->consume($token, self::PURPOSE);

        self::assertNotNull($consumed->getConsumedAt());
        self::assertTrue($consumed->isConsumed());

        $this->expectException(MagicLinkConsumedException::class);

        $this->manager->consume($token, self::PURPOSE);
    }

    public function testConsumeOnANeverIssuedTokenThrowsNotFoundException(): void
    {
        $this->expectException(MagicLinkNotFoundException::class);

        $this->manager->consume(str_repeat('a', 64), self::PURPOSE);
    }

    public function testRevokeForBeforeUseMakesTheLinkUnconsumable(): void
    {
        $link = $this->manager->issue(self::PURPOSE, 'applicant-1');

        $this->manager->revokeFor(self::PURPOSE, 'applicant-1');

        $this->expectException(MagicLinkConsumedException::class);

        $this->manager->consume((string) $link->getToken(), self::PURPOSE);
    }

    public function testConsumeWhenTheStoreLosesTheRaceThrowsConsumedException(): void
    {
        $store = new class() extends InMemoryMagicLinkStore {
            public function consume(string $plainToken, DateTimeImmutable $now): bool
            {
                // Simulate another request consuming the token in between the
                // validate() and consume() calls.
                return false;
            }
        };
        $manager = new MagicLinkManager($store, 3600);
        $link = $manager->issue(self::PURPOSE);

        $this->expectException(MagicLinkConsumedException::class);

        $manager->consume((string) $link->getToken(), self::PURPOSE);
    }

    public function testRevokeForInvalidatesOnlyMatchingLinks(): void
    {
        $linkA = $this->manager->issue(self::PURPOSE, 'applicant-1');
        $linkB = $this->manager->issue(self::PURPOSE, 'applicant-1');
        $linkOther = $this->manager->issue(self::PURPOSE, 'applicant-2');

        $this->manager->revokeFor(self::PURPOSE, 'applicant-1');

        $this->assertUnusable((string) $linkA->getToken());
        $this->assertUnusable((string) $linkB->getToken());
        self::assertSame(
            'applicant-2',
            $this->manager->validate((string) $linkOther->getToken(), self::PURPOSE)->getSubject()
        );
    }

    public function testRevokeForWithNullSubjectOnlyInvalidatesNullSubjectLinks(): void
    {
        $linkWithoutSubject = $this->manager->issue(self::PURPOSE);
        $linkWithSubject = $this->manager->issue(self::PURPOSE, 'applicant-1');

        $this->manager->revokeFor(self::PURPOSE, null);

        $this->assertUnusable((string) $linkWithoutSubject->getToken());
        self::assertSame(
            'applicant-1',
            $this->manager->validate((string) $linkWithSubject->getToken(), self::PURPOSE)->getSubject()
        );
    }

    private function assertUnusable(string $plainToken): void
    {
        try {
            $this->manager->validate($plainToken, self::PURPOSE);
            self::fail('Expected a MagicLinkException to be thrown.');
        } catch (MagicLinkException) {
            // Expected.
        }
    }
}
