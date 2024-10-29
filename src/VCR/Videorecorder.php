<?php

declare(strict_types=1);

namespace VCR;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use VCR\Event\AfterHttpRequestEvent;
use VCR\Event\AfterPlaybackEvent;
use VCR\Event\BeforeHttpRequestEvent;
use VCR\Event\BeforePlaybackEvent;
use VCR\Event\BeforeRecordEvent;
use VCR\Event\Event;
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
    protected ?Cassette $cassette = null;

    protected bool $isOn = false;

    protected EventDispatcherInterface $eventDispatcher;

    /**
     * @var array<string, int>
     */
    protected array $indexTable = [];

    public function __construct(
        protected Configuration $config,
        protected HttpClient $client,
        protected VCRFactory $factory
    ) {
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->eventDispatcher = $dispatcher;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        if (!isset($this->eventDispatcher)) {
            $this->eventDispatcher = new EventDispatcher();
        }

        return $this->eventDispatcher;
    }

    private function dispatch(Event $event, ?string $eventName = null): Event
    {
        $res = $this->getEventDispatcher()->dispatch($event, $eventName);

        Assertion::isInstanceOf($res, Event::class);

        return $res;
    }

    /**
     * @api
     */
    public function turnOn(): void
    {
        if ($this->isOn) {
            $this->turnOff();
        }

        $this->enableLibraryHooks();
        $this->isOn = true;
    }

    /**
     * @api
     */
    public function turnOff(): void
    {
        if ($this->isOn) {
            $this->disableLibraryHooks();
            $this->eject();
            $this->isOn = false;
        }
    }

    /**
     * @api
     */
    public function eject(): void
    {
        Assertion::true($this->isOn, 'Please turn on VCR before ejecting a cassette, use: VCR::turnOn().');

        $this->cassette = null;
        $this->resetIndex();
    }

    /**
     * @api
     */
    public function insertCassette(string $cassetteName): void
    {
        Assertion::true($this->isOn, 'Please turn on VCR before inserting a cassette, use: VCR::turnOn().');

        if (null !== $this->cassette) {
            $this->eject();
        }

        $storage = $this->factory->get('Storage', [$cassetteName]);

        $this->cassette = new Cassette($cassetteName, $this->config, $storage);
        $this->enableLibraryHooks();
        $this->resetIndex();
    }

    /**
     * @api
     */
    public function configure(): Configuration
    {
        return $this->config;
    }

    /**
     * Records, sends or plays back a intercepted request.
     *
     * If a request was already recorded on a cassette it's response is returned,
     * otherwise the request is issued and it's response recorded (and returned).
     *
     * @throws \LogicException if the mode is set to none or once and
     *                         the cassette did not have a matching response
     *
     * @api
     */
    public function handleRequest(Request $request): Response
    {
        if (null === $this->cassette) {
            throw new \BadMethodCallException('Invalid http request. No cassette inserted. Please make sure to insert a cassette in your unit test using '."VCR::insertCassette('name');");
        }

        $this->dispatch(new BeforePlaybackEvent($request, $this->cassette), VCREvents::VCR_BEFORE_PLAYBACK);

        // Add an index to the request to allow recording identical requests and play them back in the same sequence.
        $index = $this->iterateIndex($request);
        $response = $this->cassette->playback($request, $index);

        // Playback succeeded and the recorded response can be returned.
        if (!empty($response)) {
            $this->dispatch(
                new AfterPlaybackEvent($request, $response, $this->cassette),
                VCREvents::VCR_AFTER_PLAYBACK
            );

            return $response;
        }

        if (VCR::MODE_NONE === $this->config->getMode()
            || VCR::MODE_ONCE === $this->config->getMode()
            && false === $this->cassette->isNew()
        ) {
            throw new \LogicException(\sprintf("The request does not match a previously recorded request and the 'mode' is set to '%s'. If you want to send the request anyway, make sure your 'mode' is set to 'new_episodes'. ".'Please see http://php-vcr.github.io/documentation/configuration/#record-modes.'."\nCassette: %s \n Request: %s", $this->config->getMode(), $this->cassette->getName(), print_r($request->toArray(), true)));
        }

        $this->disableLibraryHooks();

        try {
            $this->dispatch(new BeforeHttpRequestEvent($request), VCREvents::VCR_BEFORE_HTTP_REQUEST);
            $response = $this->client->send($request);
            $this->dispatch(new AfterHttpRequestEvent($request, $response), VCREvents::VCR_AFTER_HTTP_REQUEST);

            $this->dispatch(new BeforeRecordEvent($request, $response, $this->cassette), VCREvents::VCR_BEFORE_RECORD);
            $this->cassette->record($request, $response, $index);
        } finally {
            $this->enableLibraryHooks();
        }

        return $response;
    }

    /**
     * @api
     */
    protected function disableLibraryHooks(): void
    {
        foreach ($this->config->getLibraryHooks() as $hookClass) {
            $hook = $this->factory->get($hookClass);
            $hook->disable();
        }
    }

    /**
     * @api
     */
    protected function enableLibraryHooks(): void
    {
        $self = $this;
        foreach ($this->config->getLibraryHooks() as $hookClass) {
            $hook = $this->factory->get($hookClass);
            $hook->enable(
                fn (Request $request) => $self->handleRequest($request)
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

    protected function iterateIndex(Request $request): int
    {
        $hash = $request->getHash();
        if (!isset($this->indexTable[$hash])) {
            $this->indexTable[$hash] = -1;
        }

        return ++$this->indexTable[$hash];
    }

    public function resetIndex(): void
    {
        $this->indexTable = [];
    }
}
