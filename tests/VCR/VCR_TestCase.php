<?php
namespace VCR;

use VCR\LibraryHooks\CurlRunkit;


class VCR_TestCase extends \PHPUnit_Framework_TestCase
{
    protected function skipTestIfRunkitUnavailable()
    {
        try{
            $curlHook = new CurlRunkit();
        } catch (\BadMethodCallException $e){
            $this->markTestSkipped($e->getMessage());
        }
    }
}
