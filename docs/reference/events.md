# Events Reference

> One-liner: five Symfony events fire around the record/playback lifecycle — hook in via
> `VCR::getEventDispatcher()`.

**On this page:** [VCR_BEFORE_PLAYBACK](#vcr_before_playback) · [VCR_AFTER_PLAYBACK](#vcr_after_playback) · [VCR_BEFORE_HTTP_REQUEST](#vcr_before_http_request) · [VCR_AFTER_HTTP_REQUEST](#vcr_after_http_request) · [VCR_BEFORE_RECORD](#vcr_before_record)

```php
\VCR\VCR::getEventDispatcher()->addListener(
    \VCR\VCREvents::VCR_BEFORE_RECORD,
    function (\VCR\Event\BeforeRecordEvent $event) {
        // ...
    }
);
```

All event classes live in `VCR\Event\` and extend Symfony's base `Event`. Constants live on `VCR\VCREvents`.

## `VCR_BEFORE_PLAYBACK`
- **Constant value:** `vcr.before_playback`
- **Dispatched:** before php-vcr attempts to find a matching recording.
- **Event class:** `BeforePlaybackEvent` — `getRequest(): Request`, `getCassette(): Cassette`

## `VCR_AFTER_PLAYBACK`
- **Constant value:** `vcr.after_playback`
- **Dispatched:** after a recording was successfully found and returned.
- **Event class:** `AfterPlaybackEvent` — `getRequest(): Request`, `getResponse(): Response`, `getCassette(): Cassette`

## `VCR_BEFORE_HTTP_REQUEST`
- **Constant value:** `vcr.before_http_request`
- **Dispatched:** right before a real HTTP request is sent (no match was found, or mode is `all`).
- **Event class:** `BeforeHttpRequestEvent` — `getRequest(): Request`

## `VCR_AFTER_HTTP_REQUEST`
- **Constant value:** `vcr.after_http_request`
- **Dispatched:** right after the real HTTP response comes back, before it's recorded.
- **Event class:** `AfterHttpRequestEvent` — `getRequest(): Request`, `getResponse(): Response`

## `VCR_BEFORE_RECORD`
- **Constant value:** `vcr.before_record`
- **Dispatched:** right before the request/response pair is written to the cassette.
- **Event class:** `BeforeRecordEvent` — `getRequest(): Request`, `getResponse(): Response`, `getCassette(): Cassette`
- **Common use:** redact secrets before they hit disk — mutate the `Request`/`Response` object in your
  listener (they're passed by reference). See [Filter sensitive data](../howto/filter-sensitive-data.md).

---
← [Storage Backends](storage-backends.md) · Next: [Request/Response](request-response.md) →
