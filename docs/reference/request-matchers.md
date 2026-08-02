# Request Matchers Reference

> One-liner: matching decides whether an incoming request "is" a recorded one. All enabled matchers must
> agree (AND) — see [Request Matching](../guides/request-matching.md) for the concept.

**On this page:** [method](#method) · [url](#url) · [host](#host) · [headers](#headers) · [body](#body) · [post_fields](#post_fields) · [query_string](#query_string) · [soap_operation](#soap_operation) · [Custom matchers](#custom-matchers)

All 8 are enabled by default. Configure a subset via
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
