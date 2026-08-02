# Request/Response Objects Reference

> One-liner: what you get in event listeners and custom matchers — `VCR\Request` and `VCR\Response`.

**On this page:** [Request](#request) · [Response](#response)

## Request

```php
new Request(string $method, ?string $url, array $headers = [])
```

| Getters | Setters |
|---|---|
| `getMethod()` (honours `CURLOPT_CUSTOMREQUEST`) | `setMethod(string)` (uppercases) |
| `getUrl()` | `setUrl(?string)` (also sets the `Host` header) |
| `getHost()` (host:port; throws `InvalidHostException` if unparsable) | — |
| `getPath()`, `getQuery()` | — |
| `getHeaders()`, `getHeader(string $key)`, `hasHeader(string $key)` | `setHeader(string, string)`, `removeHeader(string)` |
| `getBody()` | `setBody(?string)` |
| `getPostFields()` | `setPostField(string, mixed)`, `setPostFields(array)` |
| `getPostFiles()` | `setPostFiles(array)`, `addPostFile(array $file)` — keys `fieldName`, `contentType`, `filename`, `postname` |
| `getCurlOptions()`, `getCurlOption(int)` | `setCurlOption(int, mixed)`, `setCurlOptions(array)` |
| — | `setAuthorization(string $username, string $password)` — sets HTTP Basic auth |
| `getHash()` | — |
| `matches(Request $other, callable[] $matchers): bool` | — |
| `toArray()` / `fromArray()` | keys: `method`, `url`, `headers`, `body`, `post_files`, `post_fields` (empty values filtered out) |

```php
// Typical use in a BEFORE_RECORD listener: redact a header before it's written
$request->setHeader('Authorization', 'REDACTED');
```

## Response

```php
new Response($status, array $headers = [], ?string $body = null, array $curlInfo = [])
// $status: int|string, or ['code' => ..., 'message' => ..., 'http_version' => ...]
```

| Getters |
|---|
| `getStatusCode(): int`, `getStatusMessage(): string`, `getHttpVersion(): mixed` |
| `getHeaders(): array`, `getHeader(string $key): ?string` |
| `getContentType(): ?string` |
| `getBody(): string` |
| `getCurlInfo(?string $option = null): mixed` |
| `toArray()` / `fromArray()` |

> **📌 Note:** `toArray()`/`fromArray()` automatically base64-encode/decode the body when the response's
> `Content-Type` is `application/x-gzip` or its `Content-Transfer-Encoding` header is `binary` — this is how
> binary response bodies survive a round trip through YAML/JSON cassette files.

---
← [Events](events.md) · Next: [How VCR works](../guides/how-vcr-works.md) →
