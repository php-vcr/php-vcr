<?php

namespace VCR;

/**
 * Singleton interface to a VCR
 */
class VCR
{
    public static function __callStatic($method, $parameters)
    {
        $instance = VCRFactory::get('Videorecorder');
        return call_user_func_array(array($instance, $method), $parameters);
    }
}

