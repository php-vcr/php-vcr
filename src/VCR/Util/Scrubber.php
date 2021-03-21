<?php

namespace VCR\Util;

use VCR\Configuration;
use VCR\Request;
use VCR\Response;

class Scrubber
{
    /**
     * VCR configuration.
     *
     * @var Configuration
     */
    protected $config;

    /**
     * Create a new Scrubber.
     *
     * @param Configuration $config configuration to use for this scrubber
     */
    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    /**
     * Scrub the given request/response of all secrets, using the configured redactions.
     *
     * @param Request  $request  request to scrub
     * @param Response $response response to scrub
     *
     * @return array<string,mixed> The scrubbed recording
     */
    public function scrub(Request $request, Response $response)
    {
        $redactions = $this->evaluateRedactions($request, $response);
        $recording = $this->buildRecording($request, $response);

        return $this->scrubArray($recording, $redactions);
    }

    /**
     * Unmask the redacted parts of a recording, using the configured redactions.
     *
     * @param array<string,mixed> $recording The recording, ie. an array with 'request' and 'response' keys
     *
     * @return array<string,mixed> The unscrubbed recording
     */
    public function unscrub($recording)
    {
        $request = Request::fromArray($recording['request']);
        $response = Response::fromArray($recording['response']);

        $unmaskings = array_flip($this->evaluateRedactions($request, $response));

        return $this->scrubArray($recording, $unmaskings);
    }

    /**
     * Evaluate the configured redactions in the context of the request/response pair.
     *
     * @param Request  $request  The request
     * @param Response $response The response
     *
     * @return array<string, string> An array of token => replacement pairs
     */
    private function evaluateRedactions($request, $response)
    {
        $replacements = [];

        foreach ($this->config->getRedactions() as $replacement => $callback) {
            $privateData = $callback($request, $response);
            if ($privateData) {
                if (!\is_string($privateData)) {
                    throw new \InvalidArgumentException("Redaction callback for $replacement did not return a string");
                }
                $replacements[$replacement] = $privateData;
            }
        }

        return $replacements;
    }

    /**
     * Builds a recording in standard VCR format.
     *
     * @param Request  $request  The request
     * @param Response $response The response
     *
     * @return array<string,mixed> The recording, ie. an array with request and response keys
     */
    private function buildRecording(Request $request, Response $response)
    {
        return [
            'request' => $request->toArray(),
            'response' => $response->toArray(),
        ];
    }

    /**
     * Walk an array recursively, replacing substrings on each key.
     *
     * @param array<string,mixed>  $arr          The array to traverse
     * @param array<string,string> $replacements Replacements in search=>replacement pairs
     *
     * @return array<string,mixed> The resulting array with all replacements performed
     */
    private function scrubArray(array &$arr, $replacements)
    {
        $search = array_values($replacements);
        $replace = array_keys($replacements);

        foreach ($arr as $key => $value) {
            if (\is_string($value)) {
                $arr[$key] = str_replace($search, $replace, $value);
            } elseif (\is_array($value)) {
                $arr[$key] = $this->scrubArray($value, $replacements);
            }
        }

        return $arr;
    }
}
