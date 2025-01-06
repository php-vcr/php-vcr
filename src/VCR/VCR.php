<?php

declare(strict_types=1);

namespace VCR;

use Assert\Assertion;

/**
 * Singleton interface to a Videorecorder.
 *
 * @method static Configuration configure()
 * @method static void          insertCassette(string $cassetteName)
 * @method static void          turnOn()
 * @method static void          turnOff()
 * @method static void          eject()
 *
 * @mixin Videorecorder
 */
class VCR
{
    /**
     * Always allow to do HTTP requests and add to the cassette. Default mode.
     */
    public const MODE_NEW_EPISODES = 'new_episodes';

    /**
     * Only allow new HTTP requests when the cassette is newly created.
     */
    public const MODE_ONCE = 'once';

    /**
     * Treat the fixtures as read only and never allow new HTTP requests.
     */
    public const MODE_NONE = 'none';

    /**
     * @param mixed[] $parameters
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        $callable = [VCRFactory::get(Videorecorder::class), $method];

        Assertion::isCallable($callable);

        return \call_user_func_array($callable, $parameters);
    }
}
