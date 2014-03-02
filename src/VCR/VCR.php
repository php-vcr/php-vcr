<?php

namespace VCR;

/**
 * Singleton interface to a Videorecorder.
 *
 * @method static Configuration configure()
 * @method static void insertCassette(string $cassetteName)
 * @method static void turnOn()
 * @method static void turnOff()
 * @method static void eject()
 */
class VCR
{
    public static function __callStatic($method, $parameters)
    {
        $instance = VCRFactory::get('VCR\Videorecorder');

        return call_user_func_array(array($instance, $method), $parameters);
    }
}
