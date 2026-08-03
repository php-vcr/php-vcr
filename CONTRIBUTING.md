# Contributing to php-vcr

Thanks for looking into contributing. This document covers everything you need to work in this repository:
setup, the checks that must pass before a PR, and — importantly — the expectation that documentation moves
together with code.

## Getting set up

```bash
git clone https://github.com/php-vcr/php-vcr.git
cd php-vcr
composer install
composer test
```

For matrix testing across PHP versions, `docker-compose.yaml` defines `workspace80`–`workspace85` containers
(build contexts in `resources/docker/workspace/`). Prepare a workspace before using it:

```bash
docker compose run --rm workspace80 composer update
```

Always run `composer update` (not just `install`) per workspace before testing — the committed lockfile may
not be compatible with the container's PHP version.

## The mandatory pre-push / pre-PR gate

Before every push and every pull request, run the following locally and make sure all of it passes. Prepare
and run each workspace individually and sequentially — parallel execution across workspaces isn't supported.

```bash
# workspace80: static analysis + style (CI runs these on PHP 8.0)
docker compose run --rm workspace80 composer update
docker compose run --rm workspace80 composer phpstan
docker compose run --rm workspace80 composer cs-fix   # apply fixes, then commit if anything changed
docker compose run --rm workspace80 composer cs        # must report 0 files to fix
docker compose run --rm workspace80 composer ec

# workspace80: tests with lowest and highest deps
docker compose run --rm workspace80 composer update --prefer-lowest
docker compose run --rm workspace80 composer test
docker compose run --rm workspace80 composer update
docker compose run --rm workspace80 composer test

# workspace85: tests with lowest and highest deps
docker compose run --rm workspace85 composer update --prefer-lowest
docker compose run --rm workspace85 composer test
docker compose run --rm workspace85 composer update
docker compose run --rm workspace85 composer test
```

`workspace85` is the highest PHP version available locally; the full 8.0–8.5 × lowest/highest matrix runs in
CI on every push and PR.

## PHP version awareness

php-vcr targets `^8.0,<8.2 | >=8.2.9,<8.6` — PHP 8.0 through 8.5, excluding the broken 8.2.0–8.2.8 patch
range. **Every new file and every change must work on PHP 8.0**, the lowest supported version. Syntax
introduced in PHP 8.1+ (`readonly` properties, enums, fibers, intersection types, …) isn't allowed in new
code unless it's guarded by a version check or confined to a context that only runs on 8.1+.

## Documentation is part of Definition of Done

**This is a hard requirement, no exceptions.** Any change that adds, alters, or removes user-visible
behaviour — a new feature, a bug fix that changes behaviour, a new/changed configuration option, matcher,
hook, storage backend, or event — updates the relevant page(s) under [`docs/`](docs/index.md) in the **same**
pull request.

- **New capability** → new or extended page, with a `> **🆕 Since X.Y**` callout on the new entry.
- **Behaviour change** → update the page, showing before/after where it helps.
- **Deprecation** → `> **🗑️ Deprecated since X.Y**` note on the affected entry.

A pull request that changes behaviour without a matching `docs/` diff is not complete — reviewers should ask
for the documentation before approving, not after merging. See [Writing documentation](#writing-documentation)
below for how to figure out which page(s) are affected and how to write the change consistently.

php-vcr's documentation is a projection of the **current** codebase — never write about planned, proposed, or
"coming soon" functionality. Only document what the code actually does today.

## Writing documentation

### Structure

```text
docs/
  index.md, getting-started.md, requirements.md, upgrading.md
  guides/    — explanation: how a mechanism works and why (how-vcr-works, cassettes, record-modes, request-matching)
  howto/     — task recipes (use-with-phpunit, use-with-codeception, filter-sensitive-data, custom-request-matcher, select-library-hooks, record-soap)
  reference/ — exhaustive lookup, one page per surface (vcr-facade, configuration, request-matchers, library-hooks, storage-backends, events, request-response)
```

Guides explain concepts; how-to pages solve one task; reference pages are for scanning/anchoring, not
reading top to bottom. When you're not sure which bucket a new page belongs in, ask: is a reader trying to
*understand* something (guide), *do* something (how-to), or *look something up* (reference)?

### Every example must be tested before it's written down

Never document a code sample you haven't actually run. Build a small throwaway harness (a local
Composer project requiring php-vcr via a path repository, plus a tiny local HTTP/SOAP dummy server) and run
the example against it — record, then kill the server and confirm replay — before it goes in a page. This
project's documentation has already caught real gotchas this way (for example: redacting a request body in a
`VCR_BEFORE_RECORD` listener breaks replay unless the `body`/`post_fields` matchers are also narrowed) that
would have shipped as broken advice otherwise.

