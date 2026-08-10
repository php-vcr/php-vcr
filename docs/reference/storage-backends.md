# Storage Backends Reference

> One-liner: cassettes are files on disk, serialized as YAML (default), JSON, or nowhere at all (Blackhole).

**On this page:** [yaml](#yaml) · [json](#json) · [blackhole](#blackhole) · [encrypted](#encrypted) ·
[Custom storage backend](#custom-storage-backend) · [Cassette file naming](#cassette-file-naming)

Select via [`setStorageFactory()`](configuration.md#storage-factory). `yaml`, `json`, and `blackhole`
implement `PurgeableStorageInterface` directly, so all three work with [`MODE_ALL`](../guides/record-modes.md#all);
`encrypted` wraps one of them and works with `MODE_ALL` whenever the wrapped backend does.

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

## `encrypted`

> **🆕 Since 1.13**

- **Factory:** `\VCR\Storage\EncryptedStorageFactory` — wraps another storage factory so every cassette it
  writes has its sensitive fields encrypted, and every cassette it reads is decrypted before request matching
  runs.
- Requires `ext-sodium`; encrypts with XChaCha20-Poly1305, keyed by a 32-byte `EncryptionKey`.
- `method`, `url`, and status stay in plaintext on purpose — the cassette is still reviewable in a diff.

```php
$key = \VCR\Storage\Encryption\EncryptionKey::fromBase64($_SERVER['VCR_CASSETTE_KEY']);

\VCR\VCR::configure()->setStorageFactory(
    \VCR\Storage\EncryptedStorageFactory::withKey(new \VCR\Storage\YamlStorageFactory(), $key)
);
```

Generate a key once and keep it outside the repository — losing it makes every cassette encrypted with it
unrecoverable:

```php
$key = \VCR\Storage\Encryption\EncryptionKey::generate();
echo $key->toBase64(); // store this, e.g. as the VCR_CASSETTE_KEY environment variable
```

A recorded cassette looks like this — the request body and the `Authorization` header are replaced by an
opaque `vcr:enc:v1:...` value, while `method` and `url` stay readable:

```yaml
-
    request:
        method: POST
        url: 'http://example.com/post'
        headers:
            Authorization: 'vcr:enc:v1:lQDi6mIvZalIRRBCbPFAC5kslFL6PZh3YQfc+ojigif6k/rdDP+zcqsB10UMQEXM0sZS...'
        body: 'vcr:enc:v1:FGnejCxN3ST4WPgtgd5ZY+QnqmY2zVx63Kf7QJ/9OLGUFX+7yVeifQr3gfLztL8tPD6MXsll1RFsig=='
    response:
        status: { code: 200, message: OK }
        body: 'vcr:enc:v1:lQVZ9S4OFm/oOyNLaSjv2vDKl0ifq8ZbBxl9nvXLQHAwyYSN/jvZjF5qvhiOTm0agNjjz6pH5BoFegy...'
    index: 0
```

### Policy defaults

`\VCR\Storage\Encryption\EncryptionPolicy` decides which fields are encrypted. Unless a custom policy is
passed as the third argument to `EncryptedStorageFactory::withKey()`, it encrypts:

- **Fields:** `request.body` · `request.post_fields` · `request.post_files` · `response.body`
- **Headers** (matched case-insensitively, in both the request and the response):
  `Authorization` · `Proxy-Authorization` · `Cookie` · `Set-Cookie` · `X-Api-Key`

```php
\VCR\Storage\EncryptedStorageFactory::withKey(
    new \VCR\Storage\YamlStorageFactory(),
    $key,
    new \VCR\Storage\Encryption\EncryptionPolicy(['response.body'], [])
);
```

> **⚠️ Limitations**
>
> - Secrets in the query string stay in plaintext — `url` is intentionally left readable.
> - Identical values in the same field produce identical ciphertext, the cost of a deterministic nonce (it
>   keeps a re-recorded cassette byte-identical instead of producing a spurious diff).
> - A cassette written with a since-lost key cannot be decrypted again — keep the key itself out of the
>   repository, and keep a backup of it.

Works with both `yaml` and `json` as the wrapped backend.

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
