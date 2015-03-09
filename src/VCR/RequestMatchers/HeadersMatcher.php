<?php

namespace VCR\RequestMatchers;

use VCR\Request;

class HeadersMatcher extends SimpleMatcher implements IRequestMatcher {
    public function getName() {
        return "headers";
    }

    public function getMismatchMessagePrefix() {
        return "Headers";
    }

    public function getRequestValue(Request $request) {
        return print_r(array_filter($request->getHeaders()), true);
    }
}
