# Cassettes

> One-liner: a cassette is a file of recorded request/response pairs, replayed in order when the same
> request comes in again.

**On this page:** [What gets recorded](#what-gets-recorded) · [File format](#file-format) · [Identical requests](#identical-requests) · [Where cassettes live](#where-cassettes-live)

## What gets recorded

`VCR::insertCassette('example')` attaches a [storage backend](../reference/storage-backends.md) to a
`Cassette`. Every request that misses playback gets a real HTTP round trip, then the request and response are
recorded as one entry. On the next run, an incoming request that [matches](request-matching.md) a recorded
one gets that recorded response back — no network call.

## File format

Here's an actual YAML cassette entry, recorded from `file_get_contents('http://example.com/hello')`:

```yaml
-
    request:
        method: GET
        url: 'http://example.com/hello'
        headers:
            Host: example.com
    response:
        status:
            code: 200
            message: OK
        headers:
            Content-type: text/plain
        body: 'Hello, php-vcr!'
        curl_info:
            url: 'http://example.com/hello'
            http_code: 200
            # ... (curl's full getinfo() array, useful for debugging but not required reading)
    index: 0
```

<details>
<summary>What every field means</summary>

- **`request`** — a serialized [`Request`](../reference/request-response.md#request): method, URL, headers,
  and (if present) body/post fields/post files.
- **`response`** — a serialized [`Response`](../reference/request-response.md#response): status, headers,
  body, plus curl's diagnostic `curl_info` array (recorded for reference, not used during replay matching).
- **`index`** — see [Identical requests](#identical-requests) below.

[JSON storage](../reference/storage-backends.md#json) records the exact same fields, just as JSON instead of
YAML.
</details>

## Identical requests

If a test makes the *same* request more than once, each occurrence gets recorded as its own cassette entry,
distinguished by an incrementing `index` — and replayed back in that same order:

```yaml
-
    request: { method: GET, url: '/counter', headers: { Host: example.com } }
    response: { status: { code: 200, message: OK }, body: 'call-1' }
    index: 0
-
    request: { method: GET, url: '/counter', headers: { Host: example.com } }
    response: { status: { code: 200, message: OK }, body: 'call-2' }
    index: 1
```

This is controlled by
[`setRecordIdenticalRequests()`](../reference/configuration.md#record-identical-requests) (default `true`).
Set it to `false` if your test issues the same request a variable number of times across runs — every
occurrence then replays the **first** recorded response instead of advancing through the sequence.

> **📌 Note:** cassettes recorded before php-vcr added the `index` field still work — a missing `index` falls
> back to match-anything behaviour.

## Where cassettes live

- Configured via [`setCassettePath()`](../reference/configuration.md#cassette-path) (default `tests/fixtures`).
- The file is named **exactly** the cassette name — no extension is appended.
- A cassette name with a path separator (`'api/users'`) auto-creates the subfolder.
- [Blackhole storage](../reference/storage-backends.md#blackhole) never writes a file at all.

---
← [How VCR works](how-vcr-works.md) · Next: [Record Modes](record-modes.md) →
