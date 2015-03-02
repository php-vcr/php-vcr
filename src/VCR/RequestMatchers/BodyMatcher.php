<?php

namespace VCR\RequestMatchers;

use VCR\Request;

class BodyMatcher extends SimpleMatcher implements IRequestMatcher {
    public function getName() {
        return "body";
    }

    public function getMismatchMessagePrefix() {
        return "Body";
    }

    public function getRequestValue(Request $request) {
        return $request->getBody();
    }
}
