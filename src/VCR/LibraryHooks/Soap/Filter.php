<?php

namespace VCR\LibraryHooks\Soap;

use VCR\LibraryHooks\AbstractFilter;

class Filter extends AbstractFilter
{
    const NAME = 'vcr_soap';

    /**
     * @param string $code
     *
     * @return mixed
     */
    public function transformCode($code)
    {
        return $code;
    }
}
