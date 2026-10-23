<?php
namespace App\Console\Commands;

use App\Encryption\Envelope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class EncryptFiles extends Command
{
    protected $signature = 'sirukun:encrypt-files';
    protected $description = 'Mengenkripsi dokumen/backup lama dan membersihkan sesi/cache plaintext saat maintenance';
    public function handle(): int
    {
        if (!app()->isDownForMaintenance()) { $this->error('Jalankan php artisan down dan hentikan Apache/scheduler dahulu.'); return self::FAILURE; }
        try {
            $crypto = app(Envelope::class);
            $root = storage_path('app/private');
            $count = 0;
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!$file->isFile() || $file->getFilename() === '.gitignore') continue;
                if ($file->isLink()) throw new RuntimeException('Symlink pada dokumen tidak diizinkan.');
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                $bytes = file_get_contents($file->getPathname());
                if (str_starts_with($bytes, "SIRUKUN-GCM\x01")) { $crypto->decrypt($bytes, 'file:v1:'.$relative); continue; }
                Storage::disk('local')->put($relative, $bytes);
                if (!hash_equals(hash('sha256', $bytes), hash('sha256', Storage::disk('local')->get($relative)))) throw new RuntimeException('Verifikasi berkas gagal.');
                $count++;
            }
            $log = storage_path('logs/laravel.log');
            if (is_file($log)) {
                Storage::disk('local')->put('legacy/laravel.log', file_get_contents($log));
                if (!unlink($log)) throw new RuntimeException('Log lama tidak dapat dihapus.');
            }
            foreach (['framework/sessions', 'framework/cache/data', 'framework/testing'] as $dir) {
                $path = storage_path($dir);
                if (!is_dir($path)) continue;
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ($file->isFile() && $file->getFilename() !== '.gitignore' && !unlink($file->getPathname())) throw new RuntimeException('Data sementara lama tidak dapat dibersihkan.');
                }
            }
            $this->info($count.' berkas lama dienkripsi. Sesi/cache lama dibersihkan; pengguna harus masuk kembali.');
            return self::SUCCESS;
        } catch (Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
