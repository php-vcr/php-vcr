# php-vcr docs site

Renders [`../docs`](../docs) as a searchable, versioned site with [Docusaurus](https://docusaurus.io/).
`docs/` stays the single source of truth — this app only builds it. See
[CONTRIBUTING.md](../CONTRIBUTING.md#previewing-the-rendered-site) for the full picture (versioning,
PR previews).

Uses [pnpm](https://pnpm.io/) — not npm/yarn.

## Install

```bash
pnpm install --frozen-lockfile
```

## Local development

```bash
pnpm start
```

Starts a local dev server with live reload at `http://localhost:3000/php-vcr/`.

## Build

```bash
pnpm build
```

Generates static content into `build/`. Fails on broken links (`onBrokenLinks: 'throw'`).

## Deployment

Handled by GitHub Actions (`docs-preview.yml` for pull requests, `docs-deploy.yml` for `master`/releases) —
not run manually.
