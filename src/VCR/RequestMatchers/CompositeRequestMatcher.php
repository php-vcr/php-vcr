<?php


namespace VCR\RequestMatchers;

use VCR\Request;
use VCR\Util\Assertion;

/**
 * A matcher that matches only if all the matchers that it contains are matching too.
 */
class CompositeRequestMatcher implements RequestMatcherInterface
{
    /**
     * @var array|RequestMatcherInterface[]
     */
    private $requestMatchers;

    /**
     * @param RequestMatcherInterface[] $requestMatchers
     */
    public function __construct(array $requestMatchers)
    {
        Assertion::allIsInstanceOf($requestMatchers, RequestMatcherInterface::class);
        $this->requestMatchers = $requestMatchers;
    }

    /**
     * Returns true if all the matchers from the CompositeMatcher return true.
     *
     * @param  Request $storedRequest First request to match, coming from the cassette.
     * @param  Request $request Second request to match, the request performed by the user.
     *
     * @return boolean True if both specified requests match.
     */
    public function match(Request $storedRequest, Request $request): bool
    {
        foreach ($this->requestMatchers as $matcher) {
            if ($matcher->match($storedRequest, $request) === false) {
                return false;
            }
        }

        return true;
    }
}
