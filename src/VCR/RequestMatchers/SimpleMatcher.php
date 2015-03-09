<?php

namespace VCR\RequestMatchers;

use VCR\Request;

abstract class SimpleMatcher extends RequestMatcher implements IRequestMatcher {
    public function match(Request $first, Request $second) {
        return $this->getRequestValue($first) === $this->getRequestValue($second);
    }

    public function checkMatch(Request $first, Request $second, MismatchExplainer $mismatchExplainer) {
        if (!$this->match($first, $second)) {
            $mismatchExplainer->markMismatch($first, $second, $this);
        }
    }

    public function getMismatchMessage(Request $first, Request $second) {
        return $this->buildSimpleMismatchMessage($this->getMismatchMessagePrefix(), $this->getRequestValue($first), $this->getRequestValue($second));
    }

    abstract public function getMismatchMessagePrefix();
    abstract public function getRequestValue(Request $request);

    public function getFieldMismatch() {

    }
}
