# Storage Backends Reference

> One-liner: cassettes are files on disk, serialized as YAML (default), JSON, or nowhere at all (Blackhole).

**On this page:** [yaml](#yaml) · [json](#json) · [blackhole](#blackhole) ·
[Custom storage backend](#custom-storage-backend) · [Cassette file naming](#cassette-file-naming)

Select via [`setStorageFactory()`](configuration.md#storage-factory). All three implement
`PurgeableStorageInterface`, so all three work with [`MODE_ALL`](../guides/record-modes.md#all).

## `yaml`

- **Default.**
- **Factory:** `\VCR\Storage\YamlStorageFactory`
- One YAML list entry per recording, appended as it's recorded — streamed one record at a time rather than
  parsed whole into memory.

```yaml
-
    request:
        method: GET
        url: 'http://example.com/hello'
        headers:
            Host: example.com
    response:
        status: { code: 200, message: OK }
        headers: { Content-Type: text/plain }
        body: 'Hello, php-vcr!'
    index: 0
```

> **⚠️ Warning:** very large requests/responses can hit a PCRE backtrack-limit segfault in Symfony's YAML
> parser. Raise `pcre.backtrack_limit` in `php.ini`, or switch to `json`.

## `json`

- **Factory:** `\VCR\Storage\JsonStorageFactory`
- Pretty-printed JSON array, parsed/written incrementally (character-by-character) rather than all at once.

```json
[
    {
        "request": { "method": "GET", "url": "http:\/\/example.com\/hello", "headers": { "Host": "example.com" } },
        "response": { "status": { "code": 200, "message": "OK" }, "body": "Hello, php-vcr!" },
        "index": 0
    }
]
```

## `blackhole`

- **Factory:** `\VCR\Storage\BlackholeStorageFactory`
- Discards everything. `storeRecording()` and `purge()` are no-ops, `isNew()` always returns `true`, and the
  iterator is always empty — nothing is ever replayed.

> **📌 Note:** unlike `yaml`/`json`, Blackhole never touches the filesystem at all — no cassette file is
> created, even after `insertCassette()`.

- Useful for smoke-testing library-hook behaviour without leaving cassette files behind.

## Custom storage backend

> **🆕 Since 1.12**

Any backend — not just files on disk — can serialize cassettes as long as it implements
`\VCR\Storage\StorageFactoryInterface`:

```php
interface StorageFactoryInterface
{
    public function create(string $cassettePath, string $cassetteName): StorageInterface;
}
```

`create()` returns a `\VCR\Storage\StorageInterface` — an `\Iterator` over recorded request/response pairs plus
`storeRecording()` and `isNew()`. Implement `\VCR\Storage\PurgeableStorageInterface` (adds `purge()`) instead if
the backend should support [`MODE_ALL`](../guides/record-modes.md#all).

The three built-ins above are ordinary `StorageFactoryInterface` implementations and a good template to
start from. See [Custom storage backend](../howto/custom-storage.md) for two runnable examples, including one
backed by a database.

## Cassette file naming

- The file is named **exactly** the cassette name passed to `insertCassette()` — no `.yml`/`.json` extension
  is appended automatically.
- A cassette name containing a path separator (e.g. `'api/users'`) auto-creates the subfolder under the
  configured [cassette path](configuration.md#cassette-path).
- Every recorded entry carries an `index` (see [Cassettes → identical requests](../guides/cassettes.md#identical-requests)).

---
← [Library Hooks](library-hooks.md) · Next: [Events](events.md) →
