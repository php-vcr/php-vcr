<?php

namespace VCR\RequestMatchers;

use VCR\MismatchExplainer;
use VCR\Request;

interface IRequestMatcher {
    public function getName();
    public function setMismatchExplainer(MismatchExplainer $mismatchExplainer);
    public function match(Request $first, Request $second);
}
