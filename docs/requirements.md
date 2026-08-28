# Requirements

> One-liner: PHP 8.0–8.5 (minus the broken 8.2.0–8.2.8 range), `ext-curl` always, `ext-soap`/`ext-xml` only if
> you use the SOAP hook.

**On this page:** [PHP](#php) · [Extensions](#extensions) · [Dependencies](#dependencies) · [Tested HTTP libraries](#tested-http-libraries)

## PHP

```text
^8.0,<8.2 | >=8.2.9,<8.6
```

That is: PHP 8.0, 8.1, 8.3, 8.4, 8.5 — and 8.2 **only from patch 8.2.9 onward** (earlier 8.2.x patches have a
known incompatibility). CI runs the full matrix across every supported version, on both lowest and highest
allowed dependency versions.

## Extensions

| Extension | Required | Why |
| --- | --- | --- |
| `curl` | Always | Used both by the `curl` hook and internally to perform real HTTP requests when recording. |
| `soap` | Only for the `soap` hook | `SoapHook` throws `BadMethodCallException` at construction if `SoapClient` doesn't exist. |
| `xml` (`ext-dom`) | Only for the `soap` hook | Same constructor check, via `DOMDocument`. |

If you never use `SoapClient` in your codebase, you can skip `ext-soap`/`ext-xml` entirely — the `soap` hook
is simply never constructed unless something asks for it.

## Dependencies

Composer installs all of these for you:

- [`symfony/event-dispatcher`](https://github.com/symfony/event-dispatcher) — powers [Events](reference/events.md).
- [`symfony/yaml`](https://github.com/symfony/yaml) — the default [storage backend](reference/storage-backends.md).
- [`beberlei/assert`](https://github.com/beberlei/assert) — internal input validation.

## Tested HTTP libraries

php-vcr intercepts at the level of PHP's stream wrapper and `curl_*`/`SoapClient` functions, so it works with
any library built on top of them — these are the ones actually exercised by the test suite:

| Library | Hook | Notes |
| --- | --- | --- |
| `file_get_contents()`, `fopen()`, `fread()`, … | `stream_wrapper` | Any function that goes through PHP's `http`/`https` stream wrapper. |
| `Symfony\Component\HttpClient\NativeHttpClient` | `stream_wrapper` | Uses stream wrappers under the hood. |
| `ext-curl` (`curl_init`/`curl_exec`/`curl_multi_*`/…) | `curl` | Direct curl function calls. |
| `Symfony\Component\HttpClient\CurlHttpClient` | `curl` | Symfony's curl-backed client. |
| Guzzle (curl handler) | `curl` | Guzzle's default transport on most installs. |
| `SoapClient` (built-in or a subclass) | `soap` | Both direct instantiation and custom `extends SoapClient` classes. |

See [Library Hooks](reference/library-hooks.md) for how each hook actually intercepts these, and
[How VCR works](guides/how-vcr-works.md) for the underlying mechanism.

---
← [Documentation home](index.md) · Next: [Getting Started](getting-started.md) →
