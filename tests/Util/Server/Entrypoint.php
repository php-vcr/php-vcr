<?php

declare(strict_types=1);

// Suppress PHP notices/warnings from leaking into HTTP response bodies.
// The built-in server has no separate stderr for PHP output; deprecation
// notices from lowest-deps packages (e.g. guzzlehttp/promises on PHP 8.4)
// would otherwise corrupt JSON responses and break test assertions.
ini_set('display_errors', '0');
error_reporting(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);

require __DIR__.'/../../../vendor/autoload.php';

VCR\Tests\Util\Server\Router::handle();
