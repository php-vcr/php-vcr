<?php

namespace VCR\Util;

class CurlException extends \Exception
{
    /**
     * @var array<string,mixed>
     */
    private $info;

    /**
     * @param resource $ch The cURL handler
     *
     * @return CurlException
     */
    public static function create($ch): self
    {
        $e = new self(curl_error($ch), curl_errno($ch));
        $e->info = curl_getinfo($ch);

        return $e;
    }

    /**
     * Returns the curl_info array.
     *
     * @return array<string,mixed>
     */
    public function getInfo(): array
    {
        return $this->info;
    }
}
