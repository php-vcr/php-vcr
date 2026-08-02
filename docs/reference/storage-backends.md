# Storage Backends Reference

> One-liner: cassettes are files on disk, serialized as YAML (default), JSON, or nowhere at all (Blackhole).

**On this page:** [yaml](#yaml) · [json](#json) · [blackhole](#blackhole) · [Cassette file naming](#cassette-file-naming)

Select via [`setStorage()`](configuration.md#storage). All three implement `PurgeableStorage`, so all three
work with [`MODE_ALL`](../guides/record-modes.md#all).

## `yaml`

- **Default.**
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

- Discards everything. `storeRecording()` and `purge()` are no-ops, `isNew()` always returns `true`, and the
  iterator is always empty — nothing is ever replayed.
> **📌 Note:** unlike `yaml`/`json`, Blackhole never touches the filesystem at all — no cassette file is
> created, even after `insertCassette()`.
- Useful for smoke-testing library-hook behaviour without leaving cassette files behind.

## Cassette file naming

- The file is named **exactly** the cassette name passed to `insertCassette()` — no `.yml`/`.json` extension
  is appended automatically.
- A cassette name containing a path separator (e.g. `'api/users'`) auto-creates the subfolder under the
  configured [cassette path](configuration.md#cassette-path).
- Every recorded entry carries an `index` (see [Cassettes → identical requests](../guides/cassettes.md#identical-requests)).

---
← [Library Hooks](library-hooks.md) · Next: [Events](events.md) →
