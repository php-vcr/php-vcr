# Storage Backends Reference

> One-liner: cassettes are files on disk, serialized as YAML (default), JSON, or nowhere at all (Blackhole).

**On this page:** [yaml](#yaml) · [json](#json) · [blackhole](#blackhole) · [encrypted](#encrypted) ·
[redacting](#redacting) · [Custom storage backend](#custom-storage-backend) ·
[Cassette file naming](#cassette-file-naming)

Select via [`setStorageFactory()`](configuration.md#storage-factory). `yaml`, `json`, and `blackhole`
implement `PurgeableStorageInterface` directly, so all three work with [`MODE_ALL`](../guides/record-modes.md#all);
`encrypted` and `redacting` each wrap one of them and work with `MODE_ALL` whenever the wrapped backend does.

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

- **Fields:** `request.body` · `request.post_fields` · `request.post_files` · `response.body` ·
  `response.curl_info.request_header`
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
> - Other `curl_info` sub-fields besides `request_header` (e.g. timing data) are not covered by the default
>   policy — they generally don't carry secrets.

Works with both `yaml` and `json` as the wrapped backend.

## `redacting`

> **🆕 Since 1.13**

- **Factory:** `\VCR\Storage\RedactingStorageFactory` — wraps another storage factory so every cassette it
  writes has the fields matched by its rules redacted, and every cassette it reads has reversible rules'
  redaction restored before request matching runs.
- Rules are declared via `\VCR\Storage\Redaction\RedactionRules`, a fluent collection: `filterSensitiveData()`
  for a literal secret value anywhere in the recording, `header()`/`allHeaders()`, `queryParameter()`,
  `postField()`, `host()` for targeted fields, and `body()`/`postFields()` for an arbitrary callback. See
  [Filter sensitive data](../howto/filter-sensitive-data.md) for the day-to-day usage and
  [Custom redaction rule](../howto/custom-redaction-rule.md) for writing a new rule.
- `withRules()` takes a caller-built `RedactionRules`; `withDefaults()` needs no configuration and strips the
  standard sensitive response headers (`Set-Cookie`, `WWW-Authenticate`, `Proxy-Authenticate`).

```php
$rules = \VCR\Storage\Redaction\RedactionRules::create()
    ->filterSensitiveData('<<AUTH_TOKEN>>', getenv('API_TOKEN'));

\VCR\VCR::configure()->setStorageFactory(
    \VCR\Storage\RedactingStorageFactory::withRules(new \VCR\Storage\YamlStorageFactory(), $rules)
);
```

A recorded cassette looks like this — `filterSensitiveData()` walked the whole recording, so the secret sent as
an `Authorization` header is replaced by the placeholder everywhere it appears, including inside the response
body a test server echoed it back into:

```yaml
-
    request:
        method: POST
        url: 'http://127.0.0.1:8098/'
        headers:
            Authorization: 'Bearer <<AUTH_TOKEN>>'
            Content-Type: application/x-www-form-urlencoded
        body: ping=pong
    response:
        status: { code: 200, message: OK }
        headers:
            X-Echo-Authorization: 'Bearer <<AUTH_TOKEN>>'
            Content-Type: application/json
        body: '{"method":"POST","headers":{"AUTHORIZATION":"Bearer <<AUTH_TOKEN>>"},"body":"ping=pong"}'
    index: 0
```

### Reversibility

Every rule reports `isReversible(): bool`.

A **reversible** rule can put the original value back before the matchers run, so all 8 default request matchers
keep working unmodified. That is `filterSensitiveData()`, and `header()`/`queryParameter()`/`postField()`/
`host()` **given a source**. In that form the source is the *real* secret — read it from an environment variable
or a secrets manager, don't hard-code it — and the rule writes a placeholder to the cassette in its place,
resolving the source again on read to restore it:

| Rule | Placeholder written to the cassette |
| --- | --- |
| `header('Authorization', …)` | `<<REDACTED:HEADER:authorization>>` (the name is lowercased — headers are case-insensitive) |
| `postField('password', …)` | `<<REDACTED:POST_FIELD:password>>` |
| `queryParameter('token', …)` | `<<REDACTED:QUERY_PARAMETER:token>>` (written into the URL unencoded) |
| `host(…)` | `redacted-host.invalid` |

The placeholder is derived from the rule's own identity, never randomised, so re-recording a cassette produces
byte-identical output instead of a spurious diff. `host()` is the exception to the shape: its placeholder is
written into `request.url`, which `parse_url()` still has to accept, so it is a hostname in the `.invalid` TLD
[RFC 2606](https://www.rfc-editor.org/rfc/rfc2606) reserves for exactly this. `host()` also swaps the host out
of the `Host` header rather than overwriting it, so an appended `:8443` survives the round trip — the host is
located case-insensitively, so a header that spells it differently from the URL keeps its port too, though the
host itself comes back in the source's casing rather than the header's own.

> **⚠️ `queryParameter()` and `host()` write the source verbatim.** Neither percent-encodes it. There is no
> encoding convention every HTTP client follows identically — `urlencode()` escapes `@` and writes a space as
> `+`, `rawurlencode()` writes `%20`, and plenty of clients leave both alone — and the `query_string` matcher
> compares the raw query string byte-for-byte, so any fixed encoder would break replay for some client. Supply
> the source in the form your client actually puts on the wire: `'a%20b'` if it encodes the space, `'a b'` if it
> doesn't. `filterSensitiveData()` has always worked this way; these two now match it.

A source is either a literal string or a callable, optionally taking the recording being processed. `getenv()`
returning `false` for an unset variable — and `null` — are accepted rather than rejected: they surface as a
`MissingSecretException` naming the placeholder when the secret is actually needed, so a CI run without the
secret configured fails with a readable message rather than a type error. Anything else (an int, a non-callable
array) is a configuration mistake and is rejected with `InvalidRedactionRuleException` right where the rule is
built, as is a scope string that is not one of `Scope`'s three values.

An **irreversible** rule (`body()`, `postFields()`, or any of the field-targeted rules used *without* a source)
permanently discards the original value and reports which request matchers it invalidates via
`affectedMatchers()`.

`RedactionRules::add()` enforces this at composition time: registering an irreversible, request-scoped rule
throws `MissingReplacementException` unless `allowIrreversibleRequestRedaction()` was called first. Once a
caller has opted in, `safeRequestMatchers()` returns the default matcher keys that remain reliable given every
rule added so far — pass it straight to `enableRequestMatchers()`:

```php
$rules = \VCR\Storage\Redaction\RedactionRules::create()
    ->allowIrreversibleRequestRedaction()
    ->postField('card_number');

\VCR\VCR::configure()
    ->setStorageFactory(\VCR\Storage\RedactingStorageFactory::withRules(new \VCR\Storage\YamlStorageFactory(), $rules))
    ->enableRequestMatchers($rules->safeRequestMatchers());
```

### Composing with `encrypted`

Wrap `EncryptedStorageFactory` in `RedactingStorageFactory` to redact first, then encrypt the result — this
covers fields the encryption policy doesn't touch by default (a custom header, say) with a placeholder, while
still encrypting everything the policy does cover:

```php
$key = \VCR\Storage\Encryption\EncryptionKey::fromBase64($_SERVER['VCR_CASSETTE_KEY']);
$rules = \VCR\Storage\Redaction\RedactionRules::create()->filterSensitiveData('<<AUTH_TOKEN>>', getenv('API_TOKEN'));

\VCR\VCR::configure()->setStorageFactory(
    \VCR\Storage\RedactingStorageFactory::withRules(
        \VCR\Storage\EncryptedStorageFactory::withKey(new \VCR\Storage\YamlStorageFactory(), $key),
        $rules
    )
);
```

This was verified directly: the secret never reached the disk either way, `request.body`/`response.body`
(covered by the default encryption policy) came out as `vcr:enc:v1:...` ciphertext, and a custom response
header outside that policy still carried the placeholder rather than the real secret — the placeholder inside
policy-covered fields was itself encrypted as part of the surrounding field, since redaction runs before
encryption in this composition order.

> **⚠️ Limitations**
>
> - `body()`/`postFields()` callbacks and any field rule used without a replacement source are irreversible —
>   the original value cannot be recovered once redacted, only the matcher gap it creates can be worked around.
> - Every rule that writes a placeholder throws `PlaceholderCollisionException` if that placeholder already
>   occurs in the value about to be redacted, and `MissingSecretException` if the source resolves to an empty
>   value — both surface immediately rather than silently corrupting a cassette. The secret is resolved during
>   `redact()` even though only `restore()` needs it, so an unresolvable source fails at record time instead of
>   producing a cassette that can never be restored.
> - **Every read resolves every source again.** A cassette redacted against a source can only be replayed where
>   that source resolves — the same environment variable set, the same secrets manager reachable. Reading it
>   without the secret fails with `MissingSecretException` naming the placeholder, at the point `current()`
>   restores the recording, before matching even starts. There is no silent fallback to the placeholder: the
>   matchers would then compare it against the real value a live request carries and never find the recording.
> - `header()` owns the header's *whole* value, so its source has to resolve to all of it, prefix included
>   (`'Bearer '.getenv('API_TOKEN')`). Concatenating a prefix onto an unset `getenv()` yields `'Bearer '` — a
>   non-empty string — so the missing-secret guard cannot fire for it. When only part of a value is secret,
>   reach for `filterSensitiveData()` on the token itself instead.
> - Blanking a header, query parameter, or post field replaces its value with an empty string rather than
>   removing the key — `RecordingPath` has no key-deletion primitive.
> - `allHeaders()` can only blank. One source cannot stand for every header in scope — it would write the same
>   value into all of them and restore that one value into all of them on read — so handing a source to the
>   underlying wildcard rule is rejected with `InvalidRedactionRuleException` at construction.

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
