# Filter Sensitive Data

> One-liner: prefer declarative rules via `RedactingStorageFactory` — they redact on write and restore on read
> before matchers run, so replay isn't broken. Reach for a `VCR_BEFORE_RECORD` listener only when a rule can't
> express what you need.

**On this page:** [Declarative redaction (recommended)](#declarative-redaction-recommended) ·
[Redact via a listener (fallback)](#redact-via-a-listener-fallback) ·
[Encrypt instead of redacting](#encrypt-instead-of-redacting)

Cassettes are plain files that typically end up committed to your repo — don't let an auth token, API key, or
cookie leak into one.

## Declarative redaction (recommended)

> **🆕 Since 1.13**

`RedactionRules` collects one or more rules and hands them to `RedactingStorageFactory`, which wraps another
storage factory so every cassette it writes has sensitive fields redacted, and every cassette it reads has them
restored *before* `Cassette::playback()` applies the request matchers:

```php
$rules = \VCR\Storage\Redaction\RedactionRules::create()
    ->filterSensitiveData('<<AUTH_TOKEN>>', getenv('API_TOKEN'));

\VCR\VCR::configure()->setStorageFactory(
    \VCR\Storage\RedactingStorageFactory::withRules(new \VCR\Storage\YamlStorageFactory(), $rules)
);
```

`filterSensitiveData()` walks the *entire* recording — headers, body, `post_fields`, even
`response.curl_info.request_header` (the raw outgoing headers curl captures) — and replaces every occurrence of
the secret with the placeholder, on both the request and response side. Because the swap is a literal,
reversible string substitution, the cassette above still replays with all 8 default matchers enabled — no
matcher narrowing required, unlike the listener route below.

This was verified directly: a request carrying a real `Authorization: Bearer <secret>` header was recorded
against a live dummy server with the rule above configured. The resulting cassette contained `<<AUTH_TOKEN>>`
and never the secret, in both the request header and the response body (a test server that echoes the request
back). With the dummy server then killed, replaying the same cassette reproduced the exact original response —
proof that redaction and restoration round-trip correctly.

If an unset `API_TOKEN` makes `getenv()` return `false`, the rule above fails with a `MissingSecretException`
naming `<<AUTH_TOKEN>>` rather than a type error — a CI run without the secret configured tells you which one is
missing.

> **⚠️ The secret is needed to *replay*, not only to record.** Restoration resolves every configured source
> again on every cassette read, so the environment replaying a cassette needs the same `API_TOKEN` (or whatever
> the source reads) as the one that recorded it. Without it, playback fails with a `MissingSecretException`
> naming the placeholder — it can't silently fall back to the placeholder, because the matchers would then
> compare the placeholder against the real value a live request carries and never find the recording. This
> applies to `filterSensitiveData()` and to `header()`/`postField()`/`queryParameter()`/`host()` used with a
> source. Verified directly: a cassette recorded with the token set, then read back with it unset, fails with
> `The replacement source for placeholder "<<REDACTED:HEADER:authorization>>" resolved to an empty value.` If a
> CI job may not have the secret, don't redact against a source there — encrypt the cassette instead, or use
> the irreversible form and narrow the matchers.

`RedactionRules` has purpose-built methods beyond `filterSensitiveData()` for header-, query-parameter-,
post-field-, and host-shaped secrets:

```php
$rules = \VCR\Storage\Redaction\RedactionRules::create()
    ->header('X-Api-Key', getenv('API_KEY'), \VCR\Storage\Redaction\Scope::REQUEST)
    ->queryParameter('signature', getenv('API_SIGNATURE'));
```

Given a source, these behave exactly like `filterSensitiveData()`: the source is the *real* value, the cassette
stores a derived placeholder in its place, and the real value is restored on read — so all 8 matchers stay
enabled. This was verified directly: the cassette came out carrying
`X-Api-Key: '<<REDACTED:HEADER:x-api-key>>'` and `signature=<<REDACTED:QUERY_PARAMETER:signature>>`, with
neither real value anywhere on disk, and the restored recording matched a live request carrying both real
values against all 8 default matchers.

`queryParameter()` and `host()` write their source into the URL verbatim — no percent-encoding is applied, the
same as `filterSensitiveData()` — so give them the value in the form your HTTP client actually sends
(`'a%20b'` if it encodes the space, `'a b'` if it doesn't). Encoding it for you would mean picking one client's
convention, and the `query_string` matcher compares the raw query string byte-for-byte.

Each also has an *irreversible* form (omit the source to blank the value with no way to restore it),
gated behind `allowIrreversibleRequestRedaction()` plus narrowing matchers via `safeRequestMatchers()`. See
[Storage Backends → redacting](../reference/storage-backends.md#redacting) for the full reference, and
[Custom redaction rule](custom-redaction-rule.md) for writing your own rule when none of the built-ins fit.

## Redact via a listener (fallback)

Before 1.13, mutating the `Request` in a `VCR_BEFORE_RECORD` listener was the only way to redact. It still
works and remains useful for a quick, one-off tweak that doesn't warrant wiring up `RedactionRules`, but it has
a sharp edge declarative redaction doesn't: whatever a listener overwrites on the request is gone for good, so
any request matcher that still compares against the original value breaks on replay — narrowing the matchers
by hand is the caller's responsibility, not something the library enforces. `VCR_BEFORE_RECORD` fires with the
real `Request`/`Response` right before they're serialized; only the **request** side is mutable through this
event — see [What you can't redact this way](#what-you-cant-redact-this-way) below.

### Redact a header

```php
\VCR\VCR::getEventDispatcher()->addListener(
    \VCR\VCREvents::VCR_BEFORE_RECORD,
    function (\VCR\Event\BeforeRecordEvent $event) {
        $request = $event->getRequest();
        if ($request->hasHeader('Authorization')) {
            $request->setHeader('Authorization', 'REDACTED');
        }
    }
);
```

Register this before `turnOn()`. The recorded cassette then contains `Authorization: REDACTED` instead of the
real bearer token — while the real request that was actually sent (and its real response) are unaffected, since
recording happens *after* the real HTTP call. **`headers` is one of the 8 default request matchers**
(`RequestMatcher::matchHeaders()` does a strict whole-array comparison), so this breaks replay by default: the
live request made during replay still carries the real header, which no longer equals the redacted value stored
on the cassette. Drop `headers` from the enabled matchers whenever you redact a header this way:

```php
\VCR\VCR::configure()->enableRequestMatchers(['method', 'url', 'host', 'body', 'post_fields', 'query_string', 'soap_operation']);
```

> This was verified directly: with the `headers` matcher enabled (the default), replaying a cassette recorded
> with the listener above fails because the live request's `Authorization` header no longer matches the
> redacted one on disk; disabling the `headers` matcher fixes replay while the redacted value still never
> touches the cassette.

### Redact the body

A secret sent as a POST field (`api_key=super-secret&name=test`) lives in `Request::getBody()` — the raw wire
body — not necessarily in `getPostFields()` (that array is only populated when the request came in through a
hook that parses it as such; a raw form-urlencoded body sent via `file_get_contents()`/stream context leaves
`getPostFields()` empty). Redact the raw body directly:

```php
\VCR\VCR::getEventDispatcher()->addListener(
    \VCR\VCREvents::VCR_BEFORE_RECORD,
    function (\VCR\Event\BeforeRecordEvent $event) {
        $request = $event->getRequest();
        $request->setBody(preg_replace('/api_key=[^&]+/', 'api_key=REDACTED', (string) $request->getBody()));
    }
);
```

> **⚠️ Warning:** if the `body` (or `post_fields`) [matcher](../reference/request-matchers.md) is enabled —
> it is, by default — this breaks replay. The cassette now stores the *redacted* body, but on replay the real
> incoming request still carries the *original* secret, so `body` no longer matches and playback fails. Drop
> `body`/`post_fields` from the enabled matchers whenever you redact body content:

```php
\VCR\VCR::configure()->enableRequestMatchers(['method', 'url', 'host']);
```

> This was verified directly — redacting the body without narrowing the matchers reproducibly breaks replay
> with a stream-wrapper error; narrowing the matchers first fixes it.

### What you can't redact this way

`Response` (see [Request/Response reference](../reference/request-response.md)) exposes **only getters** —
`BeforeRecordEvent::getResponse()` gives you a read-only view, with no setter to replace it. If a secret comes
back *in the response body* (rather than being sent in the request), this event can't strip it before it's
written to the cassette. In that case, use [declarative redaction](#declarative-redaction-recommended) instead
— `body()`/`filterSensitiveData()` cover the response side too — or, failing that, have your test server/fixture
avoid echoing the secret back, or post-process the cassette file after recording.

## Encrypt instead of redacting

> **🆕 Since 1.13**

Redaction, declarative or via a listener, leaves a recognizable placeholder in the cassette — useful for keeping
a diff reviewable, but the placeholder itself confirms *that* a secret was there. The
[`encrypted` storage backend](../reference/storage-backends.md#encrypted) goes further and encrypts whole fields
so nothing about their content is readable at all — it decrypts a recording in `current()`, which, like
redaction's restoration step, runs before `Cassette::playback()` applies the matchers, so matching always sees
plaintext while only ciphertext ever reaches disk:

```php
$key = \VCR\Storage\Encryption\EncryptionKey::fromBase64($_SERVER['VCR_CASSETTE_KEY']);

\VCR\VCR::configure()->setStorageFactory(
    \VCR\Storage\EncryptedStorageFactory::withKey(new \VCR\Storage\YamlStorageFactory(), $key)
);
```

The two compose: wrap `EncryptedStorageFactory` in `RedactingStorageFactory` to redact first and encrypt the
result, which covers fields the encryption policy doesn't touch by default (a custom header, say) with a
placeholder while still encrypting everything the policy does cover — see
[Storage Backends → redacting](../reference/storage-backends.md#redacting) for a runnable example of that
stack. See [Storage Backends → encrypted](../reference/storage-backends.md#encrypted) for the full
configuration, the fields covered by default, and its limitations (query-string secrets stay readable, and a
lost key makes a cassette unrecoverable).

---
← [Use with Codeception](use-with-codeception.md) · Next: [Custom request matcher](custom-request-matcher.md) →
