<?php

namespace VCR\RequestMatchers;

use VCR\Request;

class QueryStringMatcher extends SimpleMatcher implements IRequestMatcher {
    public function getName() {
        return "query_string";
    }

    public function getMismatchMessagePrefix() {
        return "Query string";
    }

    public function getRequestValue(Request $request) {
        return $request->getQuery();
    }
}
