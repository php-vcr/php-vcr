<?php

namespace VCR;

class MatchObserver
{
    // This is an array keyed by a hash of the (first aka stored) request.
    private $requestToMatchersMap;
    private $requestCount0;
    private $requestToCountMap;
    private $requestToRequestsMap;
    private $shouldObserve = false;

    public function clear() {
        $this->requestToMatchersMap = array();
        $this->requestCount = 0;
        $this->requestToCountMap = array();
        $this->requestToRequestsMap = array();
    }

    public function markMismatch(Request $first, Request $second, $matcher) {
        $hash = $first->getIdentityHash();
        if (array_key_exists($hash, $this->requestToMatchersMap)) {
            $this->requestToMatchersMap[$hash][] = $matcher;
        } else {
            $this->requestToMatchersMap[$hash] = array($matcher);
            $this->requestCount++;
            $this->requestToCountMap[$hash] = $this->requestCount;
            $this->requestToRequestsMap[$hash] = array($first, $second);
        }
    }

    public function getMismatchMessage() {
        if (count($this->requestToMatchersMap) == 0) {
            return "";
        }

        $hash = $this->getClosestRequestHash();
        $message = $this->buildMismatchMessage($hash);

        return $message;
    }

    protected function buildMismatchMessage($hash) {
        $count = count($this->requestToMatchersMap);
        $message = "The closest match was request #{$this->requestToCountMap[$hash]} of {$count}.\n";
        $matchers = $this->requestToMatchersMap[$hash];
        foreach ($matchers as $matcher) {
            list($first, $second) = $this->requestToRequestsMap[$hash];
            $message .= $matcher->getMismatchMessage($first, $second);
            $message .= "\n";
        }
        return $message;
    }

    protected function getClosestRequestHash() {
        // For the moment for testing, just return the first one
        reset($this->requestToMatchersMap);
        $firstKey = key($this->requestToMatchersMap);
        return $firstKey;
    }

    public function shouldObserve() {
        return $this->shouldObserve;
    }

    public function startObserving() {
        $this->clear();
        $this->shouldObserve = true;
    }

    public function stopObserving() {
        $this->shouldObserve = false;
    }
}
