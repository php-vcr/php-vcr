# Custom Redaction Rule

> One-liner: reach for a callback rule first, drop to a full `RedactionRuleInterface` implementation only when a
> rule needs its own reversibility contract, and to a custom storage decorator only when redaction itself isn't
> the right shape for the problem.
>
> **🆕 Since 1.13**

**On this page:** [Level 1: a callback rule](#level-1-a-callback-rule) ·
[Level 2: a custom RedactionRuleInterface](#level-2-a-custom-redactionruleinterface) ·
[Level 3: your own storage decorator](#level-3-your-own-storage-decorator)

[`RedactionRules`](../reference/storage-backends.md#redacting) covers header-, query-parameter-, post-field-,
host-, and whole-value redaction out of the box (see [Filter sensitive data](filter-sensitive-data.md)). Use
this page when none of that fits — a body format only your API uses, a JSON field buried inside it, or redaction
logic bespoke enough that a declarative rule isn't the right shape at all.

## Level 1: a callback rule

`body()` and `postFields()` accept an arbitrary callback and are the smallest extension point — no interface to
implement, just a function:

```php
$rules = \VCR\Storage\Redaction\RedactionRules::create()
    ->allowIrreversibleRequestRedaction()
    ->body(function (string $body): string {
        return (string) preg_replace('/"ssn":"\d{3}-\d{2}-\d{4}"/', '"ssn":"REDACTED"', $body);
    }, \VCR\Storage\Redaction\Scope::REQUEST);
```

A callback rule is always **irreversible** — the callback's transformation is arbitrary, so there is nothing to
invert on replay. `RedactionRules::add()` enforces that: registering a request-scoped irreversible rule throws
`MissingReplacementException` unless `allowIrreversibleRequestRedaction()` has already been called, and once it
has, the matcher the rule invalidates (`body`, here) has to be dropped — call
`enableRequestMatchers($rules->safeRequestMatchers())` rather than hand-picking the list yourself, so a rule
added later isn't silently left unaccounted for.

Response-scoped callbacks never need the opt-in — the response is never matched against, so there's no matcher
to invalidate:

```php
$rules->body(fn (string $body): string => 'scrubbed', \VCR\Storage\Redaction\Scope::RESPONSE);
```

## Level 2: a custom `RedactionRuleInterface`

Implement `\VCR\Storage\Redaction\RedactionRuleInterface` directly when a callback isn't enough — typically
because the rule needs to be **reversible** (so it doesn't force a matcher out of the picture) but a callback
can't express that, since `BodyCallbackRule`/`PostFieldsCallbackRule` are hard-coded irreversible. The contract:

```php
interface RedactionRuleInterface
{
    public function scope(): string;
    public function isReversible(): bool;
    public function affectedMatchers(): array;
    public function redact(array $recording): array;
    public function restore(array $recording): array;
}
```

`affectedMatchers()` is the contract that keeps `RedactionRules::safeRequestMatchers()`/
`invalidatedRequestMatchers()` honest.

`RedactionRules` calls it on **every** registered rule, whatever `isReversible()` says. Its return value only
*matters*, though, when `isReversible()` returns `false`: an irreversible rule is the only kind that can force a
matcher out of `safeRequestMatchers()`. A reversible rule returns `[]`, because `restore()` has already put the
original value back by the time the matchers run — there is nothing left for them to get wrong.

So, concretely:

- **`isReversible(): false`** — list every default matcher key (of the 8 in
  [Request Matchers](../reference/request-matchers.md)) that your `redact()` breaks for a request those matchers
  would otherwise compare correctly. Under-report and replay fails with nothing pointing back here; over-report
  and a matcher is dropped for no reason.
- **`isReversible(): true`** — return `[]`. Leaving a stale non-empty list on a rule that has become reversible
  costs a matcher that would have worked fine.

Do not verify this by eye. Write a test that runs a recording through your `redact()`, then your `restore()`, and
compares the result against a live request through the real `RequestMatcher` callbacks — the built-in rules do
exactly that, because an `affectedMatchers()` assertion only checks what a rule *claims* about itself.

The example below redacts a field *inside* a JSON request body — something none of the built-ins reach, since
`postField()` only targets `request.post_fields`, not arbitrary JSON. It follows the same shape
`HeaderRule`/`QueryParameterRule`/`PostFieldRule` use internally, which is worth copying rather than inventing:

1. The source resolves to the **real** secret — `\VCR\Storage\Redaction\SecretSource` wraps it and turns `getenv()`'s `false` for an unset variable into a `MissingSecretException` naming the placeholder, instead of a `TypeError`.
2. `redact()` writes a **placeholder** derived from the rule's own identity, never a random one, so re-recording the cassette produces byte-identical output rather than a spurious diff.
3. `restore()` resolves the source again and writes the real secret back, before the matchers run.
4. `redact()` — and only `redact()` — rejects a value that already carries the placeholder. Doing that check in the shared rewrite helper would make `restore()` throw on the placeholder it is there to replace.

```php
namespace App\Redaction;

use VCR\Storage\RecordingPath;
use VCR\Storage\Redaction\PlaceholderCollisionException;
use VCR\Storage\Redaction\RedactionRuleInterface;
use VCR\Storage\Redaction\Scope;
use VCR\Storage\Redaction\SecretSource;

final class JsonBodyFieldRule implements RedactionRuleInterface
{
    private SecretSource $source;

    private string $placeholder;

    public function __construct(private string $field, callable $source)
    {
        $this->placeholder = '<<REDACTED:JSON_BODY_FIELD:'.$field.'>>';
        $this->source = new SecretSource($this->placeholder, $source);
    }

    public function scope(): string
    {
        return Scope::REQUEST;
    }

    public function isReversible(): bool
    {
        return true;
    }

    public function affectedMatchers(): array
    {
        return [];
    }

    public function redact(array $recording): array
    {
        // Resolve here without using the result: a cassette redacted against a source that
        // cannot be resolved could never be restored, and that belongs at record time.
        $this->source->resolve($recording);

        $body = RecordingPath::get($recording, ['request', 'body']);

        if (\is_string($body) && str_contains($body, $this->placeholder)) {
            throw PlaceholderCollisionException::forPlaceholder($this->placeholder, 'request.body');
        }

        return $this->rewrite($recording, $this->placeholder);
    }

    public function restore(array $recording): array
    {
        return $this->rewrite($recording, $this->source->resolve($recording));
    }

    private function rewrite(array $recording, string $value): array
    {
        return RecordingPath::replace($recording, ['request', 'body'], function ($body) use ($value) {
            $decoded = json_decode((string) $body, true);

            if (!\is_array($decoded) || !\array_key_exists($this->field, $decoded)) {
                return $body;
            }

            $decoded[$this->field] = $value;

            return json_encode($decoded);
        });
    }
}
```

`\VCR\Storage\RecordingPath` is the helper the built-in rules use to read and write a specific field
or header without hand-rolling array traversal: `resolvePaths()` turns dot paths (`'request.body'`) and header
names into segment arrays, `replace()` rewrites the value found at a segment path (a no-op if any segment is
missing), and `get()` reads one back — all operating on the same `array<string,mixed>` shape a recording has on
the wire. `\VCR\Storage\Redaction\SecretSource` is the matching helper for the secret side: it accepts a
literal string or a callable (with or without a `$recording` parameter — it decides by reflection, once), and
`resolve()` either returns a non-empty string or throws `MissingSecretException`.

Register it like any other rule via `add()`, pointing the source at wherever the **real** value lives:

```php
\VCR\VCR::configure()->setStorageFactory(
    \VCR\Storage\RedactingStorageFactory::withRules(
        new \VCR\Storage\YamlStorageFactory(),
        \VCR\Storage\Redaction\RedactionRules::create()->add(
            new \App\Redaction\JsonBodyFieldRule('requestId', fn () => getenv('API_REQUEST_ID'))
        )
    )
);
```

This was verified directly: storing a recording whose JSON body carried a real, random-looking `requestId`
produced a cassette with `<<REDACTED:JSON_BODY_FIELD:requestId>>` in its place and the real value nowhere on
disk; reading that cassette back through `RedactingStorage` restored the real value, and the restored request
matched a live request carrying that same real value against **all 8** default matchers, unchanged. Pointing the
source at an unset environment variable failed with
`The replacement source for placeholder "<<REDACTED:JSON_BODY_FIELD:requestId>>" resolved to an empty value.`
rather than a type error.

## Level 3: your own storage decorator

`RedactingStorageFactory`/`RedactingStorage` are themselves just a `StorageFactoryInterface`/`StorageInterface`
decorator — reach past `RedactionRuleInterface` entirely and write your own when the redact-on-write/
restore-on-read shape doesn't fit. For example, tagging every recording with a sequence number as it's written,
independent of any single rule's redact/restore pair:

```php
namespace App\Storage;

use VCR\Storage\StorageInterface;

final class SequentialTaggingStorage implements StorageInterface
{
    private int $recordedCount = 0;

    public function __construct(private StorageInterface $storage)
    {
    }

    public function storeRecording(array $recording): void
    {
        // Tag the response, never the request: request fields feed the request matchers, so
        // adding anything there that a live replay request won't also carry breaks replay.
        $recording['response']['headers']['X-Recorded-Sequence'] = 'call-'.++$this->recordedCount;

        $this->storage->storeRecording($recording);
    }

    public function current(): ?array
    {
        return $this->storage->current();
    }

    public function key(): int
    {
        return $this->storage->key();
    }

    public function next(): void
    {
        $this->storage->next();
    }

    public function rewind(): void
    {
        $this->storage->rewind();
    }

    public function valid(): bool
    {
        return $this->storage->valid();
    }

    public function isNew(): bool
    {
        return $this->storage->isNew();
    }
}
```

Wrap it the same way `RedactingStorageFactory` wraps a factory — a small `StorageFactoryInterface` whose
`create()` calls the inner factory and passes the result into the decorator. This was verified directly:
recording and replaying through this decorator produced a cassette carrying the `X-Recorded-Sequence` response
header, with replay unaffected since nothing on the request side changed.

See [Custom storage backend](custom-storage.md) for the full `StorageFactoryInterface`/`StorageInterface`
contracts, including `PurgeableStorageInterface` for [`MODE_ALL`](../guides/record-modes.md#all) support, and
[Custom request matcher](custom-request-matcher.md) for the matcher side of the same extension pattern.

---
← [Custom matcher](custom-request-matcher.md) · Next: [Custom storage](custom-storage.md) →
