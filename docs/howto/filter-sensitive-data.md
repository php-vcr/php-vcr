# Filter Sensitive Data

> One-liner: mutate the `Request` in a `VCR_BEFORE_RECORD` listener before it's written to disk — but redacting
> the body means you must also stop matching on it, or replay breaks.

**On this page:** [Redact a header](#redact-a-header) · [Redact the body](#redact-the-body) ·
[What you can't redact this way](#what-you-cant-redact-this-way) ·
[Encrypt instead of redacting](#encrypt-instead-of-redacting)

Cassettes are plain files that typically end up committed to your repo — don't let an auth token, API key, or
cookie leak into one. `VCR_BEFORE_RECORD` fires with the real `Request`/`Response` right before they're
serialized. Only the **request** side is mutable through this event (see below) — that already covers the
most common leak: an `Authorization` header, or a secret sent in the request body.

## Redact a header

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
real bearer token — while the real request that was actually sent (and its real response) are unaffected,
since recording happens *after* the real HTTP call. Headers aren't part of request matching by default unless
you rely on the `headers` matcher for this specific header, so redacting doesn't affect replay.

## Redact the body

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

## What you can't redact this way

`Response` (see [Request/Response reference](../reference/request-response.md)) exposes **only getters** —
`BeforeRecordEvent::getResponse()` gives you a read-only view, with no setter to replace it. If a secret comes
back *in the response body* (rather than being sent in the request), this event can't strip it before it's
written to the cassette. In that case, either have your test server/fixture avoid echoing the secret back, or
post-process the cassette file after recording.

## Encrypt instead of redacting

> **🆕 Since 1.13**

Redaction has a sharp edge, [documented above](#redact-the-body): once the body on disk no longer matches the
body of the real incoming request, the `body`/`post_fields` matchers must be narrowed too, or replay breaks.
The [`encrypted` storage backend](../reference/storage-backends.md#encrypted) sidesteps this entirely — it
decrypts a recording in `current()`, which runs before `Cassette::playback()` applies the matchers, so matching
always sees plaintext while only ciphertext ever reaches disk:

```php
$key = \VCR\Storage\Encryption\EncryptionKey::fromBase64($_SERVER['VCR_CASSETTE_KEY']);

\VCR\VCR::configure()->setStorageFactory(
    \VCR\Storage\EncryptedStorageFactory::withKey(new \VCR\Storage\YamlStorageFactory(), $key)
);
```

This also covers the response-body case above, which redaction through `VCR_BEFORE_RECORD` can't reach at
all — `response.body` is encrypted by default. See [Storage Backends → encrypted](../reference/storage-backends.md#encrypted)
for the full configuration, the fields covered by default, and its limitations (query-string secrets stay
readable, and a lost key makes a cassette unrecoverable).

---
← [Use with Codeception](use-with-codeception.md) · Next: [Custom request matcher](custom-request-matcher.md) →
