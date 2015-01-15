<?php

namespace VCR;

use VCR\Util\Assertion;
use VCR\Util\HttpClient;

/**
 * A videorecorder records requests on a cassette.
 *
 * If turned on, a videorecorder will intercept HTTP requests using
 * hooks into libraries like cUrl, SOAP and streamWrappers.
 * Requests and responses will be recorded on a inserted cassette.
 * If a request is already recorded on a cassette the videorecorder
 * will play back its response and not issue a HTTP request.
 *
 * If turned off, HTTP requests won't be intercepted and will
 * hit their original servers.
 *
 * @author Adrian Philipp <mail@adrian-philipp.com>
 */
class Videorecorder
{
    /**
     * @var Configuration Config options like which library hooks to use.
     */
    protected $config;

    /**
     * @var HttpClient Client to use to issue HTTP requests.
     */
    protected $client;

    /**
     * @var VCRFactory Factory which can create instances and resolve dependencies.
     */
    protected $factory;

    /**
     * @var Cassette Cassette on which to store requests and responses.
     */
    protected $cassette;

    /**
     * @var boolean Flag if this videorecorder is turned on or not.
     */
    protected $isOn = false;

    /**
     * Creates a videorecorder instance.
     *
     * @param Configuration $config  Config options like which library hooks to use.
     * @param HttpClient    $client  Client which is used to issue HTTP requests.
     * @param VCRFactory    $factory Factory which can create instances and resolve dependencies.
     */
    public function __construct(Configuration $config, HttpClient $client, VCRFactory $factory)
    {
        $this->config = $config;
        $this->client = $client;
        $this->factory = $factory;
    }

    /**
     * Turns on this videorecorder.
     *
     * This enables configured library hooks.
     *
     * @api
     *
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
     * Turns off this videorecorder.
     *
     * Library hooks will be disabled and cassettes ejected.
     *
     * @api
     *
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

    /**
     * Eject the currently inserted cassette.
     *
     * Recording and playing back requests won't be possible after ejecting.
     *
     * @api
     *
     * @return void
     */
    public function eject()
    {
        Assertion::true($this->isOn, 'Please turn on VCR before ejecting a cassette, use: VCR::turnOn().');
        $this->cassette = null;
    }

    /**
     * Inserts a cassette to record responses and requests on.
     *
     * @api
     *
     * @param string $cassetteName Name of the cassette (used for the cassette filename).
     *
     * @return void
     * @throws VCRException If videorecorder is turned off when inserting a cassette.
     */
    public function insertCassette($cassetteName)
    {
        Assertion::true($this->isOn, 'Please turn on VCR before inserting a cassette, use: VCR::turnOn().');

        if (!is_null($this->cassette)) {
            $this->eject();
        }

        $storage = $this->factory->get('Storage', array($cassetteName));

        $this->cassette = new Cassette($cassetteName, $this->config, $storage);
        $this->enableLibraryHooks();
    }

    /**
     * Returns the current Configuration for this videorecorder.
     *
     * @api
     *
     * @return Configuration Configuration for this videorecorder.
     */
    public function configure()
    {
        return $this->config;
    }

    /**
     * Records, sends or plays back a intercepted request.
     *
     * If a request was already recorded on a cassette it's response is returned,
     * otherwise the request is issued and it's response recorded (and returned).
     *
     * @api
     *
     * @param Request $request Intercepted request.
     *
     * @return Response                Response for the intercepted request.
     * @throws \BadMethodCallException If there was no cassette inserted.
     * @throws \LogicException         If the mode is set to none or once and the cassette did not have a matching response.
     */
    public function handleRequest(Request $request)
    {
        if ($this->cassette === null) {
            throw new \BadMethodCallException(
                "Invalid http request. No cassette inserted. "
                . "Please make sure to insert a cassette in your unit test using "
                . "VCR::insertCassette('name');"
            );
        }
        $cassettename = $this->cassette->getName();
        $cassettename = pathinfo($cassettename, PATHINFO_FILENAME);
        $md5 = md5(md5($request->getBody()).md5($request->getUrl()).$request->getMethod());
        $this->insertCassette($cassettename . "." . $md5);

        $response = $this->cassette->playback($request);
        if ($response !== null) {
             return $response;
        }

        if ($this->config->getMode() == 'none' || $this->config->getMode() == 'once' && $this->cassette->isNew() === false) {
            throw new \LogicException(
                "Invalid http request. The cassette inserted did not have the necessary response. "
                . "If you want to send a request anyway, make sure your mode is set to new_episodes.");
        }

        $this->disableLibraryHooks();
        $response = $this->client->send($request);
        $this->cassette->record($request, $response);
        $this->enableLibraryHooks();

        return $response;
    }

    /**
     * Disables all library hooks.
     *
     * @api
     *
     * @return void
     */
    protected function disableLibraryHooks()
    {
        foreach ($this->config->getLibraryHooks() as $hookClass) {
            $hook = $this->factory->get($hookClass);
            $hook->disable();
        }
    }

    /**
     * Enables configured library hooks.
     *
     * @api
     *
     * @return void
     */
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
     * Turns off this videorecorder when instance is destroyed.
     *
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        if ($this->isOn) {
            $this->turnOff();
        }
    }
}
