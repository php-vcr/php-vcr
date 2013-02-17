<?php

namespace Adri\VCR;

/**
 * Factory.
 */
class VCR
{
    public static $isOn = false;
    protected $cassette;
    protected $httpClient;
    protected $config;
    protected $libraryHooks = array();

    public function __construct($config = null)
    {
        $this->config = $config ?: new Configuration;

        if ($this->config->getTurnOnAutomatically()) {
            $this->turnOn();
        }
    }

    /**
     * Initializes VCR and all it's dependencies.
     * @return void
     */
    public function turnOn()
    {
        if (self::$isOn) {
            return;
        }

        $this->libraryHooks = $this->createLibraryHooks();
        $this->enableLibraryHooks();
        $this->httpClient = $this->createHttpClient();

        self::$isOn = true;
    }

    /**
     * Shuts down VCR and all it's dependencies.
     * @return void
     */
    public function turnOff()
    {
        $this->disableLibraryHooks();

        unset($this->cassette);

        self::$isOn = false;
    }

    public function insertCassette($cassetteName)
    {
        $this->cassette = new Cassette($cassetteName, $this->config);
    }

    public function getCurrentCassette()
    {
        return $this->cassette;
    }

    public function handleRequest($request)
    {
        if ($this->getCurrentCassette() === null) {
            throw new \BadMethodCallException(
                'Invalid http request. No cassette inserted. '
                . ' Please make sure to insert a cassette in your unit-test using '
                . '$vcr->insertCassette(\'name\'); or annotation @vcr:cassette(\'name\').'
            );
        }

        $cassette = $this->getCurrentCassette();

        if (!$cassette->hasResponse($request)) {
            $this->disableLibraryHooks();
            $response = $this->httpClient->send($request);
            $cassette->record($request, $response);
            $this->enableLibraryHooks();
        }

        return $cassette->playback($request);
    }

    public function createHttpClient()
    {
        return new Client();
    }

    /**
     * Factory method which returns all configured library hooks.
     * @return array Library hooks.
     */
    protected function createLibraryHooks()
    {
        $hooks = array();
        $self = $this;
        foreach ($this->config->getLibraryHooks() as $hookName) {
            $hooks[] = new $hookName(function(Request $request) use($self) {
                return $self->handleRequest($request);
            });
        }
        return $hooks;
    }

    protected function disableLibraryHooks()
    {
        foreach ($this->libraryHooks as $hook) {
            $hook->disable();
        }
    }

    protected function enableLibraryHooks()
    {
        foreach ($this->libraryHooks as $hook) {
            $hook->enable();
        }
    }

    /**
     * Turns off VCR.
     */
    public function __destruct()
    {
        if (self::$isOn) {
            $this->turnOff();
        }
    }
}

