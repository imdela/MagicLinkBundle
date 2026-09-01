<?php

declare(strict_types=1);

namespace Mosl\MagicLinkBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A single-use capability link. The link's plaintext token IS the credential —
 * the holder does not need an account (the W3C "capability URL" pattern).
 * Only its SHA-256 hash is stored; the plaintext exists in memory only until
 * the caller has built the URL, then it is gone.
 */
#[ORM\Entity]
#[ORM\Table(name: 'mosl_magic_link')]
class MagicLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * SHA-256 hex digest of the plaintext token (64 chars). The token itself is
     * never persisted so a database leak cannot be replayed.
     */
    #[ORM\Column(name: 'token_hash', length: 64, unique: true)]
    private string $tokenHash = '';

    /**
     * What this link is for (e.g. "candidate_portal", "signup_confirm"). The
     * consuming application routes on this value.
     */
    #[ORM\Column(length: 64)]
    private string $purpose = '';

    /**
     * Optional identifier of the thing the link grants access to (e.g. an
     * applicant id). Kept as a string so the bundle stays entity-agnostic.
     */
    #[ORM\Column(nullable: true)]
    private ?string $subject = null;

    /**
     * Arbitrary data the link carries (e.g. a role to grant on acceptance).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'consumed_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $consumedAt = null;

    /**
     * The plaintext token. Transient by design: set at issue time, never
     * persisted, null after the entity is reloaded from the database.
     *
     * @var non-empty-string|null
     */
    private ?string $plainToken = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getConsumedAt(): ?DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function setConsumedAt(?DateTimeImmutable $consumedAt): self
    {
        $this->consumedAt = $consumedAt;

        return $this;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new DateTimeImmutable();
    }

    /**
     * The plaintext token, or null once the entity has been persisted and
     * reloaded. Never expose this value in any response body.
     */
    public function getToken(): ?string
    {
        return $this->plainToken;
    }

    /**
     * @param non-empty-string $plainToken
     */
    public function setPlainToken(string $plainToken): self
    {
        $this->plainToken = $plainToken;

        return $this;
    }
}
