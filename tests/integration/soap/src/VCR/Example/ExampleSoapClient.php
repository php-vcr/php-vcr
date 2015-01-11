<?php
namespace VCR\Example;

/**
 * Checks cdyne.com for local weather information.
 *
 * @link http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL
 */
class ExampleSoapClient
{
    const EXAMPLE_WSDL = 'http://www.w3schools.com/webservices/tempconvert.asmx?WSDL';

    public function call($zip = '10')
    {
        $client = new \SoapClient(self::EXAMPLE_WSDL, array('soap_version' => SOAP_1_2));
        $response = $client->CelsiusToFahrenheit(array('Celsius' => $zip));

        return (int) $response->CelsiusToFahrenheitResult;
    }
}
