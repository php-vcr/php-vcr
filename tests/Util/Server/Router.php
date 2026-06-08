<?php

declare(strict_types=1);

namespace VCR\Tests\Util\Server;

final class Router
{
    public static function handle(): void
    {
        self::incrementCounter();

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = strtok($uri, '?') ?: '/';
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ('GET' === $method && '/get' === $path) {
            self::sendGetResponse($uri);

            return;
        }

        if (\in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true) && '/'.strtolower($method) === $path) {
            self::sendBodyResponse($method, $uri);

            return;
        }

        if ('GET' === $method && 1 === preg_match('#^/status/(\d+)$#', $path, $m)) {
            http_response_code((int) $m[1]);
            header('Content-Type: application/json');
            echo json_encode(['status' => (int) $m[1]], \JSON_THROW_ON_ERROR);

            return;
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Found'], \JSON_THROW_ON_ERROR);
    }

    private static function incrementCounter(): void
    {
        $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
        if (0 === $port) {
            return;
        }

        $counterFile = sys_get_temp_dir()."/php-vcr-test-counter-{$port}";
        $fp = fopen($counterFile, 'c+');
        if (false === $fp) {
            return;
        }

        flock($fp, \LOCK_EX);
        $count = (int) stream_get_contents($fp);
        fseek($fp, 0);
        ftruncate($fp, 0);
        fwrite($fp, (string) ($count + 1));
        flock($fp, \LOCK_UN);
        fclose($fp);
    }

    private static function sendGetResponse(string $uri): void
    {
        $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $args = [];
        if ('' !== $queryString) {
            parse_str($queryString, $args);
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
        $url = 'http://'.$host.$uri;

        header('Content-Type: application/json');
        /** @var array<string, mixed> $args */
        echo json_encode(['url' => $url, 'args' => $args, 'headers' => self::getRequestHeaders()], \JSON_THROW_ON_ERROR);
    }

    private static function sendBodyResponse(string $method, string $uri): void
    {
        $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $args = [];
        if ('' !== $queryString) {
            parse_str($queryString, $args);
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
        $url = 'http://'.$host.$uri;
        $body = (string) file_get_contents('php://input');

        header('Content-Type: application/json');
        /** @var array<string, mixed> $args */
        echo json_encode([
            'url' => $url,
            'method' => $method,
            'args' => $args,
            'body' => $body,
            'headers' => self::getRequestHeaders(),
        ], \JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    private static function getRequestHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }
            $name = str_replace('_', '-', substr($key, 5));
            $headers[$name] = (string) $value;
        }

        return $headers;
    }
}
