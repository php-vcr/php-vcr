<?php

namespace VCR\RequestMatchers;

use VCR\Request;

class MethodMatcher extends RequestMatcher implements IRequestMatcher {
    public function getName() {
        return "method";
    }

    public function match(Request $first, Request $second) {
        $equal = $first->getMethod() == $second->getMethod();
        if ($this->getMatchObserver() && $this->getMatchObserver()->shouldObserve()) {
            $this->getMatchObserver()->markMismatch($first, $second, $this);
        }
        return $equal;
    }

    public function getMismatchDescription(Request $first, Request $second) {
        $message = " Stored request: Method: {$first->getMethod()}\n"
                   . "Current request: Method: {$second->getMethod()}";
        return $message;
    }
}
