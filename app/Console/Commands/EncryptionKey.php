<?php
namespace App\Console\Commands;

use App\Encryption\Envelope;
use Illuminate\Console\Command;

final class EncryptionKey extends Command
{
    protected $signature = 'sirukun:encryption-key';
    protected $description = 'Membuat DATA_ENCRYPTION_KEY sekali tanpa mengubah APP_KEY atau kunci yang ada';
    public function handle(): int
    {
        $path = base_path('.env');
        if (!is_file($path)) { $this->error('Salin .env.example ke .env terlebih dahulu.'); return self::FAILURE; }
        $content = file_get_contents($path);
        if (preg_match('/^DATA_ENCRYPTION_KEY=(.+)$/m', $content, $match) && trim($match[1], " \t\r\n\"'") !== '') {
            $this->error('Kunci sudah ada. Kunci tidak diubah untuk mencegah kehilangan data.'); return self::FAILURE;
        }
        $line = 'DATA_ENCRYPTION_KEY='.Envelope::generateKey();
        $content = preg_match('/^DATA_ENCRYPTION_KEY=.*$/m', $content) ? preg_replace('/^DATA_ENCRYPTION_KEY=.*$/m', $line, $content) : rtrim($content)."\n".$line."\n";
        $temporary = $path.'.gcm-tmp';
        if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $path)) { $this->error('Konfigurasi tidak dapat ditulis.'); return self::FAILURE; }
        @chmod($path, 0600);
        $this->info('Kunci data dibuat. Simpan salinan .env secara aman dan terpisah dari backup data.');
        return self::SUCCESS;
    }
}
