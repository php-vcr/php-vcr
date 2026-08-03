# Configuration Reference

> One-liner: `VCR::configure()` returns a fluent `Configuration` object — every option below is a chainable
> setter. Configure **before** `VCR::turnOn()`.

```php
\VCR\VCR::configure()
    ->setCassettePath(__DIR__ . '/fixtures')
    ->setMode(\VCR\VCR::MODE_ONCE);
\VCR\VCR::turnOn();
```

**On this page:** [cassette-path](#cassette-path) · [mode](#mode) · [storage](#storage) · [library-hooks](#library-hooks) · [request-matchers](#request-matchers) · [white/blacklist](#white---blacklist) · [record-identical-requests](#record-identical-requests)

Every entry below follows the same shape: **Values · Default · Description · Example · Notes**.

## `cassette-path`

- **Setter/Getter:** `setCassettePath(string $path): self` / `getCassettePath(): string`
- **Values:** any existing, readable directory path
- **Default:** `tests/fixtures`
- Where cassette files are read from and written to.

```php
\VCR\VCR::configure()->setCassettePath(__DIR__ . '/tests/fixtures');
```

> **⚠️ Warning:** the directory must already exist — php-vcr validates it and throws if it doesn't.

## `mode`

- **Setter/Getter:** `setMode(string $mode): self` / `getMode(): string`
- **Values:** `new_episodes` · `once` · `none` · `all` (see [`VCR::MODE_*`](vcr-facade.md#mode-constants))
- **Default:** `new_episodes`
- Controls whether/when new HTTP requests are allowed. Full behaviour per mode:
  [Record Modes](../guides/record-modes.md).

```php
\VCR\VCR::configure()->setMode(\VCR\VCR::MODE_ONCE);
```

## `storage`

- **Setter/Getter:** `setStorage(string $name): self` / `getStorage(): string` (returns the resolved class name)
- **Values:** `yaml` · `json` · `blackhole`
- **Default:** `yaml`
- Which [storage backend](storage-backends.md) serializes cassettes to disk.

```php
\VCR\VCR::configure()->setStorage('json');
```

> **💡 Tip:** switch to `json` if you hit a segfault recording very large requests/responses — that's a known
> PCRE backtrack-limit issue with the YAML parser (raise `pcre.backtrack_limit` in `php.ini`, or use `json`).

## `library-hooks`

- **Setter/Getter:** `enableLibraryHooks(string|string[] $hooks): self` / `getLibraryHooks(): array`
- **Values:** any subset of `stream_wrapper` · `curl` · `soap`
- **Default:** all three enabled
- Restricts interception to the given hooks only. See [Library Hooks](library-hooks.md) and
  [Select library hooks](../howto/select-library-hooks.md).

```php
\VCR\VCR::configure()->enableLibraryHooks(['curl']);
```

## `request-matchers`

- **Setter/Getter:** `enableRequestMatchers(array $matchers): self` / `getRequestMatchers(): array` (callables)
- **Values:** any subset of the 8 built-in matcher names (see [Request Matchers](request-matchers.md)), plus
  any name registered via `addRequestMatcher()`
- **Default:** all 8 built-in matchers enabled
- **Throws:** `\InvalidArgumentException` if a name doesn't exist among available matchers.

```php
\VCR\VCR::configure()->enableRequestMatchers(['method', 'url', 'host']);
```

### `addRequestMatcher(string $name, callable $callback): self`

- **Params:** `$callback` — `function (\VCR\Request $recorded, \VCR\Request $incoming): bool`
- Registers a custom matcher under `$name`. Must still be turned on via `enableRequestMatchers()`.
  See [Custom request matcher](../howto/custom-request-matcher.md).

```php
\VCR\VCR::configure()
    ->addRequestMatcher('always_true', fn ($a, $b) => true)
    ->enableRequestMatchers(['method', 'always_true']);
```

## White- & Blacklist

- **Setter/Getter:** `setWhiteList(string|string[] $paths): self` / `getWhiteList(): array` and
  `setBlackList(string|string[] $paths): self` / `getBlackList(): array`
- **Values:** substrings of file paths — a path is scanned if it contains a whitelist entry (or the whitelist
  is empty) **and** does not contain any blacklist entry
- **Default:** whitelist `[]` (everything); blacklist
  `['src/VCR/LibraryHooks/', 'src/VCR/Util/SoapClient', 'src/VCR/Util/StreamProcessor', 'tests/VCR/Filter']`
- Controls which files the `curl`/`soap` code-rewriting scans. Narrowing this speeds up test runs.

```php
\VCR\VCR::configure()
    ->setWhiteList(['vendor/guzzlehttp'])
    ->setBlackList(['vendor/guzzlehttp/guzzle/tests']);
```

> **⚠️ Warning:** the default blacklist exists to stop php-vcr from rewriting its own internals (infinite
> recursion). If you ever relocate those paths, update the blacklist to match.

## `record-identical-requests`

- **Setter/Getter:** `setRecordIdenticalRequests(bool $enabled): self` / `getRecordIdenticalRequests(): bool`
- **Values:** `true` / `false`
- **Default:** `true`
- When `true`, identical requests are recorded/replayed in sequence (each gets its own `index`). When `false`,
  every identical request replays the **first** recorded response regardless of how many times it's made.
  See [Cassettes → identical requests](../guides/cassettes.md#identical-requests).

```php
\VCR\VCR::configure()->setRecordIdenticalRequests(false);
```

---
← [VCR Facade](vcr-facade.md) · Next: [Request Matchers](request-matchers.md) →
