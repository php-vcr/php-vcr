# Request Matching

> One-liner: to replay a recording, php-vcr has to decide an incoming request "is" a previously recorded one
> — that decision is what request matching configures.

**On this page:** [The default: everything must agree](#the-default-everything-must-agree) · [Narrowing what matters](#narrowing-what-matters) · [Custom matchers](#custom-matchers)

## The default: everything must agree

By default, all 9 built-in matchers are enabled — method, URL path, host, headers, body, JSON body, post
fields, query string, SOAP operation. An incoming request only matches a recording if **every enabled matcher** returns
`true` (logical AND, see [`Request::matches()`](../reference/request-response.md#request)). That's the
strictest possible interpretation of "same request", and it's the safest default: nothing replays unless it's
genuinely the same call.

## Narrowing what matters

Real requests often carry incidental variation — a changing timestamp header, a random idempotency key, query
parameters your test doesn't care about. Rather than fighting that noise, enable only the matchers that
identify the request for your use case:

```php
\VCR\VCR::configure()->enableRequestMatchers(['method', 'url', 'host']);
```

Now two requests that only differ in headers or body replay the same recording. See the full list and exact
semantics of each in the [Request Matchers reference](../reference/request-matchers.md).

Dropping `body` is a blunt instrument when the variation is only in how a JSON payload is serialised — a
reordered key, a reformatted document. Swap it for
[`body_json`](../reference/request-matchers.md#body_json) instead, which compares the body as a JSON
document rather than as a string, and keeps rejecting bodies that actually differ:

```php
\VCR\VCR::configure()
    ->enableRequestMatchers(['method', 'url', 'host', 'query_string', 'body_json']);
```

## Custom matchers

When none of the built-ins capture what "the same request" means for your API, write a matcher:

```php
\VCR\VCR::configure()
    ->addRequestMatcher('api_version', function (\VCR\Request $recorded, \VCR\Request $incoming) {
        return $recorded->getHeader('X-Api-Version') === $incoming->getHeader('X-Api-Version');
    })
    ->enableRequestMatchers(['method', 'url', 'api_version']);
```

Custom matchers combine with built-ins the same way — all enabled matchers still need to agree. See
[Custom request matcher](../howto/custom-request-matcher.md) for a complete recipe.

---
← [Record Modes](record-modes.md) · Next: [Use with PHPUnit](../howto/use-with-phpunit.md) →
