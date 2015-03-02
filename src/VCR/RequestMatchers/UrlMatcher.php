<?php

namespace VCR\RequestMatchers;

use VCR\Request;

class UrlMatcher extends RequestMatcher implements IRequestMatcher {
    public function getName() {
        return "url";
    }

    public function match(Request $first, Request $second) {
        $equal = $first->getUrl() == $second->getUrl();
        if ($this->getMatchObserver() && $this->getMatchObserver()->shouldObserve()) {
            $this->getMatchObserver()->markMismatch($first, $second, $this);
        }
        return $equal;
    }

    public function getMismatchMessage(Request $first, Request $second) {
        return $this->buildSimpleMismatchMessage('URL', $first->getUrl(), $second->getUrl());
    }
}
