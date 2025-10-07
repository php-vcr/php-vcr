<?php

declare(strict_types=1);

namespace VCR\Util;

/**
 * Helper to reconstruct URLs from Host headers.
 *
 * Symfony NativeHttpClient resolves DNS to IP before calling fopen().
 * Example: jsonplaceholder.typicode.com → 188.114.97.2
 * But the original hostname is preserved in the Host header.
 * This class reconstructs the URL with the hostname for proper VCR matching.
 */
class UrlReconstructor
{
    /**
     * Reconstruct URL using Host header if different from parsed host (DNS resolution case).
     *
     * @param string $url        Original URL (may contain IP)
     * @param string $hostHeader Host header value (contains hostname)
     *
     * @return string|null Reconstructed URL with hostname, or null if no reconstruction needed
     */
    public static function reconstructFromHostHeader(string $url, string $hostHeader): ?string
    {
        $parsedUrl = parse_url($url);

        if (false === $parsedUrl || !isset($parsedUrl['host'])) {
            return null;
        }

        // Check if current URL uses a different host than the Host header
        // This indicates DNS resolution has occurred (IP vs hostname)
        if ($hostHeader === $parsedUrl['host']) {
            return null; // No reconstruction needed
        }

        // Reconstruct URL with hostname from Host header instead of IP
        $scheme = $parsedUrl['scheme'] ?? 'http';
        $newUrl = $scheme.'://'.$hostHeader;

        if (isset($parsedUrl['port']) && $parsedUrl['port'] != (('https' === $scheme) ? 443 : 80)) {
            $newUrl .= ':'.$parsedUrl['port'];
        }

        $newUrl .= ($parsedUrl['path'] ?? '/');

        if (isset($parsedUrl['query'])) {
            $newUrl .= '?'.$parsedUrl['query'];
        }

        if (isset($parsedUrl['fragment'])) {
            $newUrl .= '#'.$parsedUrl['fragment'];
        }

        return $newUrl;
    }
}
