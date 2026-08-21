<?php

declare(strict_types=1);

namespace Ossm\MagicLinkBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Ossm\MagicLinkBundle\Entity\MagicLink;
use PHPUnit\Framework\TestCase;

final class MagicLinkTest extends TestCase
{
    public function testNewEntityDefaults(): void
    {
        $link = new MagicLink();

        self::assertNull($link->getId());
        self::assertNull($link->getConsumedAt());
        self::assertNull($link->getToken());
        self::assertNull($link->getSubject());
        self::assertSame('', $link->getPurpose());
        self::assertSame([], $link->getPayload());
        self::assertFalse($link->isConsumed());
    }

    public function testPlainTokenIsTransient(): void
    {
        $link = (new MagicLink())->setPlainToken('abc');

        self::assertSame('abc', $link->getToken());
    }

    public function testIsExpired(): void
    {
        $now = new DateTimeImmutable();

        $future = (new MagicLink())->setExpiresAt($now->modify('+1 hour'));
        $past = (new MagicLink())->setExpiresAt($now->modify('-1 hour'));

        self::assertFalse($future->isExpired());
        self::assertTrue($past->isExpired());
    }

    public function testIsConsumed(): void
    {
        $link = (new MagicLink())->setConsumedAt(new DateTimeImmutable());

        self::assertTrue($link->isConsumed());
    }

    public function testSettersRoundTrip(): void
    {
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify('+1 day');
        $tokenHash = 'h' . str_repeat('a', 63);

        $link = (new MagicLink())
            ->setTokenHash($tokenHash)
            ->setPurpose('candidate_portal')
            ->setSubject('applicant-1')
            ->setPayload([
                'role' => 'candidate',
            ])
            ->setCreatedAt($now)
            ->setExpiresAt($expiresAt)
            ->setConsumedAt($now);

        self::assertSame($tokenHash, $link->getTokenHash());
        self::assertSame('candidate_portal', $link->getPurpose());
        self::assertSame('applicant-1', $link->getSubject());
        self::assertSame([
            'role' => 'candidate',
        ], $link->getPayload());
        self::assertSame($now, $link->getCreatedAt());
        self::assertSame($expiresAt, $link->getExpiresAt());
        self::assertSame($now, $link->getConsumedAt());
    }
}