### Page anatomy

Every page:

1. Opens with a one-line summary of what it covers.
2. Shows a minimal runnable example **before** any prose.
3. In guides only: explains the concept. Reference pages skip prose entirely.
4. For reference entries, uses one consistent shape: **Values · Default · Description · Example · Notes**.
5. Marks new/changed entries with a version callout directly under that entry's heading — not retroactively
  on pre-existing entries, and only when the introducing version is known and relevant.
6. Uses callouts for non-obvious constraints: `> **⚠️ Warning**`, `> **📌 Note**`, `> **💡 Tip**`.
7. Ends with a `← Prev · Next →` footer linking to adjacent pages.

Copy an existing page of the same kind (guide/how-to/reference) as your starting skeleton rather than
inventing a new structure.

### Design language

The documentation has its own consistent look, applied on every page: emoji-prefixed callouts (as above),
Mermaid diagrams for flows (rendered natively by GitHub), `<details>` blocks for long or optional content, and
a breadcrumb/prev-next footer. Keep new pages visually consistent with existing ones.

### Previewing the rendered site

`docs/` is the single source of truth and stays readable directly on GitHub — the rendered, searchable,
versioned site is generated from it by a Docusaurus app in [`website/`](website/), using
[pnpm](https://pnpm.io/) (not npm/yarn):

```bash
cd website
pnpm install --frozen-lockfile
pnpm start     # live preview at http://localhost:3000/php-vcr/
pnpm build     # production build, fails on broken links (onBrokenLinks: 'throw')
```

Any pull request touching `docs/**` or `website/**` gets an automatic PR-preview deployment; the bot comment
on the PR links to it once the build finishes — check the rendered result there, not just the raw Markdown.

Released versions are frozen as snapshots under `website/versioned_docs/` and selectable via the version
dropdown in the navbar; `docs/` itself always reflects the unreleased `Next` version. Don't hand-edit
`versioned_docs/` — snapshots are cut by the `docs-version` GitHub Action.

## Commits

Follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `chore:`, `docs:`,
`refactor:`, etc.) — semantic-release derives version bumps and the changelog from these, and malformed
commits are silently excluded. Each logically independent change gets its own commit (a new helper class,
then its usage, then a separate fix, as separate commits), and every commit must leave tests green —
bisect-friendly history matters more than a tidy-looking diff.

## Pull requests

Use the pull request template as-is. Write the test plan as a description of what you actually verified —
which Docker workspaces, which commands, any scenario-specific checks — no unverified checkboxes. Carry over
the milestone and labels from the issue(s) your PR references (see the label table below). No breaking
changes outside of a major release — minor and patch releases must stay backwards-compatible.

## Labels

The only prefixed family is `Status:`. Everything else is flat; colour signals severity (reds = dangerous,
greens = good, greys/blues = neutral).

**Type** — exactly one per issue/PR:

| Label | Meaning |
|---|---|
| `Bug` | Defect in shipped behaviour. |
| `Feature` | New, user-visible capability. |
| `Enhancement` | Refinement of existing behaviour/internals, no new surface. |
| `Deprecation` | Marks code/behaviour for removal in a future major. |
| `BC Break` | Backwards-incompatible — only mergeable into a major-release branch. |
| `Security` | Fixes or hardens a security-relevant path. |
| `Documentation` | README, docs/, code comments, examples — no behaviour change. |
| `CI` | Build / GitHub Actions / Docker workspace / static analysis tooling. |
| `Dependencies` | Composer or GitHub Actions dependency updates. |

**Status** (lifecycle, set during review): `Status: Needs Review` (default on a new PR) → `Status: Needs Work`
→ `Status: Reviewed`. Also available: `Status: Needs Decision`, `Status: Waiting Feedback`,
`Status: Works for me`.

**Triage:** `Help wanted`, `Good first issue`, `Question`, `WIP`, `Backport`, `Stalled`, `Keep open`.

## Reporting issues

Use the issue templates — a bug report or a feature request. Blank issues are disabled so every issue starts
from the right structure. **Never** report a security issue as a public issue — use
[private security advisories](https://github.com/php-vcr/php-vcr/security/advisories/new) instead.
