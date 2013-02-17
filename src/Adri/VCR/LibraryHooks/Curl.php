<?php

namespace Adri\PHPVCR\LibraryHooks;

use Adri\PHPVCR\Configuration;
use Adri\PHPVCR\Request;
use Adri\PHPVCR\Response;

/**
 */
class Curl
{
    /**
     * @var Response
     */
    private $response;

    const ENABLED = 'ENABLED';
    const DISABLED = 'DISABLED';

    private static $status = self::DISABLED;

    private static $returnTransfer = false;

    private static $request;

    private static $handleRequestCallable;

    private static $overwriteFunctions = array(
        'curl_init'       => array('$url=null', 'init($url)'),
        'curl_exec'       => array('$resource', 'exec($resource)'),
        'curl_multi_exec' => array('$resource', 'exec($resource)'),
        'curl_setopt'     => array('$ch, $option, $value', 'setOpt($ch, $option, $value)'),
    );

    public function __construct(\Closure $handleRequestCallable = null)
    {
        if (!function_exists('runkit_function_redefine')) {
            throw new \BadMethodCallException('For curl support you need to install runkit extension.');
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

        foreach (self::$overwriteFunctions as $functionName => $mapping) {
            runkit_function_rename($functionName, $functionName . '_original');
            runkit_function_add($functionName, $mapping[0], 'return ' . __CLASS__ . '::' . $mapping[1] . ';');
        }

        self::$status = self::ENABLED;
    }

    public function disable()
    {
        if (self::$status == self::DISABLED) {
            return;
        }

        foreach (self::$overwriteFunctions as $functionName => $mapping) {
            runkit_function_remove($functionName);
            runkit_function_rename($functionName . '_original', $functionName);
        }

        self::$status = self::DISABLED;
    }

    public static function init($url = null)
    {
        self::$request = new Request(null, $url);
        return \curl_init_original($url);
    }

    public static function exec($ch)
    {
        $info = curl_getinfo($ch);
        $handleRequestCallable = self::$handleRequestCallable;

        $response = $handleRequestCallable(self::$request);

        if (self::$returnTransfer === true) {
            return $response->getBody(true);
        } else {
            echo $response->getBody(true);
        }
    }

    public static function setOpt($ch, $option, $value)
    {
        // echo "{$option} = {$value}\n";

        if ($option === CURLOPT_URL) {
           self::$request->setUrl($value);
        }

        if ($option === CURLOPT_RETURNTRANSFER && $value == true) {
           self::$returnTransfer = true;
        }

        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // self::$request
        \curl_setopt_original($ch, $option, $value);
    }

}