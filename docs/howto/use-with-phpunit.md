# Use with PHPUnit

> **One-liner:** use the [`angelov/phpunit-php-vcr`](https://github.com/angelov/phpunit-php-vcr) PHPUnit extension to automatically manage PHP-VCR and cassettes with the `#[UseCassette]` attribute.

**On this page:** [Installation](#installation) · [Recording requests](#recording-requests)

## Installation

Install the PHPUnit integration:

```bash
composer require --dev angelov/phpunit-php-vcr
```

Then register the extension in your `phpunit.xml`:

```xml
<extensions>
    <bootstrap class="\Angelov\PHPUnitPHPVcr\Extension">
        <parameter name="cassettesPath" value="tests/fixtures" />
    </bootstrap>
</extensions>
```

The extension automatically configures and manages PHP-VCR for your tests. Additional configuration options (record modes, storage, request matchers, library hooks, etc.) are available in the project's documentation.

## Recording requests

Declare the `#[UseCassette]` attribute on either a test class or an individual test method.

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

The extension automatically:

- turns PHP-VCR on before each test,
- inserts the requested cassette,
- ejects the cassette after the test,
- turns PHP-VCR off again.

If the attribute is declared on a method, only that test uses the cassette. Method-level attributes override class-level ones.

> **📌 Note:** The integration also supports PHPUnit data providers, separate cassettes per data set, and additional configuration options. See the project README for the full documentation:
>
> https://github.com/angelov/phpunit-php-vcr

---
← [Request Matching](../guides/request-matching.md) · Next: [Use with Codeception](use-with-codeception.md) →
