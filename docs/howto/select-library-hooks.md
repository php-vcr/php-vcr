# Select Library Hooks

> One-liner: if you know which HTTP mechanism your code uses, enable only that hook — faster test runs, less
> to reason about.

**On this page:** [Enable a subset](#enable-a-subset) · [Why this matters](#why-this-matters)

## Enable a subset

```php
\VCR\VCR::configure()->enableLibraryHooks(['curl']);
\VCR\VCR::turnOn();
```

Available names: `stream_wrapper`, `curl`, `soap` (see [Library Hooks reference](../reference/library-hooks.md)
for what each one intercepts). All three are enabled by default — this call restricts interception to just
the ones listed.

## Why this matters

- **Speed:** the `curl`/`soap` hooks scan every file loaded via `include`/`require` for rewritable calls.
  Disabling a hook you don't need (or narrowing the [whitelist/blacklist](../reference/configuration.md#white---blacklist))
  cuts down what gets scanned.
- **Scoping:** if your codebase only ever talks HTTP through `ext-curl`, there's no reason to also intercept
  `SoapClient` — one fewer mechanism to think about when debugging why a request wasn't recorded.

> **⚠️ Warning:** whichever hooks you enable, remember the
> [require/include constraint](../guides/how-vcr-works.md#the-requireinclude-constraint) — `curl` and `soap`
> only rewrite code loaded via `include`/`require` after `turnOn()`, never the top-level entry script. This
> was verified directly: a `curl_exec()` call written straight into the invoked script silently bypasses
> interception (real request goes through, nothing is recorded) — moving the same call into a `require`d file
> fixes it. `stream_wrapper` has no such restriction.

---
← [Custom storage](custom-storage.md) · Next: [Record SOAP requests](record-soap.md) →
