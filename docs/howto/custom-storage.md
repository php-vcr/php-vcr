# Custom Storage Backend

> One-liner: implement `StorageFactoryInterface` to serialize cassettes anywhere — not just the bundled
> YAML/JSON files — and inject whatever dependency the backend needs (a database connection, a client, ...).
>
> **🆕 Since 1.12**

**On this page:** [In-memory storage](#in-memory-storage) · [Database-backed storage](#database-backed-storage) ·
[Supporting MODE_ALL](#supporting-mode_all) · [Migrating from setStorage()](#migrating-from-setstorage)

Use this when none of the built-in [storage backends](../reference/storage-backends.md) fit — for example,
cassettes need to live in a database rather than a file, or a test double needs to inject a connection the
storage itself doesn't know how to construct. Before 1.12, the only way to pick a storage was
`setStorage()`, which only ever accepted one of three fixed name strings (`yaml`/`json`/`blackhole`) for
exactly that reason: there's no way to hand a constructed dependency to a class-name string.
`setStorageFactory()` takes an object instead, so the factory can carry whatever dependency the storage
needs.

## In-memory storage

The smallest possible backend — no dependencies, storage lives in a plain PHP array. It shows the full
`\VCR\Storage\StorageInterface` contract: the five `\Iterator` methods (`current()`, `key()`, `next()`,
`rewind()`, `valid()`) plus `storeRecording()` and `isNew()`.

```php
namespace App\Storage;

use VCR\Storage\StorageInterface;

final class InMemoryStorage implements StorageInterface
{
    private int $position = 0;

    /**
     * @param array<int, array<string,mixed>> $recordings shared with the factory that created this storage
     */
    public function __construct(private array &$recordings)
    {
    }

    public function storeRecording(array $recording): void
    {
        $this->recordings[] = $recording;
    }

    public function isNew(): bool
    {
        return [] === $this->recordings;
    }

    public function current(): ?array
    {
        return $this->recordings[$this->position] ?? null;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->recordings[$this->position]);
    }
}
```

A `\VCR\Storage\StorageFactoryInterface` implementation only has to build one of these per cassette. Keeping the
array on the factory, keyed by cassette name, means every `insertCassette()` call for the same name inside one
process reuses the same records:

```php
namespace App\Storage;

use VCR\Storage\StorageFactoryInterface;
use VCR\Storage\StorageInterface;

final class InMemoryStorageFactory implements StorageFactoryInterface
{
    /** @var array<string, array<int, array<string,mixed>>> */
    private array $recordingsByCassette = [];

    public function create(string $cassettePath, string $cassetteName): StorageInterface
    {
        $this->recordingsByCassette[$cassetteName] ??= [];

        return new InMemoryStorage($this->recordingsByCassette[$cassetteName]);
    }
}
```

```php
\VCR\VCR::configure()->setStorageFactory(new \App\Storage\InMemoryStorageFactory());
```

> **📌 Note:** the array lives only as long as the PHP process does. That's fine for a test that records and
> replays within the same run, but it means recordings don't survive between separate invocations the way a
> file or database would — pick a backend that persists to disk or a database if you need cassettes to outlive
> the process that recorded them (see below).

## Database-backed storage

A storage backed by SQLite via `PDO` — the recordings persist across separate PHP processes, so a suite can
record once and replay in a later run, same as with the YAML/JSON backends. The factory receives the `\PDO`
connection as a constructor dependency and hands it, together with the cassette name, to each storage it
creates:

```php
namespace App\Storage;

use VCR\Storage\PurgeableStorageInterface;

final class PdoStorage implements PurgeableStorageInterface
{
    private int $position = 0;

    /** @var array<string,mixed>|null */
    private ?array $current = null;

    private bool $isNew;

    public function __construct(private \PDO $pdo, private string $cassette)
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS recordings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cassette TEXT NOT NULL,
                recording TEXT NOT NULL
            )'
        );

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM recordings WHERE cassette = :cassette');
        $statement->execute(['cassette' => $this->cassette]);
        $this->isNew = 0 === (int) $statement->fetchColumn();
    }

    public function storeRecording(array $recording): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO recordings (cassette, recording) VALUES (:cassette, :recording)'
        );
        $statement->execute([
            'cassette' => $this->cassette,
            'recording' => json_encode($recording),
        ]);
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function current(): ?array
    {
        return $this->current;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
        $this->loadCurrent();
    }

    public function rewind(): void
    {
        $this->position = 0;
        $this->loadCurrent();
    }

    public function valid(): bool
    {
        return null !== $this->current;
    }

    public function purge(): void
    {
        $statement = $this->pdo->prepare('DELETE FROM recordings WHERE cassette = :cassette');
        $statement->execute(['cassette' => $this->cassette]);

        $this->position = 0;
        $this->current = null;
        $this->isNew = true;
    }

    private function loadCurrent(): void
    {
        $statement = $this->pdo->prepare(
            'SELECT recording FROM recordings WHERE cassette = :cassette ORDER BY id LIMIT 1 OFFSET :offset'
        );
        $statement->bindValue('cassette', $this->cassette);
        $statement->bindValue('offset', $this->position, \PDO::PARAM_INT);
        $statement->execute();
        $recording = $statement->fetchColumn();
        $this->current = false !== $recording ? json_decode($recording, true) : null;
    }
}
```

```php
namespace App\Storage;

use VCR\Storage\StorageFactoryInterface;
use VCR\Storage\StorageInterface;

final class PdoStorageFactory implements StorageFactoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function create(string $cassettePath, string $cassetteName): StorageInterface
    {
        return new PdoStorage($this->pdo, $cassetteName);
    }
}
```

```php
$pdo = new \PDO('sqlite:'.__DIR__.'/cassettes.sqlite');

\VCR\VCR::configure()->setStorageFactory(new \App\Storage\PdoStorageFactory($pdo));
```

This is the case a class-name string can't cover: `PdoStorage` needs the `$pdo` connection at construction
time, and the factory is what carries that dependency from application setup to the moment php-vcr actually
creates the storage.

## Supporting `MODE_ALL`

[`MODE_ALL`](../guides/record-modes.md#all) purges the cassette on every `insertCassette()`, so the configured
storage must be able to reset itself. That requires implementing `\VCR\Storage\PurgeableStorageInterface`
(adds one method, `purge()`) instead of the plain `StorageInterface`.

`InMemoryStorage` above only implements `StorageInterface` — inserting a cassette under `MODE_ALL` with it
throws:

```text
LogicException: Storage "App\Storage\InMemoryStorage" does not support MODE_ALL: implement
PurgeableStorageInterface to enable purge on cassette insert.
```

`PdoStorage` already implements `PurgeableStorageInterface` and its `purge()` deletes every row for the
current cassette, so it works with `MODE_ALL` without any further changes.

## Migrating from `setStorage()`

`setStorage()`/`getStorage()` were the original, name-based way to select a storage — before 1.12 they only
ever supported the three built-in names. They're deprecated in favour of `setStorageFactory()`/`getStorageFactory()`
— see [Upgrading → Deprecations](../upgrading.md#deprecations) for the full mapping.

```php
// before
\VCR\VCR::configure()->setStorage('json');

// after
\VCR\VCR::configure()->setStorageFactory(new \VCR\Storage\JsonStorageFactory());
```

---
← [Custom matcher](custom-request-matcher.md) · Next: [Select library hooks](select-library-hooks.md) →
