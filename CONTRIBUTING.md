# Contributing

Thanks for considering a contribution to `ossm/magic-link-bundle`.

## Branches

- `dev` is the active development branch — open pull requests against it.
- `main` tracks the latest released version.
- Releases are tagged directly on `main` following [Semantic Versioning](https://semver.org/).

## Getting set up

```bash
composer install
```

## Before opening a pull request

Run the full quality suite locally — CI runs the same checks on every PR:

```bash
composer audit          # no open security advisories
vendor/bin/ecs check     # coding standard (add --fix to auto-correct)
vendor/bin/phpstan analyse
vendor/bin/phpunit
```

All four must pass. New behavior should come with test coverage; bug fixes should
include a regression test where practical.

The Doctrine store test runs against an in-memory SQLite database and requires
the `pdo_sqlite` extension. CI always runs it; if it is skipped on your machine,
make sure it passes on CI before merging.

## Commit messages

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): imperative subject

- bullet per change, if more than one
```

Common types: `feat`, `fix`, `refactor`, `docs`, `chore`, `test`. Keep each commit
to one logical change.

## Security

If you find a security issue, please do not open a public GitHub issue. See
[SECURITY.md](SECURITY.md) for how to report it privately.

## Changelog

User-facing changes belong under `[Unreleased]` in [CHANGELOG.md](CHANGELOG.md)
(format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)), in the same
pull request that introduces them.
