# Upgrading

> One-liner: breaking changes only ever ship in a major release — full release notes live on
> [GitHub Releases](https://github.com/php-vcr/php-vcr/releases); this page collects upgrade steps by impact
> when a major happens.

**On this page:** [How this page works](#how-this-page-works) · [Deprecations](#deprecations) ·
[Current status](#current-status)

## How this page works

Every entry, once one exists, follows the same shape:

- **From → To** — the version range the entry applies to.
- **Impact: High / Medium / Low** — how likely your codebase is to be affected.
- What changed, and the concrete before/after code change needed.

New entries land here in the same PR as the breaking change itself — see the documentation requirement in
[CONTRIBUTING.md](https://github.com/php-vcr/php-vcr/blob/master/CONTRIBUTING.md).

Deprecation entries follow the same shape but mark something that still works in `1.x` and is only removed in
the next major — no upgrade is required until then, but new code should prefer the replacement.

## Deprecations

### Deprecated in 1.12

**Impact: Low** — nothing breaks in `1.x`; the deprecated API keeps working until the next major.

Name-based storage selection (`setStorage()`/`getStorage()`) and the suffixless `Storage`/`PurgeableStorage`
interfaces are deprecated in favour of the typed [`StorageFactoryInterface`](reference/configuration.md#storage-factory)
contract — see [Custom storage backend](howto/custom-storage.md) for why a factory replaces a class-name
string.

| Deprecated | Replacement |
| --- | --- |
| `Configuration::setStorage('json')` | `Configuration::setStorageFactory(new \VCR\Storage\JsonStorageFactory())` |
| `Configuration::getStorage()` | `Configuration::getStorageFactory()` |
| `VCR\Storage\Storage` | `VCR\Storage\StorageInterface` |
| `VCR\Storage\PurgeableStorage` | `VCR\Storage\PurgeableStorageInterface` |

```php
// before
\VCR\VCR::configure()->setStorage('json');

// after
\VCR\VCR::configure()->setStorageFactory(new \VCR\Storage\JsonStorageFactory());
```

`getStorage()` still works for the three built-in storages, but throws `VCRException` once a custom
`StorageFactoryInterface` is configured — its resulting storage class can't be resolved ahead of time. The
built-in storages (`Yaml`, `Json`, `Blackhole`) satisfy both interface generations, so existing
`instanceof PurgeableStorage` checks keep working unchanged.

If you previously injected a custom storage by subclassing `Configuration` and overriding `getStorage()`,
switch to `setStorageFactory()` — the class name returned by `getStorage()` is no longer used to construct the
storage, so an overridden `getStorage()` is now silently ignored.

## Current status

php-vcr has stayed within the `1.x` line since its first release — no major version bump has happened yet, so
there are no breaking upgrade steps to document. Minor and patch releases are required to stay
backwards-compatible; see the full changelog on
[GitHub Releases](https://github.com/php-vcr/php-vcr/releases) for what shipped in each one, and
[Deprecations](#deprecations) above for what to migrate away from ahead of the next one.

---
← [Documentation home](index.md)
