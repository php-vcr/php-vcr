<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Soap;

/**
 * Converts temperature units from webservicex.
 *
 * @see http://www.webservicex.net/New/Home/ServiceDetail/31
 */
class ExampleSoapClient
{
    public const EXAMPLE_WSDL = 'http://www.dataaccess.com/webservicesserver/numberconversion.wso?WSDL';

    public function call(int $number = 12): string
    {
        $client = new \SoapClient(self::EXAMPLE_WSDL, ['soap_version' => \SOAP_1_2]);
        $response = $client->NumberToWords(['ubiNum' => $number]);

        return trim((string) $response->NumberToWordsResult);
    }

    public function callBadUrl(): void
    {
        // The port is not open. This leads to an error
        $client = new \SoapClient('http://localhost:9945', ['soap_version' => \SOAP_1_2]);
    }
}
