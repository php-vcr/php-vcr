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

    public function hasResponse(Request $request, int $index = 0): bool
    {
        return null !== $this->playback($request, $index);
    }

    public function playback(Request $request, int $index = 0): ?Response
    {
        foreach ($this->storage as $recording) {
            $storedRequest = Request::fromArray($recording['request']);

            // Support legacy cassettes which do not have the 'index' key by setting the index to the searched one to
            // always match this record if the request matches
            $recording['index'] ??= $index;

            if ($storedRequest->matches($request, $this->getRequestMatchers()) && $index == $recording['index']) {
                return Response::fromArray($recording['response']);
            }
        }

        return null;
    }

    public function record(Request $request, Response $response, int $index = 0): void
    {
        if ($this->hasResponse($request, $index)) {
            return;
        }

        $this->storage->storeRecording([
            'request' => $request->toArray(),
            'response' => $response->toArray(),
            'index' => $index,
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
