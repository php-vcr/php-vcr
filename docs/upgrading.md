# Upgrading

> One-liner: breaking changes only ever ship in a major release — full release notes live on
> [GitHub Releases](https://github.com/php-vcr/php-vcr/releases); this page collects upgrade steps by impact
> when a major happens.

**On this page:** [How this page works](#how-this-page-works) · [Current status](#current-status)

## How this page works

Every entry, once one exists, follows the same shape:

- **From → To** — the version range the entry applies to.
- **Impact: High / Medium / Low** — how likely your codebase is to be affected.
- What changed, and the concrete before/after code change needed.

New entries land here in the same PR as the breaking change itself — see the documentation requirement in
[CONTRIBUTING.md](../CONTRIBUTING.md).

## Current status

php-vcr has stayed within the `1.x` line since its first release — no major version bump has happened yet, so
there are no breaking upgrade steps to document. Minor and patch releases are required to stay
backwards-compatible; see the full changelog on
[GitHub Releases](https://github.com/php-vcr/php-vcr/releases) for what shipped in each one.

---
← [Documentation home](index.md)
