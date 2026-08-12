<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Storage;

use org\bovigo\vfs\vfsStream;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;
use VCR\LibraryHooks\StreamWrapperHook;
use VCR\Storage\EncryptedStorageFactory;
use VCR\Storage\Encryption\EncryptionKey;
use VCR\Storage\RedactingStorageFactory;
use VCR\Storage\Redaction\MissingSecretException;
use VCR\Storage\Redaction\RedactionRules;
use VCR\Storage\Redaction\Scope;
use VCR\Storage\YamlStorageFactory;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;
use VCR\VCR;

final class RedactingStorageIntegrationTest extends AbstractHttpServerIntegrationTestCase
{
    private const SECRET = 'hunter2-do-not-commit';

    /**
     * @param array<string,string> $headers
     */
    private function getWithHeaders(string $url, array $headers = []): int
    {
        $headerLines = '';
        foreach ($headers as $name => $value) {
            $headerLines .= $name.': '.$value."\r\n";
        }

        $context = stream_context_create([
            'http' => ['method' => 'GET', 'header' => $headerLines, 'ignore_errors' => true],
        ]);

        return $this->performAndReadStatus($url, $context);
    }

    /**
     * @param array<string,string> $headers
     */
    private function postWithHeaders(string $url, string $body, array $headers = []): int
    {
        $headerLines = "Content-Type: application/x-www-form-urlencoded\r\n";
        foreach ($headers as $name => $value) {
            $headerLines .= $name.': '.$value."\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerLines,
                'content' => $body,
                'ignore_errors' => true,
            ],
        ]);

        return $this->performAndReadStatus($url, $context);
    }

    /**
     * @param array<string,string> $headers
     */
    private function curlPost(string $url, string $body, array $headers = [], bool $captureRequestHeaders = false): int
    {
        $ch = curl_init($url);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_POST, true);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $body);

        if ([] !== $headers) {
            $headerLines = [];
            foreach ($headers as $name => $value) {
                $headerLines[] = $name.': '.$value;
            }
            curl_setopt($ch, \CURLOPT_HTTPHEADER, $headerLines);
        }

        if ($captureRequestHeaders) {
            curl_setopt($ch, \CURLINFO_HEADER_OUT, true);
        }

        curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);

        // curl_close() is a documented no-op since PHP 8.0 and deprecated on PHP 8.5, so it is
        // deliberately omitted here.
        return $statusCode;
    }

    /**
     * @param array<string,mixed> $fields
     */
    private function curlPostFields(string $url, array $fields): int
    {
        $ch = curl_init($url);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_POST, true);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $fields);
        curl_exec($ch);

        return (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
    }

    /**
     * @param resource $context
     */
    private function performAndReadStatus(string $url, $context): int
    {
        $stream = fopen($url, 'r', false, $context);

        if (false === $stream) {
            throw new \RuntimeException(\sprintf('Unable to open a stream to "%s".', $url));
        }

        // StreamWrapperHook::streamGetMetaData() reports the real response header lines whether
        // the stream was served by VCR's own wrapper (replay) or PHP's native http wrapper
        // (recording, since library hooks are disabled around the real outbound request).
        $meta = StreamWrapperHook::streamGetMetaData($stream);
        fclose($stream);

        /** @var string[] $responseHeaders */
        $responseHeaders = \is_array($meta['wrapper_data'] ?? null) ? $meta['wrapper_data'] : [];

        return $this->statusFromResponseHeaders($responseHeaders);
    }

    /**
     * @param string[] $responseHeaders
     */
    private function statusFromResponseHeaders(array $responseHeaders): int
    {
        if (!isset($responseHeaders[0]) || !preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeaders[0], $matches)) {
            throw new \RuntimeException('Unable to determine the HTTP status code from the response headers.');
        }

        return (int) $matches[1];
    }

    private function cassettePath(string $cassette): string
    {
        return vfsStream::url('testDir').'/'.$cassette;
    }

    private function cassetteContents(string $cassette): string
    {
        return (string) file_get_contents($this->cassettePath($cassette));
    }

    /**
     * @return array<string,mixed>
     */
    private function firstRecording(string $cassette): array
    {
        $parsed = SymfonyYaml::parse($this->cassetteContents($cassette));

        return \is_array($parsed[0] ?? null) ? $parsed[0] : [];
    }

    /**
     * The core proof of the feature (Verification #1): a real secret goes through
     * `filterSensitiveData()`, never touches the disk, and replay still succeeds with every
     * default request matcher enabled — reversible redaction must not force narrower matching.
     */
    public function testFilterSensitiveDataHidesSecretAndReplaysWithAllDefaultMatchersEnabled(): void
    {
        $rules = RedactionRules::create()->filterSensitiveData('<<AUTH_TOKEN>>', self::SECRET);

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $this->recordAndReplay(
            'redact-core-proof',
            fn (): int => $this->postWithHeaders(self::$baseUrl.'/post', 'ping=pong', ['Authorization' => 'Bearer '.self::SECRET])
        );

        $raw = $this->cassetteContents('redact-core-proof');

        $this->assertStringNotContainsString(self::SECRET, $raw, 'The secret must never hit the disk.');
        $this->assertStringContainsString('<<AUTH_TOKEN>>', $raw);
        // The test server echoes request headers back into the response body, so a passing
        // assertion above already proves both request and response coverage; this pins it down
        // explicitly against the parsed structure instead of relying on incidental byte layout.
        $recording = $this->firstRecording('redact-core-proof');
        $this->assertStringContainsString('<<AUTH_TOKEN>>', $recording['request']['headers']['Authorization']);
        $this->assertStringContainsString('<<AUTH_TOKEN>>', $recording['response']['body']);
    }

    /**
     * Verification #2: CURLINFO_HEADER_OUT captures the raw outgoing request headers into
     * response.curl_info.request_header — a leak channel every naive redaction approach misses.
     * filterSensitiveData() walks the whole recording, so it must cover this path too.
     */
    public function testCurlInfoHeaderOutSecretIsRedactedEverywhereInCassette(): void
    {
        $rules = RedactionRules::create()->filterSensitiveData('<<AUTH_TOKEN>>', self::SECRET);

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $this->recordAndReplay(
            'redact-curl-header-out',
            fn (): int => $this->curlPost(self::$baseUrl.'/post', 'ping=pong', ['Authorization' => 'Bearer '.self::SECRET], true)
        );

        $raw = $this->cassetteContents('redact-curl-header-out');
        $this->assertStringNotContainsString(self::SECRET, $raw, 'The secret must be absent from the entire cassette file.');

        $recording = $this->firstRecording('redact-curl-header-out');
        $requestHeaderOut = $recording['response']['curl_info']['request_header'] ?? null;
        $this->assertIsString($requestHeaderOut, 'CURLINFO_HEADER_OUT must have been captured.');
        $this->assertStringContainsString('<<AUTH_TOKEN>>', $requestHeaderOut, 'The captured outgoing headers must be redacted, not skipped.');
    }

    /**
     * PR #344 checklist: "Declare the secret as a callback resolved at runtime".
     */
    public function testCallableSecretSourceRoundTripsWithoutArguments(): void
    {
        $secret = self::SECRET;
        $rules = RedactionRules::create()->filterSensitiveData('<<AUTH_TOKEN>>', static fn (): string => $secret);

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $this->recordAndReplay(
            'redact-callable-secret',
            fn (): int => $this->postWithHeaders(self::$baseUrl.'/post', 'ping=pong', ['Authorization' => 'Bearer '.$secret])
        );

        $raw = $this->cassetteContents('redact-callable-secret');
        $this->assertStringNotContainsString($secret, $raw);
        $this->assertStringContainsString('<<AUTH_TOKEN>>', $raw);
    }

    /**
     * PR #344 checklist: "Callback receives the request/response so the secret can be derived
     * from them" — evaluated against the pre-redaction recording on write.
     */
    public function testRecordingAwareCallableSecretIsDerivedFromThePreRedactionRecording(): void
    {
        $rules = RedactionRules::create()->filterSensitiveData(
            '<<DERIVED_TOKEN>>',
            static fn (array $recording): string => 'derived-'.$recording['request']['method']
        );

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $derivedSecret = 'derived-POST';

        $this->recordAndReplay(
            'redact-recording-aware-secret',
            fn (): int => $this->postWithHeaders(self::$baseUrl.'/post', 'ping=pong', ['X-Derived-Secret' => $derivedSecret])
        );

        $raw = $this->cassetteContents('redact-recording-aware-secret');
        $this->assertStringNotContainsString($derivedSecret, $raw);
        $this->assertStringContainsString('<<DERIVED_TOKEN>>', $raw);
    }

    /**
     * PR #344 checklist: "Introspect the configured redactions" — safeRequestMatchers() and
     * invalidatedRequestMatchers() are pure functions over the registered rules.
     */
    public function testRedactionRulesExposeSafeAndInvalidatedRequestMatchers(): void
    {
        $rules = RedactionRules::create()
            ->allowIrreversibleRequestRedaction()
            ->header('X-Nonce', null, Scope::REQUEST)
            ->queryParameter('nonce');

        $this->assertSame(['headers', 'query_string'], $rules->invalidatedRequestMatchers());
        $this->assertSame(
            ['method', 'url', 'host', 'body', 'post_fields', 'soap_operation'],
            $rules->safeRequestMatchers()
        );
        $this->assertCount(2, $rules->rules());
    }

    /**
     * Verification #5 (falsy secret): the plan explicitly rejected "silently skipped" in favour
     * of MissingSecretException, end to end through RedactingStorageFactory.
     */
    public function testFalsySecretSourceRaisesMissingSecretExceptionThroughTheFactory(): void
    {
        $rules = RedactionRules::create()->filterSensitiveData('<<MISSING_TOKEN>>', static fn (): ?string => null);

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $this->expectException(MissingSecretException::class);

        VCR::turnOn();
        VCR::insertCassette('redact-falsy-secret');
        $this->postWithHeaders(self::$baseUrl.'/post', 'ping=pong');
    }

    /**
     * Verification #5 (shared secret): two placeholders sharing the same secret must not throw
     * or corrupt the recording — pins the "ordered pair list, not a flipped hash" decision.
     */
    public function testTwoPlaceholdersSharingTheSameSecretBothRoundTrip(): void
    {
        $rules = RedactionRules::create()
            ->filterSensitiveData('<<TOKEN_A>>', self::SECRET)
            ->filterSensitiveData('<<TOKEN_B>>', self::SECRET);

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $this->recordAndReplay(
            'redact-shared-secret',
            fn (): int => $this->postWithHeaders(self::$baseUrl.'/post', 'ping=pong', ['Authorization' => 'Bearer '.self::SECRET])
        );

        $raw = $this->cassetteContents('redact-shared-secret');
        $this->assertStringNotContainsString(self::SECRET, $raw);
    }

    /**
     * Verification #6: redact-then-encrypt, the documented composition order.
     */
    public function testStackingRedactionOverEncryptionHidesSecretInCiphertextCassette(): void
    {
        if (!\extension_loaded('sodium')) {
            $this->markTestSkipped('The encrypted storage requires ext-sodium, which is not loaded.');
        }

        $key = EncryptionKey::fromBinary(str_repeat("\x5a", \SODIUM_CRYPTO_KDF_KEYBYTES));
        $rules = RedactionRules::create()->filterSensitiveData('<<AUTH_TOKEN>>', self::SECRET);

        VCR::configure()->setStorageFactory(
            RedactingStorageFactory::withRules(EncryptedStorageFactory::withKey(new YamlStorageFactory(), $key), $rules)
        );

        $this->recordAndReplay(
            'redact-then-encrypt',
            fn (): int => $this->postWithHeaders(self::$baseUrl.'/post', 'ping=pong', ['Authorization' => 'Bearer '.self::SECRET])
        );

        $raw = $this->cassetteContents('redact-then-encrypt');
        $this->assertStringNotContainsString(self::SECRET, $raw);
        $this->assertStringContainsString('vcr:enc:v1:', $raw);
    }

    /**
     * External sanitizer checklist: "Ignore query fields" (valueless form, under the irreversible
     * opt-in). The query value legitimately differs between the record and replay calls; the
     * feature's promise is that wiring up safeRequestMatchers() keeps replay working anyway.
     *
     * QueryParameterRule targets request.url specifically, not the whole recording — unlike
     * filterSensitiveData(), it makes no claim about response.curl_info.url (curl's own
     * CURLINFO_EFFECTIVE_URL echo of the real request URL, unrelated to this rule), so the
     * assertion below is scoped to the field the rule actually owns.
     */
    public function testQueryParameterIgnoredWithoutSourceUsingSafeRequestMatchers(): void
    {
        $rules = RedactionRules::create()->allowIrreversibleRequestRedaction()->queryParameter('nonce');

        VCR::configure()
            ->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules))
            ->enableRequestMatchers($rules->safeRequestMatchers());

        $callNumber = 0;
        $this->recordAndReplay(
            'redact-query-ignored',
            function () use (&$callNumber): int {
                ++$callNumber;

                return $this->getWithHeaders(self::$baseUrl.'/get?nonce=call-'.$callNumber);
            }
        );

        $recording = $this->firstRecording('redact-query-ignored');
        $this->assertStringNotContainsString('call-1', $recording['request']['url'], 'The query parameter must be blanked in the recorded request URL.');
        $this->assertStringContainsString('nonce=', $recording['request']['url']);
    }

    /**
     * External sanitizer checklist: "Ignore request headers" (reversible form). Both the record
     * and the replay call send the *real* secret, exactly as a live HTTP client always would: what
     * lands on disk is the placeholder, and restoration puts the real value back before the
     * matchers run, so replay matches with every default matcher — `headers` included — still
     * enabled. Nothing here is hand-tuned to make the two sides agree.
     *
     * header() targets the header location specifically, not the whole recording — the test server
     * used here echoes every request header back into the response body, which HeaderRule never
     * touches (only filterSensitiveData() walks the whole recording), so the secret-absence
     * assertion is scoped to the recorded request, the part the rule actually owns.
     */
    public function testHeaderWithSourceConcealsSecretAndStillMatchesOnReplay(): void
    {
        $rules = RedactionRules::create()->header('X-Signature', self::SECRET, Scope::REQUEST);

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $this->recordAndReplay(
            'redact-header-source',
            fn (): int => $this->postWithHeaders(self::$baseUrl.'/post', 'ping=pong', ['X-Signature' => self::SECRET])
        );

        $recording = $this->firstRecording('redact-header-source');

        $this->assertSame(
            '<<REDACTED:HEADER:x-signature>>',
            $recording['request']['headers']['X-Signature'],
            'The recorded header must carry the placeholder, never the real signature.'
        );
        $this->assertStringNotContainsString(
            self::SECRET,
            (string) json_encode($recording['request']),
            'The secret must be absent from the recorded request on disk.'
        );
    }

    /**
     * The same proof for host(), the only built-in rule that invalidates more than one matcher when
     * it is irreversible: given the real host as its source it invalidates none, rewriting both
     * request.url and the Host header on the way out and putting both back on the way in.
     */
    public function testHostWithSourceConcealsTheRealHostAndStillMatchesOnReplay(): void
    {
        $realHost = (string) parse_url(self::$baseUrl, \PHP_URL_HOST);
        $rules = RedactionRules::create()->host($realHost);

        $this->assertSame([], $rules->invalidatedRequestMatchers());

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $this->recordAndReplay('redact-host-source', fn (): int => $this->getWithHeaders(self::$baseUrl.'/get'));

        $recording = $this->firstRecording('redact-host-source');
        $port = (string) parse_url(self::$baseUrl, \PHP_URL_PORT);

        $this->assertStringContainsString('redacted-host.invalid', $recording['request']['url']);
        $this->assertStringNotContainsString($realHost, $recording['request']['url']);
        $this->assertSame('redacted-host.invalid:'.$port, $recording['request']['headers']['Host'] ?? null);
    }

    /**
     * External sanitizer checklist: "Ignore all headers ('*')" and "Ignore response headers
     * (incl. '*')" — allHeaders() is response-scoped here, which is always legal unconditionally
     * since responses are never matched against.
     */
    public function testAllHeadersWildcardBlanksEveryResponseHeaderValue(): void
    {
        $rules = RedactionRules::create()->allHeaders(Scope::RESPONSE);

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $this->recordAndReplay('redact-all-response-headers', fn (): int => $this->getWithHeaders(self::$baseUrl.'/get'));

        $recording = $this->firstRecording('redact-all-response-headers');
        $this->assertNotEmpty($recording['response']['headers'], 'The response must have carried at least one header to blank.');
        foreach ($recording['response']['headers'] as $value) {
            $this->assertSame('', $value);
        }
    }

    /**
     * External sanitizer checklist: "Ignore hostname" — host() rewrites the host consistently in
     * both request.url and the Host header (valueless form, under the irreversible opt-in, since
     * the live test server's real host cannot be substituted for another reachable one here).
     */
    public function testHostRedactedConsistentlyInUrlAndHostHeader(): void
    {
        $rules = RedactionRules::create()->allowIrreversibleRequestRedaction()->host();

        VCR::configure()
            ->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules))
            ->enableRequestMatchers($rules->safeRequestMatchers());

        $this->recordAndReplay('redact-host', fn (): int => $this->getWithHeaders(self::$baseUrl.'/get'));

        $recording = $this->firstRecording('redact-host');
        $realHost = (string) parse_url(self::$baseUrl, \PHP_URL_HOST).':'.parse_url(self::$baseUrl, \PHP_URL_PORT);

        $this->assertStringNotContainsString($realHost, $recording['request']['url']);
        $this->assertStringContainsString('redacted', $recording['request']['url']);
        $this->assertSame('redacted', $recording['request']['headers']['Host'] ?? null);
    }

    /**
     * External sanitizer checklist: "Request body scrubbers (chained callbacks)" — multiple
     * body() calls chain in registration order, each seeing the previous rule's output.
     */
    public function testChainedBodyScrubbersApplyInRegistrationOrder(): void
    {
        $rules = RedactionRules::create()
            ->allowIrreversibleRequestRedaction()
            ->body(static fn (string $body): string => str_replace('secret', 'MASKED', $body), Scope::REQUEST)
            ->body(static fn (string $body): string => strtoupper($body), Scope::REQUEST);

        VCR::configure()
            ->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules))
            ->enableRequestMatchers($rules->safeRequestMatchers());

        $this->recordAndReplay(
            'redact-chained-body',
            fn (): int => $this->postWithHeaders(self::$baseUrl.'/post', 'greeting=hello-secret-world')
        );

        $recording = $this->firstRecording('redact-chained-body');
        $this->assertSame('GREETING=HELLO-MASKED-WORLD', $recording['request']['body']);
    }

    /**
     * External sanitizer checklist: "Response body scrubbers" — always legal unconditionally,
     * since the response is never matched against; no opt-in or matcher narrowing required.
     */
    public function testResponseBodyScrubberRedactsWithoutInvalidatingMatchers(): void
    {
        $rules = RedactionRules::create()->body(static fn (string $body): string => 'scrubbed', Scope::RESPONSE);

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $this->recordAndReplay('redact-response-body', fn (): int => $this->getWithHeaders(self::$baseUrl.'/get'));

        $recording = $this->firstRecording('redact-response-body');
        $this->assertSame('scrubbed', $recording['response']['body']);
    }

    /**
     * External sanitizer checklist: "Post field scrubbers (callback over the array)" chained via
     * multiple postFields() calls, plus postField() for a single key — both request-scoped and
     * irreversible by nature.
     */
    public function testChainedPostFieldScrubbersAndSinglePostFieldRule(): void
    {
        $rules = RedactionRules::create()
            ->allowIrreversibleRequestRedaction()
            ->postFields(static function (array $fields): array {
                if (isset($fields['card'])) {
                    $fields['card'] = 'MASKED-CARD';
                }

                return $fields;
            })
            ->postFields(static function (array $fields): array {
                $fields['scrubbed_by'] = 'chain';

                return $fields;
            })
            ->postField('password');

        VCR::configure()
            ->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules))
            ->enableRequestMatchers($rules->safeRequestMatchers());

        $this->recordAndReplay(
            'redact-post-fields',
            fn (): int => $this->curlPostFields(self::$baseUrl.'/post', ['card' => '4111111111111111', 'password' => self::SECRET, 'other' => 'keep-me'])
        );

        $recording = $this->firstRecording('redact-post-fields');
        $postFields = $recording['request']['post_fields'];

        $this->assertSame('MASKED-CARD', $postFields['card']);
        $this->assertSame('chain', $postFields['scrubbed_by']);
        $this->assertSame('', $postFields['password']);
        $this->assertSame('keep-me', $postFields['other']);
    }

    /**
     * External sanitizer checklist: "Case-insensitive header matching" — same convention as
     * EncryptionPolicy's header handling.
     */
    public function testHeaderRuleMatchesResponseHeaderNamesCaseInsensitively(): void
    {
        $rules = RedactionRules::create()->header('content-type', null, Scope::RESPONSE);

        VCR::configure()->setStorageFactory(RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules));

        $this->recordAndReplay('redact-case-insensitive-header', fn (): int => $this->getWithHeaders(self::$baseUrl.'/get'));

        $recording = $this->firstRecording('redact-case-insensitive-header');
        $this->assertSame('', $recording['response']['headers']['Content-Type'] ?? null);
    }

    /**
     * Verification #7: the storage-factory reset happens via inheritance
     * (AbstractIntegrationTestCase::setUp() resets to `new YamlStorageFactory()` before every
     * test). This proves no RedactingStorageFactory configured by an earlier test in this class
     * leaks into a test that never configures one itself.
     */
    public function testDefaultStorageFactoryIsNotLeakedFromPreviousRedactionTests(): void
    {
        $this->recordAndReplay(
            'redact-no-leak',
            fn (): int => $this->postWithHeaders(self::$baseUrl.'/post', 'ping=pong', ['Authorization' => 'Bearer '.self::SECRET])
        );

        // No redaction rules were configured for this test, so the plain YamlStorageFactory that
        // AbstractIntegrationTestCase::setUp() resets to on every test must have been used — the
        // secret is expected to be in plaintext.
        $raw = $this->cassetteContents('redact-no-leak');
        $this->assertStringContainsString(self::SECRET, $raw);
    }
}
