# Use with Codeception

> One-liner: there's no official php-vcr Codeception module — wire it up with a small
> [Extension](https://codeception.com/docs/08-Customization#Extension) that turns VCR on for the suite and inserts
> one cassette per test.

**On this page:** [The extension](#the-extension) · [Registering it](#registering-it) · [In a test](#in-a-test)

## The extension

```php
namespace App\Support\Extension;

use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;

class VcrExtension extends Extension
{
    public static array $events = [
        Events::SUITE_BEFORE => 'registerHooks',
        Events::TEST_BEFORE => 'insertCassette',
        Events::TEST_AFTER => 'eject',
    ];

    public function registerHooks(): void
    {
        \VCR\VCR::configure()->setCassettePath(codecept_data_dir('cassettes'));
        // Registers the curl/soap source rewriting this early, without leaving hooks live for the whole suite.
        \VCR\VCR::turnOn();
        \VCR\VCR::turnOff();
    }

    public function insertCassette(TestEvent $event): void
    {
        $name = preg_replace('/[^A-Za-z0-9_]+/', '_', $event->getTest()->getMetadata()->getName());
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette($name);
    }

    public function eject(): void
    {
        \VCR\VCR::eject();
        \VCR\VCR::turnOff();
    }
}
```

`SUITE_BEFORE` fires while Codeception is still bootstrapping the run — before your test classes are loaded —
so that's early enough to register the `curl`/`soap` hooks (see [How VCR works](../guides/how-vcr-works.md)).
The immediate `turnOff()` in `registerHooks()` doesn't undo that registration, it just leaves hooks in
passthrough mode until a test actually wants one — each test then turns VCR on for itself and off again once
it's done, instead of leaving hooks live for the whole suite.

## Registering it

```yaml
# codeception.yml
extensions:
    enabled:
        - App\Support\Extension\VcrExtension
```

## In a test

```php
namespace App\Tests\Unit;

use Codeception\Test\Unit;

class GreetingTest extends Unit
{
    public function testFetchesGreeting(): void
    {
        $result = file_get_contents('http://example.com/hello');
        $this->assertSame('Hello, php-vcr!', $result);
    }
}
```

Nothing VCR-specific in the test itself — the extension already inserted a cassette named after the test
method before it ran, and ejects it after.

---
← [Use with PHPUnit](use-with-phpunit.md) · Next: [Filter sensitive data](filter-sensitive-data.md) →
