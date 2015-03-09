<?php

namespace VCR;

class MismatchExplainer
{
    private $requestMismatchers;

    public function __construct(Request $currentRequest) {
        $this->currentRequest = $currentRequest;
        $this->requestMismatchers = new SplObjectStorage();
    }

    public function markMismatch(Request $storedRequest, $matcher) {
        if (isset($this->requestMismatchers[$storedRequest])) {
            $requestMismatch = $this->requestMismatchers[$storedRequest];
            $requestMismatch->addFieldMismatch($matcher->getFieldMismatch());
        } else {
            $requestMismatch = new RequestMismatch($storedRequest);
            $requestMismatch->addFieldMismatch($matcher->getFieldMismatch());
            $this->requestMismatchers[$storedRequest] = $requestMismatch;
        }
    }

    public function getMismatchMessage() {
        if ($this->requestMismatchers->count() == 0) {
            return "";
        }

        return $this->buildMismatchMessage();
    }

    protected function buildMismatchMessage() {
        $count = $this->requestMismatchers->count();
        list($requestMismatch, $index) = $this->getClosestMatch();
        $message = "The closest match was request #{$index} of {$count}.\n";
        $fieldMismatchers = $requestMismatch->getFieldMismatchers()
        foreach ($fieldMismatchers as $fieldMismatcher) {
            $message .= $fieldMismatcher->getMismatchMessage($first, $second);
            $message .= "\n";
        }
        return $message;
    }

    protected function getClosestMatch() {
        // Here's my plan for this: If there's only one request, return it. If
        // there are multiple, order them by count of mismatches, and if there
        // is a single winner, return it. If there is not, then SUM a
        // getProximityValue() value on each matcher (per request). The
        // proximity value will be the levenshtein value for simple strings
        // (method, url, host, etc). For arrays, transform them into one long
        // string and again use the levenshtein value.

        // For the moment for testing, just return the first one
        reset($this->requestMismatchers);
        $firstKey = key($this->requestMismatchers);
        return [$this->requestMismatchers[$firstKey], 0];
    }
}
