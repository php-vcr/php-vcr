<?php

namespace VCR\RequestMatchers;

use VCR\Request;

class UrlMatcher extends SimpleMatcher implements IRequestMatcher {
    public function getName() {
        return "url";
    }

    public function getMismatchMessagePrefix() {
        return "URL";
    }

    public function getRequestValue(Request $request) {
        return $request->getUrl();
    }
}
