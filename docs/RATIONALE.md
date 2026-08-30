# Why This Bundle Exists

See the [README](../README.md) for what the bundle does and how to use it,
and [docs/INTEGRATION.md](INTEGRATION.md) for installing it into a host app.

## The problem it solves

A link that grants access to something **without requiring an account** —
someone gets a URL by email, clicks it, and can act (upload a document,
confirm a booking, view a shared resource). The link itself is the
credential. No login, no password, no `UserInterface`.

This pattern has a name: the W3C calls it a
[**capability URL**](https://www.w3.org/wiki/Capability_URLs). Real-world
examples: a Dropbox password-reset link, a Doodle poll link, a private
GitHub gist URL, a Google Calendar private-sharing URL.

It is a different problem from **account-bound** single-use links —
signup confirmation, invitation acceptance, password reset — which all
resolve to an existing `User` entity. Symfony already has good primitives
for that shape (`login_link`, `UriSigner` tied to a user's state). This
bundle is specifically for the account-less case.

## Where this came from

This bundle started as a fix for a real bug: a candidate-portal magic link
in an internal HR application that was never marked as consumed after use,
so the same link could be replayed indefinitely. The natural first instinct
was to reach for Symfony's built-in `login_link` — but `login_link` requires
the target to implement `UserInterface`. The portal's `Applicant` entity
deliberately has no account, so that path was a dead end.

Before writing a single line of custom token logic, the question was: is
this a solved problem already? Building throwaway account-less-token code
inside one app is the kind of thing that quietly gets rebuilt badly in five
other places over time — worth checking properly first.

## What already exists — and why it wasn't enough

**Symfony ecosystem**: no package does "single-use link, no account, atomic
consumption" as a general-purpose bundle. Checked via the Packagist search
API, the official Symfony Bundles directory, and community discussion. The
closest matches, and why each falls short:

| Package | What it does | Why it doesn't fit |
|---|---|---|
| Symfony `login_link` (core) | Single-use, signed, auto-expiring login link | Requires `UserInterface` — no account-less support |
| `3brs/enterprise-security-bundle` | Expiring links | No single-use / consumption tracking |
| `tilleuls/url-signer-bundle`, `dsentker/url-signature-bundle` | Signed, expiring URLs | Signature validity ≠ single-use; nothing tracks "already used" |
| An abandoned 2020 prototype | Closest conceptual match found | Unmaintained, never reached a usable state |

**Symfony 8.2's `UriSigner` single-use support** (merged mid-2026, shipping
in the 8.2 release): the closest thing to a real primitive. It can make a
signed URL single-use — but only when the action *itself* flips some state
that then serves as part of the signature key (the textbook example is a
password-reset link: using it changes the password, and the new password
hash invalidates the old signature). That's "the lock, not the door": it
gives you a mechanism, not a store. It does not fit a case like "browse and
upload documents on a portal," where the action doesn't naturally produce a
new value to sign against — you still need something to explicitly record
"this token has been consumed," expire it, and revoke it. Symfony 8.2 was
evaluated as a possible foundation to build on rather than reinvent, but its
primitive alone doesn't cover the storage/consumption-tracking half of the
problem.

**Outside the Symfony/PHP world**: Laravel has this solved —
[`cesargb/laravel-magiclink`](https://github.com/cesargb/laravel-magiclink)
(461 GitHub stars, 1.39M downloads at the time of checking) does close to
exactly this: single-use, expiring, account-less links. It confirms the
pattern is a real, common need — just not available for Symfony.

## The decision

Nothing in the Symfony ecosystem covered "single-use capability link,
stored and atomically consumed, without an account." Given a real gap and a
proven pattern (Laravel's package, the W3C's own naming of the concept), the
fix was built as a standalone, reusable bundle instead of a one-off inside
the original application — modeled on
[`osm-bridge-bundle`](https://github.com/imdela/OSMBridgeBundle), an earlier
bundle from the same author, for its project structure (Doctrine
auto-mapping, ECS/PHPStan/PHPUnit gate, GitHub Actions CI matrix).

## Design choices that followed from this

- **Atomic single-use, not a signature trick** — a real `consumed_at IS NULL`
  guard in the database, immune to the "no natural state to sign against"
  limitation that blocks `UriSigner` for this use case.
- **Hash-at-rest** — only the token's SHA-256 hash is stored, so a database
  leak cannot be replayed, matching how the account-bound Symfony primitives
  (confirmation/invitation tokens) already handle their own secrets.
- **Purpose namespacing** — one token type serving multiple use cases
  (portal access, report download, guest access) without the risk of a
  token issued for one purpose being silently accepted for another.
- **Account-agnostic `subject`** — a plain opaque string, not a foreign key
  to any particular entity, so the bundle stays usable for anything, not
  just the original candidate-portal case.
