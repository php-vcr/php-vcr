<?php

namespace VCR\RequestMatchers;

use VCR\MatchObserver;
use VCR\Request;

interface IRequestMatcher {
	public function getName();
	public function setMatchObserver(MatchObserver $matchObserver);
    public function match(Request $first, Request $second);
}
