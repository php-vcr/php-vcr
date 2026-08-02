# Use with PHPUnit

> One-liner: call VCR manually per test — turn it on, insert a cassette, turn it off.

**On this page:** [Manual lifecycle](#manual-lifecycle)

## Manual lifecycle

```php
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testShouldInterceptStreamWrapper(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('example');

        $result = file_get_contents('http://example.com');
        $this->assertNotEmpty($result);

        \VCR\VCR::eject();
        \VCR\VCR::turnOff();
    }

    public function testThrowsWithoutACassette(): void
    {
        \VCR\VCR::turnOn();

        $this->expectException(\BadMethodCallException::class);
        file_get_contents('http://example.com'); // no cassette inserted -> throws
    }
}
```

`turnOn()`/`turnOff()` per test keeps hooks scoped to the tests that need them — cheaper than leaving VCR on
for the whole suite, and it means a test forgetting to insert a cassette fails loudly instead of silently
reusing whatever the previous test left behind.

> **📌 Note:** [`php-vcr/phpunit-testlistener-vcr`](https://github.com/php-vcr/phpunit-testlistener-vcr) used
> to offer a `@vcr` annotation as a shortcut for this. It implements PHPUnit's `TestListener` interface and
> the `<listeners>` XML config element — both were removed in PHPUnit 10, and the package only ever declared
> support for PHPUnit 7/8. It doesn't work on the PHPUnit 9.5.10+/10.5+/11.0+ versions php-vcr itself targets,
> so it isn't documented here until it's updated to the newer Extension/Event API.

---
← [Request Matching](../guides/request-matching.md) · Next: [Use with Codeception](use-with-codeception.md) →
