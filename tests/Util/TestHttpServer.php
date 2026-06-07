<?php

declare(strict_types=1);

namespace VCR\Tests\Util;

final class TestHttpServer
{
    private const STARTUP_TIMEOUT_SECONDS = 5.0;
    private const POLL_INTERVAL_MICROSECONDS = 50000;

    /** @var resource */
    private $process;

    /** @var resource */
    private $stderrPipe;

    private int $port;

    private function __construct(int $port)
    {
        $this->port = $port;
    }

    public static function start(): self
    {
        $port = self::findFreePort();

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        /** @var array{0: resource, 1: resource, 2: resource} $pipes */
        $pipes = [];
        $process = proc_open(
            ['php', '-S', "127.0.0.1:{$port}", __DIR__.'/Server/Entrypoint.php'],
            $descriptorSpec,
            $pipes
        );

        if (false === $process) {
            throw new \RuntimeException('Failed to start test HTTP server.');
        }

        $instance = new self($port);
        $instance->process = $process;
        $instance->stderrPipe = $pipes[2];

        $instance->waitForServerReady();

        return $instance;
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
    }

    public function getBaseUrl(): string
    {
        return "http://127.0.0.1:{$this->port}";
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if (false === $socket) {
            throw new \RuntimeException("Could not find a free port: [{$errorCode}] {$errorMessage}");
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        if (false === $address) {
            throw new \RuntimeException('Could not determine port from socket address.');
        }

        $colonPos = strrpos($address, ':');

        if (false === $colonPos) {
            throw new \RuntimeException("Unexpected socket address format: {$address}");
        }

        return (int) substr($address, $colonPos + 1);
    }

    private function waitForServerReady(): void
    {
        $start = microtime(true);

        while (true) {
            $socket = @stream_socket_client(
                "tcp://127.0.0.1:{$this->port}",
                $errorCode,
                $errorMessage,
                0.1
            );

            if (false !== $socket) {
                fclose($socket);

                return;
            }

            if (microtime(true) - $start > self::STARTUP_TIMEOUT_SECONDS) {
                $stderr = stream_get_contents($this->stderrPipe);
                throw new \RuntimeException("Test HTTP server failed to start on port {$this->port}.".(false !== $stderr && '' !== $stderr ? " STDERR: {$stderr}" : ''));
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }
    }
}
