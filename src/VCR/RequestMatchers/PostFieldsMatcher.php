<?php

namespace VCR\RequestMatchers;

use VCR\Request;

class PostFieldsMatcher extends SimpleMatcher implements IRequestMatcher {
    public function getName() {
        return "post_fields";
    }

    public function getMismatchMessagePrefix() {
        return "Post fields";
    }

    public function getRequestValue(Request $request) {
        return print_r($request->getPostFields(), true);
    }
}
