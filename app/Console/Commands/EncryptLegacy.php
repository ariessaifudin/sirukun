<?php
namespace App\Console\Commands;

use App\Database\{EncryptedConnection, Snapshot};
use App\Encryption\Envelope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, DB, Storage};
use PDO;
use RuntimeException;
use Throwable;

final class EncryptLegacy extends Command
{
    protected $signature = 'sirukun:encrypt-legacy {--target= : Database baru untuk penyimpanan terenkripsi}';
    protected $description = 'Menyalin dan memverifikasi database lama ke database AES-GCM baru, lalu mengenkripsi berkas lama';

    public function handle(): int
    {
        if (!app()->isDownForMaintenance()) { $this->error('Jalankan php artisan down terlebih dahulu dan hentikan scheduler/Apache selama migrasi.'); return self::FAILURE; }
        $target = (string)$this->option('target');
        $source = (string)config('database.connections.mysql.database');
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $target) || $target === $source) { $this->error('Gunakan --target dengan nama database baru yang berbeda.'); return self::FAILURE; }
        try {
            $crypto = app(Envelope::class);
            $legacy = DB::connection('mysql');
            $src = $legacy->getPdo();
            $src->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $src->beginTransaction();
            $allTables = $src->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $src->exec('CREATE DATABASE IF NOT EXISTS `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            // CREATE DATABASE implicitly commits MySQL transactions; restart snapshot afterwards.
            if (!$src->inTransaction()) $src->beginTransaction();
            $config = config('database.connections.encrypted');
            $config['backend']['database'] = $target;
            config(['database.connections.encrypted' => $config]);
            DB::purge('encrypted');
            $connection = DB::connection('encrypted');
            if (!$connection instanceof EncryptedConnection || $connection->getSchemaBuilder()->getTableListing()) throw new RuntimeException('Database tujuan harus baru/kosong. Jika percobaan sebelumnya gagal, gunakan nama tujuan baru.');
            if (Artisan::call('migrate', ['--database'=>'encrypted','--force'=>true]) !== 0) throw new RuntimeException('Pembuatan skema terenkripsi gagal.');
            $dst = $connection->getPdo();
            $expected = $connection->getSchemaBuilder()->getTableListing(null, false);
            if (array_diff($allTables, $expected) || array_diff($expected, $allTables)) throw new RuntimeException('Skema sumber tidak sesuai versi SIRUKUN; tidak ada data sumber yang diubah.');
            $counts = [];
            $dst->exec('PRAGMA foreign_keys=OFF');
            $connection->transaction(function () use ($src, $dst, $expected, &$counts) {
                foreach ($expected as $table) {
                    if ($table === 'migrations') continue;
                    $columns = $src->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_OBJ);
                    $names = array_map(fn($c) => $c->Field, array_filter($columns, fn($c) => !str_contains(strtoupper($c->Extra), 'GENERATED')));
                    $rows = $src->query('SELECT '.implode(',', array_map(fn($c) => '`'.$c.'`', $names)).' FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
                    Snapshot::insertRows($dst, $table, $rows);
                    $roundtrip = $dst->query('SELECT '.implode(',', array_map(Snapshot::quote(...), $names)).' FROM '.Snapshot::quote($table))->fetchAll(PDO::FETCH_ASSOC);
                    $canonical = function(array $items) { $items = array_map(fn($row) => json_encode(array_map(fn($v) => $v === null ? null : (string)$v, $row), JSON_THROW_ON_ERROR), $items); sort($items); return hash('sha256', implode("\n", $items)); };
                    if (count($rows) !== count($roundtrip) || !hash_equals($canonical($rows), $canonical($roundtrip))) throw new RuntimeException('Verifikasi salinan tabel gagal: '.$table);
                    $counts[$table] = count($rows);
                }
                if ($dst->query('PRAGMA foreign_key_check')->fetch()) throw new RuntimeException('Validasi relasi migrasi gagal.');
            });
            $dst->exec('PRAGMA foreign_keys=ON');
            $src->commit();
            // Reopen ciphertext from physical storage before declaring the database copy valid.
            DB::purge('encrypted');
            $verified = DB::connection('encrypted');
            foreach ($counts as $table => $count) if ($verified->table($table)->count() !== $count) throw new RuntimeException('Verifikasi database terenkripsi gagal.');
            DB::setDefaultConnection('encrypted');
            if (Artisan::call('sirukun:encrypt-files') !== 0) throw new RuntimeException('Enkripsi berkas belum selesai. Jangan aktifkan aplikasi. Jalankan sirukun:encrypt-files lagi.');
            $this->line(Artisan::output());
            $this->info('Salinan database terverifikasi: '.$target.'. Database asal TIDAK dihapus.');
            $this->warn('Ubah .env: DB_CONNECTION=encrypted dan DB_DATABASE='.$target.'. Bersihkan config cache, periksa data, arsipkan database asal secara terenkripsi lalu hapus database/backup plaintext lama. Jangan aktifkan layanan sebelum langkah ini selesai.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            if (isset($src) && $src->inTransaction()) $src->rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
