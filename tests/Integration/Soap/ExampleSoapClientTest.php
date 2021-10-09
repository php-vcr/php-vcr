<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Soap;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Converts temperature units from webservicex.
 *
 * @see http://www.webservicex.net/New/Home/ServiceDetail/31
 */
final class ExampleSoapClientTest extends TestCase
{
    protected function setUp(): void
    {
        // Configure virtual filesystem.
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));

        // Trigger autoload of assertions (broken after VCR is enabled...
        $this->assertNotNull(1);
        $this->assertTrue(true);
    }

    public function testCallDirectly(): void
    {
        $actual = $this->callSoap();
        $this->assertIsString($actual);
        $this->assertEquals('twelve', $actual);
    }

    public function testCallIntercepted(): void
    {
        $actual = $this->callSoapIntercepted();
        $this->assertIsString($actual);
        $this->assertEquals('twelve', $actual);
    }

    public function testCallDirectlyEqualsIntercepted(): void
    {
        $this->assertEquals($this->callSoap(), $this->callSoapIntercepted());
    }

    protected function callSoap(): mixed
    {
        $soapClient = new ExampleSoapClient();

        return $soapClient->call();
    }

    protected function callSoapIntercepted(): mixed
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        $result = $this->callSoap();
        \VCR\VCR::turnOff();

        return $result;
    }
}
