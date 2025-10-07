<?php

declare(strict_types=1);

namespace VCR\Util;

/**
 * Helper for Symfony HttpClient-specific cURL handling.
 *
 * Symfony HttpClient uses CURLOPT_PRIVATE to track response state:
 * - 'HX' = Headers phase (X is retry count)
 * - 'CX' = Content phase
 * - '_X' = Done phase (no content expected)
 */
class SymfonyHttpClientHelper
{
    /**
     * Get CURLOPT_PRIVATE value from cURL handle or use provided snapshot.
     *
     * @param \CurlHandle $ch       cURL handle
     * @param string|null $snapshot Optional snapshot value to avoid reading stale data
     *
     * @return string|null The CURLOPT_PRIVATE value, or null if not set
     */
    public static function getPrivateData(\CurlHandle $ch, ?string $snapshot = null): ?string
    {
        if (null !== $snapshot) {
            return $snapshot;
        }

        $value = curl_getinfo($ch, \CURLINFO_PRIVATE);

        // curl_getinfo returns false if CURLOPT_PRIVATE is not set
        return \is_string($value) ? $value : null;
    }

    /**
     * Check if Symfony HttpClient expects no content based on CURLOPT_PRIVATE state.
     *
     * @param \CurlHandle $ch       cURL handle
     * @param string|null $snapshot Optional CURLOPT_PRIVATE snapshot value
     *
     * @return bool True if no content is expected (state is '_X')
     */
    public static function expectsNoContent(\CurlHandle $ch, ?string $snapshot = null): bool
    {
        $privateData = self::getPrivateData($ch, $snapshot);

        return \is_string($privateData) && isset($privateData[0]) && '_' === $privateData[0];
    }

    /**
     * Transition CURLOPT_PRIVATE state from Headers (H) to Content (C).
     *
     * This must be called before WRITEFUNCTION callback to prevent
     * "Unsupported protocol" error in Symfony HttpClient.
     *
     * @param \CurlHandle $ch       cURL handle
     * @param string|null $snapshot Optional current CURLOPT_PRIVATE snapshot value
     */
    public static function transitionToContentPhase(\CurlHandle $ch, ?string $snapshot = null): void
    {
        $privateData = self::getPrivateData($ch, $snapshot);

        if (\is_string($privateData) && isset($privateData[0]) && 'H' === $privateData[0]) {
            // Change 'H' to 'C', keep the retry counter (second character)
            $newPrivate = 'C'.($privateData[1] ?? '0');
            curl_setopt($ch, \CURLOPT_PRIVATE, $newPrivate);
        }
    }
}
