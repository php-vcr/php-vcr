<?php
namespace VCR;

use lapistano\ProxyObject\ProxyBuilder;


class VCR_TestCase extends \PHPUnit_Framework_TestCase
{

    protected function getProxyBuilder($className)
    {
        return new ProxyBuilder($className);
    }
}
