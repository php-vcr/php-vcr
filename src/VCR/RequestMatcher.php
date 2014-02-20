<?php

namespace VCR;

class RequestMatcher
{

    public static function matchMethod(Request $first, Request $second)
    {
        return $first->getMethod() == $second->getMethod();
    }

    public static function matchUrl(Request $first, Request $second)
    {
        if (null !== $first->getPath()) {
            $path = str_replace('#', '\\#', $first->getPath());

            if (!preg_match('#'.$path.'#', rawurldecode($second->getPath()))) {
                return false;
            }
        }

        return true;
    }

    public static function matchHost(Request $first, Request $second)
    {
        if (null !== $first->getHost()
           && !preg_match('#'.str_replace('#', '\\#', $first->getHost()).'#i', $second->getHost())) {
            return false;
        }

        return true;
    }

    public static function matchHeaders(Request $first, Request $second)
    {
        $firstHeaders = $first->getHeaders();
        foreach ($second->getHeaders() as $key => $pattern) {
            if (!isset($firstHeaders[$key])
                || !preg_match('#'.str_replace('#', '\\#', $pattern[0]).'#', $firstHeaders[$key][0])) {
                return false;
            }
        }

        return true;
    }

    public static function matchBody(Request $first, Request $second)
    {
        if (null !== $first->getBody() && (string) $first->getBody() != (string) $second->getBody() ) {
            return false;
        }

        return true;
    }

    public static function matchPostFields(Request $first, Request $second)
    {
        if (null !== $first->getPostFields()->toArray()
          && $first->getPostFields()->toArray() != $second->getPostFields()->toArray() ) {
            return false;
        }

        return true;
    }

    public static function matchQueryString(Request $first, Request $second)
    {
        if (null !== $first->getQuery(true) && $first->getQuery(true) != $second->getQuery(true)) {
            return false;
        }
        return true;
    }
}
