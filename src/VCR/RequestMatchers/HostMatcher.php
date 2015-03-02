<?php

namespace VCR\RequestMatchers;

use VCR\Request;

class HostMatcher extends SimpleMatcher implements IRequestMatcher {
    public function getName() {
        return "host";
    }

    public function getMismatchMessagePrefix() {
        return "Host";
    }

    public function getRequestValue(Request $request) {
        return $request->getHost();
    }
}
