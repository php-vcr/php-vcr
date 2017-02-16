<?php
namespace VCR\Util;

use Exception;

/**
 * An exception from HttpClient::send
 */
class HttpClientException extends \Exception
{
    public function __construct(
        $message,
        $curlInfo,
        $curlError,
        $curlErroNo)
    {
        parent::__construct($message);
        $this->curlInfo = $curlInfo;
        $this->curlError = $curlError;
        $this->curlErrorNo = $curlErroNo;
    }
}
