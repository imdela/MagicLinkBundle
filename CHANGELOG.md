# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `MagicLinkManager` — issue, validate, consume and revoke single-use links.
- `MagicLink` entity persisted in the `ossm_magic_link` table (SHA-256 hash of
  the token only; plaintext is transient).
- `DoctrineMagicLinkStore` — hash-at-rest persistence and an atomic single-use
  `UPDATE … WHERE consumed_at IS NULL` consumption path.
- Purpose-namespaced links with `MagicLinkPurposeMismatchException` when a token
  is used for the wrong purpose.
- Optional `subject` (opaque entity reference) and JSON `payload` on links.
- Configurable default TTL (`magic_link.token_ttl`, default 24h), overridable
  per `issue()` call.
- `revokeFor()` — invalidate every outstanding link for a purpose+subject.
- Autoconfigured Doctrine mapping registration so consumers only need a
  migration, not app-side mapping.
- CI matrix (PHP 8.2–8.4 × Symfony 7.4/8.0) with ECS, PHPStan (level max)
  and PHPUnit.
- Dedicated dev container (PHP + `pdo_sqlite` preinstalled) via `task up`/`task
  gate`, so the full test suite runs identically on any machine.
