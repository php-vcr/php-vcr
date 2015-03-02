<?php

namespace VCR\RequestMatchers;

class UrlMatcher extends RequestMatcher implements IRequestMatcher {
    public function getName() {
        return "url";
    }

    public function match(Request $first, Request $second) {
        return $first->getUrl() == $second->getUrl();
    }
}
