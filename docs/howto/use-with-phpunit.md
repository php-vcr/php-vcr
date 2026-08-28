# Use with PHPUnit

> One-liner: call VCR manually per test, or use the [`angelov/phpunit-php-vcr`](https://github.com/angelov/phpunit-php-vcr) PHPUnit extension to manage PHP-VCR and cassettes automatically.

**On this page:** [Manual lifecycle](#manual-lifecycle) · [With the PHPUnit extension](#with-the-phpunit-extension)

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
> support for PHPUnit 7/8. It doesn't work on the PHPUnit 9.5.10+/10.5+/11.0+ versions php-vcr itself targets.

## With the PHPUnit extension

> **Requirements:** PHP 8.2+ and PHPUnit 10+.

The [`angelov/phpunit-php-vcr`](https://github.com/angelov/phpunit-php-vcr) PHPUnit extension automatically
manages PHP-VCR cassettes for tests using the `#[UseCassette]` attribute.

Install the PHPUnit integration:

```bash
composer require --dev angelov/phpunit-php-vcr
```

Then register the extension in your `phpunit.xml`. All parameters are optional:

```xml
<extensions>
  <bootstrap class="\Angelov\PHPUnitPHPVcr\Extension">
    <parameter name="cassettesPath" value="tests/fixtures" />
    <parameter name="storage" value="yaml" />
    <parameter name="libraryHooks" value="stream_wrapper, curl, soap" />
    <parameter name="requestMatchers" value="method, url, query_string, ..." />
    <parameter name="whitelistedPaths" value="" />
    <parameter name="blacklistedPaths" value="" />
    <parameter name="mode" value="new_episodes" />
  </bootstrap>
</extensions>
```

### Recording requests

Declare the `#[UseCassette]` attribute on either a test class or an individual test method.

When declared on a class, all test methods in that class use the same cassette:

```php
use Angelov\PHPUnitPHPVcr\UseCassette;
use PHPUnit\Framework\TestCase;

#[UseCassette('example.yml')]
class ExampleTest extends TestCase
{
    public function testShouldInterceptStreamWrapper(): void
    {
        $result = file_get_contents('http://example.com');

        $this->assertNotEmpty($result);
    }
}
```

When declared on a method, only that test uses the cassette:

```php
use Angelov\PHPUnitPHPVcr\UseCassette;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    #[UseCassette('example.yml')]
    public function testShouldInterceptStreamWrapper(): void
    {
        $result = file_get_contents('http://example.com');

        $this->assertNotEmpty($result);
    }

    public function testWithoutVcr(): void
    {
        $this->assertTrue(true);
    }
}
```

Method-level attributes override class-level ones.

When a cassette is declared, the extension automatically:

* turns PHP-VCR on before the test,
* inserts the requested cassette,
* ejects the cassette after the test,
* turns PHP-VCR off again.

> **📌 Note:** The integration also supports PHPUnit data providers. By default, all data sets share a cassette;
> it can also create separate cassettes for each data set, optionally grouped in a directory. See the
> [project README](https://github.com/angelov/phpunit-php-vcr) for the full documentation.

---

← [Request Matching](../guides/request-matching.md) · Next: [Use with Codeception](use-with-codeception.md) →
