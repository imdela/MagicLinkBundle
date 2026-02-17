# Magic links — single-use capability tokens

Notes on a magic-link mechanism I keep reusing across projects: a link that
lets a visitor act without any user account. The URL itself *is* the
credential (the W3C "capability URL" pattern): use it once, it tears.

Design decisions so far:

- **Single-use** — consumption must be atomic; a token can never be used twice.
- **Hash at rest** — only the SHA-256 hash of a token is persisted, never the plaintext.
- **Expiring** — a default TTL, overridable per link.
- **Purpose-namespaced** — a token used for the wrong purpose is rejected.
- **Revocable** — outstanding links of a purpose+subject can be invalidated.
