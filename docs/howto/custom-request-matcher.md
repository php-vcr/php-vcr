# Custom Request Matcher

> One-liner: register a callback, then enable it alongside (or instead of) the built-ins.

**On this page:** [Register and enable](#register-and-enable) · [Combine with built-ins](#combine-with-built-ins)

Use this when none of the [8 built-in matchers](../reference/request-matchers.md) capture what "the same
request" means for your API — for example, matching on a custom header that identifies a logical operation
regardless of a varying query string.

## Register and enable

```php
\VCR\VCR::configure()
    ->addRequestMatcher('api_version', function (\VCR\Request $recorded, \VCR\Request $incoming) {
        return $recorded->getHeader('X-Api-Version') === $incoming->getHeader('X-Api-Version');
    })
    ->enableRequestMatchers(['api_version']);
```

The callback receives the **recorded** request first, the **incoming** request second, and must return
`bool`. Registering a matcher (`addRequestMatcher`) doesn't enable it by itself — it still has to be listed in
`enableRequestMatchers()`.

## Combine with built-ins

Matchers are ANDed together — list your custom matcher alongside whichever built-ins you still want enforced:

```php
\VCR\VCR::configure()
    ->addRequestMatcher('api_version', function (\VCR\Request $recorded, \VCR\Request $incoming) {
        return $recorded->getHeader('X-Api-Version') === $incoming->getHeader('X-Api-Version');
    })
    ->enableRequestMatchers(['method', 'url', 'api_version']);
```

Now a request only replays if the method, path, **and** the custom header all agree — everything else
(other headers, body, query string) is ignored for matching purposes.

---
← [Filter sensitive data](filter-sensitive-data.md) · Next: [Custom redaction rule](custom-redaction-rule.md) →
