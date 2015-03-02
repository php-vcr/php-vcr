<?php

namespace VCR\RequestMatchers;

use VCR\MatchObserver;
use VCR\Request;

abstract class RequestMatcher implements IRequestMatcher {
    private $matchObserver;

    public function getMatchObserver() {
        return $this->matchObserver;
    }

    public function setMatchObserver(MatchObserver $observer) {
        $this->matchObserver = $observer;
        return $this;
    }

    public function buildSimpleMismatchMessage($prefix, $firstMessage, $secondMessage) {
        $message = " Stored request: {$prefix}: {$firstMessage}\n"
                 . "Current request: {$prefix}: {$secondMessage}";
        return $message;
    }
}
