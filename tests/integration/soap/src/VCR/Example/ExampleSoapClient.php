<?php
namespace VCR\Example;

/**
 * Converts temperature units from webservicex
 *
 * @link http://www.webservicex.net/New/Home/ServiceDetail/31
 */
class ExampleSoapClient
{
    const EXAMPLE_WSDL = 'http://www.webservicex.net/ConvertTemperature.asmx?WSDL';

    public function call($zip = '10')
    {
        $client = new \SoapClient(self::EXAMPLE_WSDL, array('soap_version' => SOAP_1_2));
        $response = $client->ConvertTemp(array('Temperature' => $zip, 'FromUnit' => 'degreeCelsius', 'ToUnit' => 'degreeFahrenheit'));

        return (int) $response->ConvertTempResult;
    }
}
