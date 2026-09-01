# Contributing

Thanks for considering a contribution to `mosl/magic-link-bundle`. See the
[README](README.md) for what the bundle does and how to use it.

## Branches

- `dev` is the active development branch — open pull requests against it.
- `main` tracks the latest released version.
- Releases are tagged directly on `main` following [Semantic Versioning](https://semver.org/).

## Getting set up

```bash
task up          # build and start the dev container (PHP + pdo_sqlite)
task install      # composer install inside it
```

## Before opening a pull request

Run the full quality suite locally — CI runs the same checks on every PR:

```bash
task gate    # audit + ECS + PHPStan (level max) + PHPUnit, inside the dev container
```

All must pass. New behavior should come with test coverage; bug fixes should
include a regression test where practical.

Always use `task gate` (not the host's local PHP) before opening a PR: the
Doctrine store test needs the `pdo_sqlite` extension, which the dev container
has preinstalled but a bare host usually doesn't — running on the host silently
skips that test instead of failing.

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
