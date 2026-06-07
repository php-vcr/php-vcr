<?php

declare(strict_types=1);

namespace VCR\Tests\Util\Server;

final class Router
{
    public static function handle(): void
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = strtok($uri, '?') ?: '/';
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ('GET' === $method && '/get' === $path) {
            self::sendGetResponse($uri);

            return;
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Found'], \JSON_THROW_ON_ERROR);
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
        echo json_encode(['url' => $url, 'args' => $args], \JSON_THROW_ON_ERROR);
    }
}
