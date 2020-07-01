<?php

namespace VCR\Example;

use function exec;
use function file_exists;
use function file_get_contents;
use function passthru;
use PHPUnit\Framework\TestCase;
use function strpos;

class OpcacheTest extends TestCase
{
    public function testOpcache()
    {
        // Step 0: cleanup
        if (file_exists(__DIR__.'/../www/vcr.yaml')) {
            unlink(__DIR__.'/../www/vcr.yaml');
        }

        // Step 1: start Docker.
        exec('docker run -d --rm --name apache-web-server -p 8080:80 -v "$PWD/../../../":/var/www/html thecodingmachine/php:7.2-v1-apache');

        // Step 2: wait for the Docker container to be up.
        $started = false;
        for ($i = 0; $i < 600; ++$i) {
            try {
                $content = file_get_contents('http://localhost:8080/tests/integration/apache/www/target.html');
                if (false !== strpos($content, 'foobar')) {
                    $started = true;
                    break;
                }
            } catch (\Throwable $t) {
                // Ignore warnings if the server is not up yet
            }
            sleep(1);
        }

        if (false === $started) {
            $this->fail('Failed to start Apache server');
        }

        // Step 3: Let's perform 2 requests: one that performs a direct cURL request and THEN, one that goes through PHP-VCR
        file_get_contents('http://localhost:8080/tests/integration/apache/www/performRequest.php');
        file_get_contents('http://localhost:8080/tests/integration/apache/www/performRequestWithPhpVcr.php');

        // Step 4: cleanup
        passthru('docker stop apache-web-server');

        // Step 5: verify that the cassette contains what we want.
        $cassette = file_get_contents('www/vcr.yaml');
        $this->assertContains('http://localhost/tests/integration/apache/www/target.html', $cassette);
    }
}
