<?php

namespace VCR\RequestMatchers;

use VCR\Request;

abstract class SimpleMatcher extends RequestMatcher implements IRequestMatcher {
    public function match(Request $first, Request $second) {
        $equal = $this->getRequestValue($first) === $this->getRequestValue($second);
        if (!$equal && $this->getMatchObserver() && $this->getMatchObserver()->shouldObserve()) {
            $this->getMatchObserver()->markMismatch($first, $second, $this);
        }
        return $equal;
    }

    public function getMismatchMessage(Request $first, Request $second) {
        return $this->buildSimpleMismatchMessage($this->getMismatchMessagePrefix(), $this->getRequestValue($first), $this->getRequestValue($second));
    }

    abstract public function getMismatchMessagePrefix();
    abstract public function getRequestValue(Request $request);
}
