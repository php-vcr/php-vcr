<?php

namespace VCR\Example\Guzzle;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;

/**
* Gets project information from github.
*/
class GithubProject
{
    const GITHUB_API = 'https://api.github.com/';

    /**
     * @var string Project name on github, example 'user/project'.
     */
    private $projectName;

    /**
     * @var Guzzle\Http\Client
     */
    private $client;

    public function __construct($project)
    {
        $this->projectName = $project;
        $this->client = new Client(self::GITHUB_API);
    }

    public function getInfo()
    {
        try {
            $request = $this->client->get('/repos/'. $this->projectName);
            $response = $request->send();

            return json_decode($response->getBody(), true);
        } catch (ClientErrorResponseException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }

        return null;
    }
}

