<?php

namespace VCR\RequestMatchers;

use VCR\Request;

class MethodMatcher extends SimpleMatcher implements IRequestMatcher {
    public function getName() {
        return "method";
    }

    public function getMismatchMessagePrefix() {
        return "Method";
    }

    public function getRequestValue(Request $request) {
        return $request->getMethod();
    }
}
