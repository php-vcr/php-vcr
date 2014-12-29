<?php

namespace VCR\Example\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
* Gets project information from github.
*/
class GithubProject
{
    const GITHUB_API = 'https://api.github.com';

    /**
     * @var string Project name on github, example 'user/project'.
     */
    private $projectName;

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    public function __construct($project)
    {
        $this->projectName = $project;
        $this->client = new Client();
    }

    public function getInfo()
    {
        try {
            $response = $this->client->get(self::GITHUB_API . '/repos/'. $this->projectName);

            return $response->json();
        } catch (ClientException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        return null;
    }
}

