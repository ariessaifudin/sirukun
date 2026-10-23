<?php
namespace App\Console\Commands;

use App\Database\EncryptedConnection;
use App\Encryption\Envelope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RestoreEncryptedBackup extends Command
{
    protected $signature = 'sirukun:restore-encrypted {file : Backup .sirukun hasil unduh aplikasi}';
    protected $description = 'Memulihkan backup AES-GCM ke database terenkripsi baru/kosong';
    public function handle(): int
    {
        if (!app()->isDownForMaintenance()) { $this->error('Jalankan php artisan down terlebih dahulu.'); return self::FAILURE; }
        try {
            $connection = DB::connection();
            if (!$connection instanceof EncryptedConnection || $connection->getSchemaBuilder()->getTableListing()) { $this->error('Konfigurasi .env harus menunjuk database encrypted baru/kosong.'); return self::FAILURE; }
            $snapshot = app(Envelope::class)->decrypt(file_get_contents($this->argument('file')), 'backup:v1');
            $connection->replaceSnapshot($snapshot);
            $this->info('Backup dipulihkan. Salin juga dokumen terenkripsi dengan path asal dan gunakan kunci yang sama.');
            return self::SUCCESS;
        } catch (Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
