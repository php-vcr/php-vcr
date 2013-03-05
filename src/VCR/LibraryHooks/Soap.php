<?php

namespace VCR\LibraryHooks;

use \VCR\Configuration;
use \VCR\Request;
use \VCR\Response;

/**
 * Library hook for curl functions.
 */
class Soap implements LibraryHookInterface
{
    private static $status = self::DISABLED;

    /**
     * @var Request
     */
    private static $request;

    /**
     * @var Response
     */
    private static $response;

    private static $handleRequestCallable;

    private static $overwriteMethods = array(
        'SoapClient::__doRequest' => array(
            '$request, $location, $action, $version, $one_way = 0',
            'doRequest($request, $location, $action, $version, $one_way)'),
    );

    public function __construct(\Closure $handleRequestCallable = null)
    {
        if (!function_exists('runkit_function_redefine')) {
            throw new \BadMethodCallException('For soap support you need to install runkit extension.');
        }

        if (!is_null($handleRequestCallable)) {
            if (!is_callable($handleRequestCallable)) {
                throw new \InvalidArgumentException('No valid callback for handling requests defined.');
            }
            self::$handleRequestCallable = $handleRequestCallable;
        }
    }

    public function enable()
    {
        if (self::$status == self::ENABLED) {
            return;
        }

        // foreach (self::$overwriteMethods as $identifier => $mapping) {
        //     list($className, $methodName) = explode('::', $identifier);
        //     runkit_method_rename($className, $methodName, $methodName . '_original');

        //     if (method_exists($className, $methodName . '_temp')) {
        //         runkit_method_rename($className, $methodName . '_temp', $methodName);
        //     } else {
        //         runkit_method_add($className, $methodName, $mapping[0], 'return ' . __CLASS__ . '::' . $mapping[1] . ';');
        //     }
        // }

        self::$status = self::ENABLED;
    }

    public function disable()
    {
        if (self::$status == self::DISABLED) {
            return;
        }

        // foreach (self::$overwriteMethods as $identifier => $mapping) {
        //     list($className, $methodName) = explode('::', $identifier);
        //     runkit_method_rename($className, $methodName, $methodName . '_temp');
        //     runkit_method_rename($className, $methodName . '_original', $methodName);
        // }

        self::$status = self::DISABLED;
    }

    public static function doRequest($request, $location, $action, $version , $one_way = 0)
    {
        var_dump($request, $location, $action, $version, $one_way);
        $handleRequestCallable = self::$handleRequestCallable;
        // self::$response = $handleRequestCallable(self::$request);

        // echo self::$response->getBody(true);
    }

}