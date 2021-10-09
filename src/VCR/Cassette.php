<?php

declare(strict_types=1);

namespace VCR;

use VCR\Storage\Storage;

/**
 * A Cassette records and plays back pairs of Requests and Responses in a Storage.
 */
class Cassette
{
    /**
     * @param Storage<array> $storage
     */
    public function __construct(
        protected string $name,
        protected Configuration $config,
        protected Storage $storage
    ) {
    }

    public function hasResponse(Request $request): bool
    {
        return null !== $this->playback($request);
    }

    public function playback(Request $request): ?Response
    {
        foreach ($this->storage as $recording) {
            $storedRequest = Request::fromArray($recording['request']);
            if ($storedRequest->matches($request, $this->getRequestMatchers())) {
                return Response::fromArray($recording['response']);
            }
        }

        return null;
    }

    public function record(Request $request, Response $response): void
    {
        if ($this->hasResponse($request)) {
            return;
        }

        $this->storage->storeRecording([
            'request' => $request->toArray(),
            'response' => $response->toArray(),
        ]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isNew(): bool
    {
        return $this->storage->isNew();
    }

    /**
     * @return callable[]
     */
    protected function getRequestMatchers(): array
    {
        return $this->config->getRequestMatchers();
    }
}
