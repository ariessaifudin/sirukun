<?php
namespace App\Database;

use Closure;
use Illuminate\Database\SQLiteConnection;
use RuntimeException;
use Throwable;

/** SQL, constraints and search execute only in RAM. MySQL receives AES-GCM envelopes. */
final class EncryptedConnection extends SQLiteConnection
{
    private bool $failed = false;

    public function __construct(private Vault $vault, array $config = [])
    {
        $snapshot = $vault->read();
        parent::__construct($snapshot === null ? Snapshot::memory() : Snapshot::decode($snapshot), ':memory:', '', ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true] + $config);
    }

    private function persist(): void
    {
        try { $this->vault->write(Snapshot::encode($this->getPdo())); }
        catch (Throwable $e) { $this->failed = true; throw $e; }
    }

    private function mutate(Closure $callback): mixed
    {
        if ($this->failed) throw new RuntimeException('Penyimpanan gagal; muat ulang aplikasi sebelum melanjutkan.');
        if ($this->pretending()) return $callback();
        $own = $this->transactionLevel() === 0;
        if ($own) $this->beginTransaction();
        try {
            $result = $callback();
            if ($own) $this->commit();
            return $result;
        } catch (Throwable $e) {
            if ($own && $this->transactionLevel()) $this->rollBack();
            throw $e;
        }
    }

    public function statement($query, $bindings = []) { return $this->mutate(fn() => parent::statement($query, $bindings)); }
    public function affectingStatement($query, $bindings = []) { return $this->mutate(fn() => parent::affectingStatement($query, $bindings)); }
    public function unprepared($query) { return $this->mutate(fn() => parent::unprepared($query)); }

    public function commit()
    {
        // Flush uncommitted RAM state atomically before acknowledging the application commit.
        if ($this->transactionLevel() === 1) $this->persist();
        parent::commit();
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        // Laravel's trait commits PDO directly; route through our durable commit instead.
        $level = $this->transactionLevel();
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->transactionLevel() > $level) $this->rollBack($level);
            throw $e;
        }
    }

    public function snapshot(): string { return Snapshot::encode($this->getPdo()); }

    public function replaceSnapshot(string $snapshot): void
    {
        if ($this->transactionLevel()) throw new RuntimeException('Restore tidak dapat berjalan di dalam transaksi.');
        $pdo = Snapshot::decode($snapshot); // Validate schema and foreign keys before persistence.
        $this->vault->write($snapshot);
        $this->setPdo($pdo);
    }

    public function disconnect() { parent::disconnect(); $this->vault->close(); }
}
