# Request Matchers Reference

> One-liner: matching decides whether an incoming request "is" a recorded one. All enabled matchers must
> agree (AND) — see [Request Matching](../guides/request-matching.md) for the concept.

**On this page:** [method](#method) · [url](#url) · [host](#host) · [headers](#headers) · [body](#body) · [body_json](#body_json) · [post_fields](#post_fields) · [query_string](#query_string) · [soap_operation](#soap_operation) · [Custom matchers](#custom-matchers)

All 9 are enabled by default. Configure a subset via
[`enableRequestMatchers()`](configuration.md#request-matchers).

## `method`

- **Compares:** `Request::getMethod()` (HTTP method, case-insensitive `==`)

```php
// GET http://example.com  vs.  POST http://example.com -> no match
```

## `url`

- **Compares:** `Request::getPath()` — the path only, **not** the query string

```php
// /users/1?debug=1  vs.  /users/1  -> match on `url` (query string is a separate matcher)
```

## `host`

- **Compares:** `Request::getHost()` — hostname including port

## `headers`

- **Compares:** all headers, with `null` headers filtered out of both sides first

```php
// Authorization: null on both sides is ignored, doesn't force a mismatch
```

## `body`

- **Compares:** the raw request body string

## `body_json`

> **🆕 Since 1.13**

- **Compares:** the request body decoded as JSON, structurally — object key order is ignored, array
  element order is significant, and scalars are compared strictly.
- Falls back to the same raw-string comparison as [`body`](#body) when either body is empty, invalid
  JSON, or a bare scalar.

```php
// {"model":"a","stream":false}  vs.  {"stream":false,"model":"a"}  -> match
// ["a","b"]                     vs.  ["b","a"]                     -> no match
// {"n":1}                       vs.  {"n":"1"}                     -> no match
// {}                            vs.  []                            -> no match
```

Replace `body` with `body_json` to get order-insensitive JSON matching:

```php
\VCR\VCR::configure()
    ->enableRequestMatchers(['method', 'url', 'host', 'query_string', 'body_json']);
```

> **📌 Note**
> Like every built-in, `body_json` is part of the default set — where it changes nothing, because
> matchers are ANDed and `body` already rejects any body it would reject. Leaving `body` enabled
> alongside it keeps the strict raw-string comparison in force.

> **⚠️ Warning**
> Decoding is only reached when two bodies differ as strings *and* both start with `{` or `[`.
> Identical bodies and non-JSON bodies (XML, binary uploads, form-encoded payloads) are settled by the
> same string comparison `body` uses, so the default matcher set costs nothing extra. When decoding
> does happen it is paid once per recording examined, against both bodies — on a 200 KiB payload that
> is milliseconds per recording, so a cassette holding many large JSON recordings will notice.
> Narrowing the enabled matchers so cheaper ones (`method`, `url`, `host`) reject non-candidates first
> keeps that cost off the hot path.

## `post_fields`

- **Compares:** `Request::getPostFields()` — parsed POST field array

## `query_string`

- **Compares:** `Request::getQuery()` — the query string only

## `soap_operation`

- **Compares:** the SOAP operation name extracted from `<SOAP-ENV:Body><operation>` in the body. If either
  side isn't a SOAP body (no match on the regex), this matcher returns `true` — it only discriminates between
  *different* SOAP operations, it doesn't require SOAP.

## Custom matchers

Register any callback of the shape `function (Request $recorded, Request $incoming): bool` via
`addRequestMatcher()`, then enable it like a built-in one:

```php
\VCR\VCR::configure()
    ->addRequestMatcher('custom_matcher', function (\VCR\Request $recorded, \VCR\Request $incoming) {
        return $recorded->getHeader('X-Api-Version') === $incoming->getHeader('X-Api-Version');
    })
    ->enableRequestMatchers(['method', 'url', 'custom_matcher']);
```

See [Custom request matcher](../howto/custom-request-matcher.md) for a complete recipe.

---
← [Configuration](configuration.md) · Next: [Library Hooks](library-hooks.md) →
