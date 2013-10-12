<?php
namespace VCR;

use lapistano\ProxyObject\ProxyBuilder;
use VCR\LibraryHooks\CurlRunkit;


class VCR_TestCase extends \PHPUnit_Framework_TestCase
{

    protected function getProxyBuilder($className)
    {
        return new ProxyBuilder($className);
    }

    protected function skipTestIfRunkitUnavailable()
    {
        try{
            $curlHook = new CurlRunkit();
        } catch (\BadMethodCallException $e){
            $this->markTestSkipped($e->getMessage());
        }
    }
}
