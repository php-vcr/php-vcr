# VCR Facade Reference

> One-liner: `VCR\VCR` is a static facade — every call forwards to a singleton `Videorecorder` instance.

**On this page:** [Lifecycle methods](#lifecycle-methods) · [Mode constants](#mode-constants) · [Events](#events) · [Misc](#misc)

## Lifecycle methods

### `VCR::turnOn(): void`

- **Throws:** nothing
- Enables all configured [library hooks](library-hooks.md). If VCR is already on, it's turned off first.

```php
\VCR\VCR::turnOn();
```

### `VCR::turnOff(bool $restoreStreamWrappers = false): void`

- **Params:** `$restoreStreamWrappers` — when `true`, also restores PHP's original `http`/`https` stream
  wrappers (only relevant if the `stream_wrapper` hook was enabled).
- Disables all library hooks and ejects the current cassette, if any.

```php
\VCR\VCR::turnOff();
```

### `VCR::insertCassette(string $cassetteName): void`

- **Params:** `$cassetteName` — file name (no extension needed) relative to the configured cassette path.
  May contain path separators to nest cassettes in subfolders.
- **Throws:** `\LogicException` if [`mode`](configuration.md#mode) is `all` and the configured storage doesn't
  implement `PurgeableStorageInterface`.
- Creates the storage + cassette, purges it first if mode is `all`, and enables library hooks.

```php
\VCR\VCR::insertCassette('example');
```

### `VCR::eject(): void`

- **Throws:** `\LogicException` if VCR is off (no cassette can be active).
- Detaches the current cassette. Call this before `turnOff()`.

```php
\VCR\VCR::eject();
```

### `VCR::configure(): Configuration`

- Returns the shared [`Configuration`](configuration.md) object for fluent setup. Must be called **before**
  `turnOn()` for hook/whitelist/blacklist changes to take effect.

```php
\VCR\VCR::configure()->setMode(\VCR\VCR::MODE_ONCE);
```

### `VCR::handleRequest(Request $request): Response`

- **Throws:** `\BadMethodCallException` if no cassette is inserted; `\LogicException` on an unmatched request
  under `none`/`once`.
- The core record/playback dispatcher. Library hooks call this internally — you won't normally call it
  yourself.

## Mode constants

| Constant | Value | See |
|---|---|---|
| `VCR::MODE_NEW_EPISODES` | `'new_episodes'` | [Record Modes](../guides/record-modes.md#new_episodes) |
| `VCR::MODE_ONCE` | `'once'` | [Record Modes](../guides/record-modes.md#once) |
| `VCR::MODE_NONE` | `'none'` | [Record Modes](../guides/record-modes.md#none) |
| `VCR::MODE_ALL` | `'all'` | [Record Modes](../guides/record-modes.md#all) |

## Events

### `VCR::setEventDispatcher(EventDispatcherInterface $dispatcher): void` / `VCR::getEventDispatcher(): EventDispatcherInterface`

- Get/replace the Symfony event dispatcher VCR uses internally. Register listeners on it to hook into the
  record/playback lifecycle — see [Events](events.md).

```php
\VCR\VCR::getEventDispatcher()->addListener(
    \VCR\VCREvents::VCR_BEFORE_RECORD,
    function (\VCR\Event\BeforeRecordEvent $event) { /* ... */ }
);
```

## Misc

### `VCR::resetIndex(): void`

- Resets the [identical-request index](../guides/cassettes.md#identical-requests) table. Called automatically
  on `insertCassette()`; rarely needed directly.

---
← [Getting Started](../getting-started.md) · Next: [Configuration](configuration.md) →
