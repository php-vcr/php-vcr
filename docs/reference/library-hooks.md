# Library Hooks Reference

> One-liner: three hooks, three interception mechanisms — all enabled by default. See
> [How VCR works](../guides/how-vcr-works.md) for the underlying mechanics.

**On this page:** [stream_wrapper](#stream_wrapper) · [curl](#curl) · [soap](#soap)

Restrict which hooks are active via
[`enableLibraryHooks()`](configuration.md#library-hooks) — see
[Select library hooks](../howto/select-library-hooks.md).

## `stream_wrapper`

- **Intercepts:** any PHP function that goes through the `http`/`https` stream wrapper — `fopen()`,
  `file_get_contents()`, `fread()`, `Symfony\Component\HttpClient\NativeHttpClient`.
- **Mechanism:** replaces PHP's built-in `http`/`https` stream wrapper globally
  (`stream_wrapper_unregister` + `stream_wrapper_register`). No source rewriting involved.
- **Works anywhere:** since the interception is a global protocol substitution, it doesn't matter where in
  your code the call is written — including directly in a script's top level.
```php
\VCR\VCR::configure()->enableLibraryHooks(['stream_wrapper']);
\VCR\VCR::turnOn();
file_get_contents('http://example.com'); // intercepted, no matter where this line lives
```

## `curl`

- **Intercepts:** `curl_init`, `curl_exec`, `curl_getinfo`, `curl_setopt`, `curl_setopt_array`,
  `curl_multi_add_handle`, `curl_multi_remove_handle`, `curl_multi_exec`, `curl_multi_info_read` — covers
  direct `ext-curl` usage, `Symfony\Component\HttpClient\CurlHttpClient`, and Guzzle's curl backend.
- **Mechanism:** rewrites `curl_*` calls in PHP source **as it's loaded via `include`/`require`**, using a
  stream filter on the `file://` wrapper.
> **⚠️ Warning:** this only rewrites code loaded through `include`/`require` — **not** the top-level script
> PHP was invoked with. In a real test suite (PHPUnit, Codeception, …) this is never an issue, since test
> code is always loaded via the autoloader. See [How VCR works](../guides/how-vcr-works.md) for why.

## `soap`

- **Intercepts:** `SoapClient` — either `new SoapClient(...)` directly, or your own `extends SoapClient`
  subclass. Interception happens at `__doRequest()`, right before the request is sent, so custom logic in
  your subclass runs unaffected.
- **Mechanism:** same source-rewriting approach as `curl` — same `include`/`require` requirement applies.
- **Requires:** `ext-soap` and `ext-xml` (see [Requirements](../requirements.md#extensions)) — the hook's
  constructor throws `\BadMethodCallException` if either is missing.

See [Record SOAP requests](../howto/record-soap.md) for a full recipe.

---
← [Request Matchers](request-matchers.md) · Next: [Storage Backends](storage-backends.md) →
