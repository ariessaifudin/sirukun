<?php
namespace App\Database;

use App\Encryption\Envelope;
use PDO;
use RuntimeException;
use Throwable;

/** MySQL stores only authenticated ciphertext chunks, never application columns. */
final class Vault
{
    private bool $locked = false;
    private string $lockName;
    private bool $closed = false;

    public function __construct(private PDO $pdo, private Envelope $crypto, string $identity, private int $maxBytes = 16777216)
    {
        $this->lockName = 'sirukun-gcm-'.substr(hash('sha256', $identity), 0, 48);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $q = $pdo->prepare('SELECT GET_LOCK(?, 30)');
            $q->execute([$this->lockName]);
            if ((int)$q->fetchColumn() !== 1) throw new RuntimeException('Database sedang sibuk. Coba lagi.');
            $this->locked = true;
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if (array_diff($tables, ['sirukun_vault'])) {
                $this->close();
                throw new RuntimeException('Database masih berisi tabel lama. Jalankan migrasi ke database terenkripsi baru.');
            }
            $pdo->exec('CREATE TABLE IF NOT EXISTS sirukun_vault (slot INT UNSIGNED PRIMARY KEY, payload LONGBLOB NOT NULL) ENGINE=InnoDB');
        } else {
            // File-backed SQLite vault is only used by automated persistence tests.
            $pdo->exec('CREATE TABLE IF NOT EXISTS sirukun_vault (slot INTEGER PRIMARY KEY, payload BLOB NOT NULL)');
        }
    }

    public function read(): ?string
    {
        $rows = $this->pdo->query('SELECT slot, payload FROM sirukun_vault ORDER BY slot')->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!$rows) return null;
        if (!isset($rows[0])) throw new RuntimeException('Manifest database terenkripsi hilang.');
        $manifest = json_decode($this->crypto->decrypt($rows[0], 'vault:manifest:v1'), true, 512, JSON_THROW_ON_ERROR);
        $count = $manifest['count'] ?? -1;
        if (!is_int($count) || $count < 1 || count($rows) !== $count + 1) throw new RuntimeException('Potongan database tidak lengkap.');
        $payload = '';
        for ($i = 1; $i <= $count; $i++) {
            if (!isset($rows[$i])) throw new RuntimeException('Potongan database hilang.');
            $payload .= $this->crypto->decrypt($rows[$i], 'vault:'.$manifest['generation'].':'.$i.':'.$count);
            if (strlen($payload) > $this->maxBytes) throw new RuntimeException('Database melebihi batas ukuran.');
        }
        if (!hash_equals($manifest['sha256'], hash('sha256', $payload))) throw new RuntimeException('Integritas snapshot database gagal.');
        return $payload;
    }

    public function write(string $snapshot): void
    {
        if ($this->closed) throw new RuntimeException('Koneksi penyimpanan sudah ditutup.');
        if (strlen($snapshot) > $this->maxBytes) throw new RuntimeException('Database melebihi batas snapshot 16 MiB; penyimpanan dibatalkan.');
        $chunks = str_split($snapshot, 262144);
        $count = count($chunks);
        $generation = bin2hex(random_bytes(16));
        $manifest = json_encode(['count' => $count, 'generation' => $generation, 'sha256' => hash('sha256', $snapshot)], JSON_THROW_ON_ERROR);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM sirukun_vault');
            $insert = $this->pdo->prepare('INSERT INTO sirukun_vault (slot, payload) VALUES (?, ?)');
            $insert->execute([0, $this->crypto->encrypt($manifest, 'vault:manifest:v1')]);
            foreach ($chunks as $i => $chunk) $insert->execute([$i + 1, $this->crypto->encrypt($chunk, 'vault:'.$generation.':'.($i + 1).':'.$count)]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function close(): void
    {
        if ($this->locked) {
            $q = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $q->execute([$this->lockName]);
            $this->locked = false;
        }
        $this->closed = true;
    }

    public function __destruct() { try { $this->close(); } catch (Throwable) {} }
}
