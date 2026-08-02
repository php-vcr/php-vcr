# Getting Started

> One-liner: install php-vcr, turn it on as early as possible, insert a cassette, make requests — first run
> records, every run after that replays.

**On this page:** [Requirements](#requirements) · [Install](#install) · [Turn it on — early](#turn-it-on-early) · [Record, then replay](#record-then-replay) · [Next steps](#next-steps)

## Requirements

The short version: PHP 8, `ext-curl`. Full compatibility matrix (including which HTTP libraries are covered
and when `ext-soap`/`ext-xml` matter) lives in [Requirements](requirements.md).

## Install

```bash
composer require --dev php-vcr/php-vcr
```

## Turn it on — early

> **⚠️ Warning — this is the part everyone gets wrong once.** `VCR::turnOn()` must run **before** any file that
> calls `curl_*` or instantiates `SoapClient` is loaded — ideally right after Composer's autoloader, in your
> test bootstrap. The `curl` and `soap` hooks work by rewriting source code as PHP `include`s/`require`s it;
> code that's already loaded when `turnOn()` runs cannot be rewritten anymore. See
> [How VCR works](guides/how-vcr-works.md) for the full mechanism — it also explains a sharp edge: those two
> hooks only ever rewrite code loaded via `include`/`require`, **not** the top-level script PHP was invoked
> with. In a real test suite this is a non-issue (PHPUnit loads your test classes via the autoloader), but a
> raw script with `curl_exec()` written directly at the top level will silently bypass interception.

```php
// tests/bootstrap.php
require __DIR__ . '/../vendor/autoload.php';

\VCR\VCR::turnOn();
\VCR\VCR::turnOff();
```

That `turnOn()`/`turnOff()` pair looks pointless but isn't: `turnOn()` is what registers the `curl`/`soap`
source rewriting — a one-time, permanent registration for the rest of the process. `turnOff()` right after
just flips the hooks back to passthrough; it doesn't undo the registration. So this pattern gets the
registration done as early as possible **without** leaving hooks live for your whole test run. Each individual
test then calls `turnOn()` again — cheaply, since the registration already happened — only when it actually
wants a cassette, and `turnOff()` when it's done (see the example below).

The `stream_wrapper` hook (used by `fopen()`, `file_get_contents()`, …) doesn't have this restriction — it
replaces the `http`/`https` stream wrapper globally, so it works no matter where the call is written.

## Record, then replay

```php
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testFetchesExampleDotCom(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('example');

        // First test run: no recording exists yet -> a real HTTP request is made and recorded.
        // Every run after that: the cassette has a match -> the real request is never sent.
        $result = file_get_contents('http://example.com');

        $this->assertNotEmpty($result);

        \VCR\VCR::eject();
        \VCR\VCR::turnOff();
    }
}
```

> **💡 Tip:** if your request contains something that changes on every call — a timestamp, a nonce, a
> generated idempotency key — the default configuration (all matchers enabled) will never replay, since the
> exact body/query string never matches again. Narrow the enabled matchers to ignore that part, e.g.
> `VCR::configure()->enableRequestMatchers(['method', 'url', 'host']);`. See
> [Request Matching](guides/request-matching.md).

Cassettes land in the configured cassette path (default `tests/fixtures`, see
[Configuration](reference/configuration.md#cassette-path)) as a file named exactly `example` — php-vcr does
**not** append `.yml`/`.json` automatically. If the cassette name contains a path separator
(`'api/example'`), the subfolder is created for you.

Delete the cassette file and re-run the test to force a fresh recording — that's the entire "re-record"
workflow for `new_episodes` (the default mode). For other strategies, see
[Record Modes](guides/record-modes.md).

## Next steps

- [How VCR works](guides/how-vcr-works.md) — the two interception mechanisms, and why bootstrap order matters.
- [Record Modes](guides/record-modes.md) — `new_episodes` / `once` / `none` / `all`.
- [Request Matching](guides/request-matching.md) — how php-vcr decides a request "matches" a recording.
- [Use with PHPUnit](howto/use-with-phpunit.md) — the manual lifecycle, wired into a real test class.

---
[Documentation home](index.md) · Next: [How VCR works](guides/how-vcr-works.md) →
