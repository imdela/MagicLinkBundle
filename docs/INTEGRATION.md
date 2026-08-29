# Integration Guide

Step-by-step guide for pulling this bundle into a host Symfony application,
verifying the install actually works, and wiring the token lifecycle end to
end. Every command here has been run against a real host app.

## 1. Require the bundle

### Private repository (during development)

While the repository is private, add it as a VCS repository and require the
`dev-dev` branch:

```json
// composer.json
{
    "require": {
        "ossm/magic-link-bundle": "dev-dev"
    },
    "repositories": {
        "magic-link-bundle": {
            "type": "vcs",
            "url": "https://github.com/imdela/MagicLinkBundle.git"
        }
    }
}
```

```bash
composer update ossm/magic-link-bundle
```

A private repository needs a GitHub token composer can authenticate with
(`COMPOSER_AUTH` env var, or `composer config github-oauth.github.com <token>`).
The token must have **explicit access to this repository** — a fine-grained
personal access token created before this repo existed will not have it by
default; add the repo to the token's allowed list on GitHub
(Settings → Developer settings → Fine-grained tokens → edit the token →
Repository access).

Symptom of a missing/under-scoped token:

```
Failed to clone ... : remote: Write access to repository not granted.
fatal: ... returned error: 403
```

### Public repository / tagged release (once published)

```bash
composer require ossm/magic-link-bundle
```

## 2. Register the bundle

`composer require` does **not** register the bundle automatically — this
package ships no Symfony Flex recipe. Add it to `config/bundles.php` by hand:

```php
// config/bundles.php
return [
    // ...
    Ossm\MagicLinkBundle\MagicLinkBundle::class => ['all' => true],
];
```

## 3. Add the configuration

```yaml
# config/packages/magic_link.yaml
magic_link:
    token_ttl: 86400 # optional, defaults to 86400 (24h)
```

## 4. Verify the container boots

Before touching the database, confirm Symfony can actually compile the
container with the bundle registered:

```bash
bin/console lint:container
```

Expected output: `[OK] The container was linted successfully...`

If this fails with `Bundle "..." does not exist or it is not enabled.`, the
bundle's Doctrine mapping registration is broken — that is a bug in the
bundle itself (see `src/MagicLinkBundle.php`), not a host-app configuration
mistake. It happened once during this bundle's own development: the mapping
was keyed by an arbitrary label instead of the bundle's registered kernel
name, and `is_bundle: true` resolution only matches an exact kernel bundle
name. Fixed in commit `1ba7dfc` — make sure you are not on an older ref.

## 5. Run the migration

The bundle registers its own Doctrine mapping (`src/Entity/MagicLink.php`),
so the only schema step needed on the host side is a migration:

```bash
bin/console doctrine:migrations:diff
```

Review the generated migration — it should contain exactly one new table:

```sql
CREATE TABLE ossm_magic_link (
    id INT ... PRIMARY KEY,
    token_hash VARCHAR(64) NOT NULL,
    purpose VARCHAR(64) NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    payload JSON NOT NULL,
    expires_at TIMESTAMP ... NOT NULL,
    created_at TIMESTAMP ... NOT NULL,
    consumed_at TIMESTAMP ... DEFAULT NULL
);
CREATE UNIQUE INDEX ... ON ossm_magic_link (token_hash);
```

Then apply it (dev **and** test databases, if your app has a separate test
DB):

```bash
bin/console doctrine:migrations:migrate --no-interaction
bin/console doctrine:migrations:migrate --no-interaction --env=test  # if applicable
bin/console doctrine:schema:validate
```

`doctrine:schema:validate` must report both the mapping and the database as
in sync before you move on.

## 6. Smoke-test the integration

A minimal functional check that the whole chain — DI wiring, Doctrine
mapping, migration — actually works, without any host-specific business
logic:

```php
<?php
// tests/Functional/MagicLinkSmokeTest.php (adapt namespace/base class to your app)

namespace App\Tests\Functional;

use Ossm\MagicLinkBundle\Exception\MagicLinkConsumedException;
use Ossm\MagicLinkBundle\Manager\MagicLinkManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MagicLinkSmokeTest extends KernelTestCase
{
    public function testIssueValidateConsumeRoundTrip(): void
    {
        self::bootKernel();
        /** @var MagicLinkManager $manager */
        $manager = self::getContainer()->get(MagicLinkManager::class);

        $link = $manager->issue('smoke_test', subject: 'smoke-1');
        $token = (string) $link->getToken();

        // Valid the first time.
        $consumed = $manager->consume($token, 'smoke_test');
        self::assertSame('smoke-1', $consumed->getSubject());

        // Rejected the second time — proves the DB round trip and the
        // single-use guarantee both actually work against this app's DB,
        // not just against the bundle's own test suite.
        $this->expectException(MagicLinkConsumedException::class);
        $manager->consume($token, 'smoke_test');
    }
}
```

Run it against your app's real test database (not SQLite unless that is
what your app actually uses in `test`):

```bash
bin/phpunit tests/Functional/MagicLinkSmokeTest.php
```

If this passes, the integration is verified end to end: the service is
autowired, the entity is mapped, the migration created a working schema, and
a token really can be issued, consumed once, and rejected on reuse.

## 7. Wire it into your feature

Only after the smoke test passes, inject `MagicLinkManager` into your own
service or controller — see the [README](../README.md#usage) for the
`issue()` / `validate()` / `consume()` / `revokeFor()` API and the security
model to follow (never log a token, never surface purpose-mismatch details
to the requester, always serve links over HTTPS).
