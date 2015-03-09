<?php

namespace VCR\RequestMatchers;

use VCR\MismatchExplainer;
use VCR\Request;

abstract class RequestMatcher implements IRequestMatcher {
    private $mismatchExplainer;

    public function getMismatchExplainer() {
        return $this->mismatchExplainer;
    }

    public function setMismatchExplainer(MismatchExplainer $mismatchExplainer) {
        $this->mismatchExplainer = $mismatchExplainer;
        return $this;
    }

    public function buildSimpleMismatchMessage($prefix, $firstMessage, $secondMessage) {
        $message = " Stored request: {$prefix}: {$firstMessage}\n"
                 . "Current request: {$prefix}: {$secondMessage}";
        return $message;
    }
}
