<?php

namespace VCR\Example;

/**
 * Converts temperature units from webservicex
 *
 * @link http://www.webservicex.net/New/Home/ServiceDetail/31
 */
class ExampleSoapClient
{
    const EXAMPLE_WSDL = 'http://www.dataaccess.com/webservicesserver/numberconversion.wso?WSDL';

    public function call($number = 12)
    {
        $client = new \SoapClient(self::EXAMPLE_WSDL, array('soap_version' => SOAP_1_2));
        $response = $client->NumberToWords(array('ubiNum' => $number));

        return trim((string) $response->NumberToWordsResult);
    }
}
