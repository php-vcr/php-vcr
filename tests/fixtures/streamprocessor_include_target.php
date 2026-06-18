<?php

declare(strict_types=1);

namespace VCR\Tests\Fixtures;

class StreamProcessorIncludeTarget
{
    public static function marker(): string
    {
        return 'included';
    }
}
