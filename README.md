# MagicLinkBundle

A Symfony bundle for **single-use, expiring capability links** — links that let
a visitor act *without any user account*.

The W3C calls this a [capability URL](https://www.w3.org/wiki/Capability_URLs):
the URL itself *is* the credential. Classic use cases:

- a candidate uploads their documents on a portal handed to them by an email
- a customer confirms a booking or downloads a report
- a guest accessor reaches a resource shared with them

This is **not** the same thing as password-reset / signup-confirmation / invite
links: those always resolve to an existing account. Here there is no account —
the token *is* the access.

## Why another bundle?

Symfony 8.2 added `UriSigner` support for single-use signed URLs, but those only
work when the action itself flips some signed state. When your "single use" is a
real state you need to *store* (a token record you can expire, revoke, and
consume atomically), nothing in the Symfony ecosystem ships it. This bundle fills
that gap.

## Design

| Property | What it means |
|---|---|
| **Single-use** | Consumption is one atomic `UPDATE … WHERE consumed_at IS NULL`. Two concurrent requests for the same token race, and exactly one wins. |
| **Hash at rest** | Only the SHA-256 hash of the token is persisted. A leaked table cannot be replayed. |
| **Expiry** | A default TTL (24h), overridable per `issue()` call. |
| **Purpose** | Links are namespaced (`candidate_portal`, `signup_confirm`, …). A token validated against the wrong purpose is rejected. |
| **Revocable** | `revokeFor()` invalidates every outstanding link of a purpose+subject, e.g. when a new link supersedes old ones. |
| **Account-free** | The `subject` is just an opaque string (e.g. an entity id). The bundle never needs a user. |

## Installation

```bash
composer require ossm/magic-link-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Ossm\MagicLinkBundle\MagicLinkBundle::class => ['all' => true],
];
```

The bundle registers its own Doctrine entity mapping, so the only remaining step
is the migration for the `ossm_magic_link` table:

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

## Configuration

```yaml
# config/packages/magic_link.yaml
magic_link:
    token_ttl: 86400  # default lifetime in seconds, overridable per issue()
```

The key is optional — it defaults to 86400 (24h).

## Usage

Inject `Ossm\MagicLinkBundle\Manager\MagicLinkManager`.

### Issue a link

```php
$link = $this->magicLinkManager->issue(
    purpose: 'candidate_portal',
    subject: (string) $applicant->getId(),
    payload: ['offer' => (string) $offer->getId()],
);

// $link->getToken() is the plaintext credential — build the URL, send it,
// then let it go. Only its hash is ever stored.
$url = $this->router->generate('portal', ['token' => $link->getToken()]);
```

### Validate without consuming

```php
try {
    $link = $this->magicLinkManager->validate($token, 'candidate_portal');
    $applicant = $this->applicantRepository->find($link->getSubject());
} catch (MagicLinkException $e) {
    // render the generic "link invalid or expired" response
}
```

### Validate and consume

```php
$link = $this->magicLinkManager->consume($token, 'candidate_portal');
```

Exactly one request ever succeeds. Every later attempt throws
`MagicLinkConsumedException` (or `MagicLinkExpiredException` /
`MagicLinkNotFoundException`). All failures extend `MagicLinkException`, so a
single `catch` renders a generic error without leaking why.

### Revoke outstanding links

```php
$this->magicLinkManager->revokeFor('candidate_portal', (string) $applicant->getId());
```

## Security model

- **Token generation**: 32 random bytes (`random_bytes`), hex-encoded — 256 bits
  of entropy, unguessable.
- **Storage**: SHA-256 hash only, unique indexed column. The plaintext exists in
  memory on the issuing request and nowhere else.
- **Atomicity**: consumption is a single conditional `UPDATE`, so a token can
  never be used twice, even under concurrency.
- **Timing**: `validate()`/`consume()` compare in constant time on purpose and
  state — a token never "leaks" whether a sibling purpose exists.
- **Rotation**: issuing a replacement link should be paired with `revokeFor()` so
  leaked old links die immediately.
- **Transport**: always serve the link over HTTPS; the token travels in the URL
  and lands in server logs and referrers otherwise.

## Development

```bash
composer install
vendor/bin/ecs check          # coding standard (add --fix to auto-correct)
vendor/bin/phpstan analyse    # static analysis, level max
vendor/bin/phpunit            # unit tests (Doctrine store test needs pdo_sqlite)
```

## License

MIT — see [LICENSE](LICENSE). Not affiliated with the Symfony project.
