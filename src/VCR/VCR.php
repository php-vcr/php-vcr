<?php

namespace VCR;

/**
 * Singleton interface to a Videorecorder.
 *
 * @method VCR\Configuration configure()
 * @method void insertCassette(string cassetteName)
 * @method void turnOn()
 * @method void turnOff()
 * @method void eject()
 */
class VCR
{
    public static function __callStatic($method, $parameters)
    {
        $instance = VCRFactory::get('VCR\Videorecorder');

        return call_user_func_array(array($instance, $method), $parameters);
    }
}
