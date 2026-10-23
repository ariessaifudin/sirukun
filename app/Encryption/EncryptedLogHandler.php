<?php
namespace App\Encryption;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

final class EncryptedLogHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        $crypto = new Envelope((string)config('encryption.key'));
        $path = storage_path('logs/laravel.gcm.log');
        $line = base64_encode($crypto->encrypt((string)$record->formatted, 'log:v1'))."\n";
        if (file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) throw new \RuntimeException('Log terenkripsi tidak dapat ditulis.');
        @chmod($path, 0600);
    }
}
