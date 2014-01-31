<?php
namespace VCR\Util;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;
use VCR\Request;
use VCR\Response;

/**
 * Sends requests over the HTTP protocol.
 */
class HttpClient
{
    /**
     * @var \Guzzle\Http\Client
     */
    protected $client;

    /**
     * Creates a new HttpClient instance
     *
     * @param Client $client
     */
    public function __construct(Client $client = null)
    {
        $this->client = $client ?: new Client;
    }

    /**
     * Returns a response for specified HTTP request.
     *
     * @param  Request $request HTTP Request to send.
     *
     * @return Response Response for specified request.
     */
    public function send(Request $request)
    {
        try {
            $response = $this->client->send($request);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        }

        return new Response(
            $response->getStatusCode(),
            $response->getHeaders(),
            $response->getBody()
        );
    }
}
