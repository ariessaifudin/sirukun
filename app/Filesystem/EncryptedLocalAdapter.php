<?php
namespace App\Filesystem;

use App\Encryption\Envelope;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

final class EncryptedLocalAdapter extends LocalFilesystemAdapter
{
    public function __construct(private string $root, private Envelope $crypto) { parent::__construct($root); }
    public function write(string $path, string $contents, Config $config): void
    {
        // Same-directory atomic replacement contains ciphertext from its first byte.
        $target = rtrim($this->root, '/\\').DIRECTORY_SEPARATOR.$path;
        $this->ensureDirectoryExists(dirname($target), 0700);
        $tmp = $target.'.'.bin2hex(random_bytes(12)).'.gcm-tmp';
        try {
            $bytes = $this->crypto->encrypt($contents, 'file:v1:'.$path);
            if (file_put_contents($tmp, $bytes, LOCK_EX) !== strlen($bytes) || !rename($tmp, $target)) throw new RuntimeException('Berkas terenkripsi tidak dapat disimpan.');
            @chmod($target, 0600);
        } finally { if (is_file($tmp)) @unlink($tmp); }
    }
    public function writeStream(string $path, $contents, Config $config): void
    {
        $bytes = stream_get_contents($contents);
        if ($bytes === false) throw new RuntimeException('Pembacaan unggahan gagal.');
        $this->write($path, $bytes, $config);
    }
    public function read(string $path): string { return $this->crypto->decrypt(parent::read($path), 'file:v1:'.$path); }
    public function readStream(string $path)
    {
        $stream = fopen('php://memory', 'w+b');
        fwrite($stream, $this->read($path)); rewind($stream); return $stream;
    }
    public function fileSize(string $path): FileAttributes { return new FileAttributes($path, strlen($this->read($path))); }
    public function mimeType(string $path): FileAttributes { return new FileAttributes($path, null, null, null, (new \finfo(FILEINFO_MIME_TYPE))->buffer($this->read($path))); }
    public function copy(string $source, string $destination, Config $config): void { $this->write($destination, $this->read($source), $config); }
    public function move(string $source, string $destination, Config $config): void { $this->copy($source, $destination, $config); $this->delete($source); }
}
