<?php

declare(strict_types=1);

/**
 * Stream metadata proxy functions for Symfony HttpClient compatibility.
 *
 * These functions are automatically used by PHP when they exist in a namespace.
 * They wrap the global stream_get_meta_data() function to transform VCR's
 * StreamWrapperHook object into an array for Symfony's NativeHttpClient.
 */

namespace Symfony\Component\HttpClient\Response;

use VCR\LibraryHooks\StreamWrapperHook;

/**
 * Proxy for stream_get_meta_data() that handles VCR's StreamWrapperHook.
 *
 * When VCR intercepts streams, stream_get_meta_data() returns a StreamWrapperHook
 * object in the 'wrapper_data' key instead of an array. This function detects
 * this and extracts the actual array from the wrapper object.
 *
 * @param resource $stream
 *
 * @return array<string,mixed>
 */
function stream_get_meta_data($stream): array
{
    $metadata = stream_get_meta_data($stream);

    // If wrapper_data is a StreamWrapperHook object, extract the actual array
    // StreamWrapperHook has a public $wrapper_data property, so no reflection needed
    if (isset($metadata['wrapper_data']) && $metadata['wrapper_data'] instanceof StreamWrapperHook) {
        $metadata['wrapper_data'] = $metadata['wrapper_data']->wrapper_data;
    }

    return $metadata;
}
