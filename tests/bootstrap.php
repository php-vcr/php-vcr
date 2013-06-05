<?php

require_once __DIR__ . '/../src/VCR/LibraryHooks/CurlRewrite/Wrapper.php';
\VCR\LibraryHooks\CurlRewrite\Wrapper::interceptIncludes(
    array(
        'tests/VCR/LibraryHooks/CurlRewriteTest'
    )
);

require_once __DIR__ . '/../vendor/autoload.php';
