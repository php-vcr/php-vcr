<?php

namespace VCR;

use VCR\Storage\Storage;

/**
 * A Cassette records and plays back pairs of Requests and Responses in a Storage.
 */
class Cassette
{
    /**
     * Casette name
     *
     * @var string
     */
    protected $name;

    /**
     * VCR configuration.
     *
     * @var Configuration
     */
    protected $config;

    /**
     * Storage used to store records and request pairs.
     *
     * @var Storage<array>
     */
    protected $storage;

    /**
     * Indicates whether the cassette has started playback.
     *
     * @var boolean
     */
    protected $startedPlayback = false;
    
    /**
     * Creates a new cassette.
     *
     * @param  string           $name    Name of the cassette.
     * @param  Configuration    $config  Configuration to use for this cassette.
     * @param  Storage<array>   $storage Storage to use for requests and responses.
     * @throws \VCR\VCRException If cassette name is in an invalid format.
     */
    public function __construct(string $name, Configuration $config, Storage $storage)
    {
        $this->name = $name;
        $this->config = $config;
        $this->storage = $storage;
    }

    /**
     * Returns true if a response was recorded for specified request.
     *
     * @return boolean True if a response was recorded for specified request.
     */
    public function hasResponse(): bool
    {
        return $this->playback() !== null;
    }

    /**
     * Returns a response for given request or null if not found.
     *
     * @return Response|null Response for specified request.
     */
    public function playback(): ?Response
    {
        if ( ! $this->startedPlayback) {
            $this->storage->rewind();
            $this->startedPlayback = true;
        }
        
        $response = null;
        
        if ($this->storage->valid()) {
            $recording = $this->storage->current();
            $response  = Response::fromArray($recording);
        }
        
        $this->storage->next();
        
        return $response;
    }

    /**
     * Records a request and response pair.
     *
     * @param Response $response Response to record.
     *
     * @return void
     */
    public function record(Response $response): void
    {
        $this->storage->storeRecording($response->toArray());
    }

    /**
     * Returns the name of the current cassette.
     *
     * @return string Current cassette name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns true if the cassette was created recently.
     *
     * @return boolean
     */
    public function isNew(): bool
    {
        return $this->storage->isNew();
    }
}
