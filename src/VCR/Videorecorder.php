<?php

namespace VCR;

use VCR\Util\HttpClient;

class Videorecorder
{
    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @var HttpClient
     */
    protected $client;

    /**
     * @var Cassette
     */
    protected $cassette;

    /**
     * @var boolean
     */
    public $isOn = false;

    public function __construct(Configuration $config, HttpClient $client, VCRFactory $factory)
    {
        $this->config = $config;
        $this->client = $client;
        $this->factory = $factory;
    }

    /**
     * Initializes VCR and all it's dependencies.
     * @return void
     */
    public function turnOn()
    {
        if ($this->isOn) {
            $this->turnOff();
        }

        $this->enableLibraryHooks();
        $this->isOn = true;
    }

    /**
     * Shuts down VCR and all it's dependencies.
     * @return void
     */
    public function turnOff()
    {
        if ($this->isOn) {
            $this->disableLibraryHooks();
            $this->eject();
            $this->isOn = false;
        }
    }

    public function eject()
    {
        Assertion::true($this->isOn, 'Please turn on VCR before ejecting a cassette, use: VCR::turnOn().');
        $this->cassette = null;
    }

    public function insertCassette($cassetteName)
    {
        Assertion::true($this->isOn, 'Please turn on VCR before inserting a cassette, use: VCR::turnOn().');

        if (!is_null($this->cassette)) {
            $this->eject();
        }

        $filePath = $this->config->getCassettePath() . DIRECTORY_SEPARATOR . $cassetteName;
        $storage = $this->factory->get('Storage', array($filePath));

        $this->cassette = new Cassette($cassetteName, $this->config, $storage);
        $this->enableLibraryHooks();
    }

    public function configure()
    {
        return $this->config;
    }

    public function handleRequest($request)
    {
        if ($this->cassette === null) {
            throw new \BadMethodCallException(
                "Invalid http request. No cassette inserted. "
                . "Please make sure to insert a cassette in your unit test using "
                . "VCR::insertCassette('name');"
            );
        }

        if (!$this->cassette->hasResponse($request)) {
            $this->disableLibraryHooks();
            $response = $this->client->send($request);
            $this->cassette->record($request, $response);
            $this->enableLibraryHooks();
        }

        return $this->cassette->playback($request);
    }

    protected function disableLibraryHooks()
    {
        foreach ($this->config->getLibraryHooks() as $hookClass) {
            $hook = $this->factory->get($hookClass);
            $hook->disable();
        }
    }

    protected function enableLibraryHooks()
    {
        $self = $this;
        foreach ($this->config->getLibraryHooks() as $hookClass) {
            $hook = $this->factory->get($hookClass);
            $hook->enable(
                function (Request $request) use ($self) {
                    return $self->handleRequest($request);
                }
            );
        }
    }

    /**
     * Turns off VCR.
     */
    public function __destruct()
    {
        if ($this->isOn) {
            $this->turnOff();
        }
    }
}
