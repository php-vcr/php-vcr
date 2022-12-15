<?php

declare(strict_types=1);

namespace VCR\Util;

class CurlException extends \Exception
{
    /**
     * @var array<string,mixed>
     */
    private array $info;

    public static function create(\CurlHandle $ch): self
    {
        $e = new self(curl_error($ch), curl_errno($ch));
        $e->info = curl_getinfo($ch);

        return $e;
    }

    /**
     * @return array<string,mixed>
     */
    public function getInfo(): array
    {
        return $this->info;
    }
}
