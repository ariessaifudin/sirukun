<?php
namespace App\Encryption;

use RuntimeException;

/** Versioned binary AEAD envelope. No plaintext fallback, no implicit key generation. */
final class Envelope
{
    private const MAGIC = "SIRUKUN-GCM\x01";
    private string $key;

    public function __construct(string $encodedKey)
    {
        $key = str_starts_with($encodedKey, 'base64:')
            ? base64_decode(substr($encodedKey, 7), true) : false;
        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('DATA_ENCRYPTION_KEY harus base64: dengan tepat 32 byte.');
        }
        $this->key = $key;
    }

    public function encrypt(string $plaintext, string $context): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key,
            OPENSSL_RAW_DATA, $iv, $tag, self::MAGIC."\0".$context, 16);
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Enkripsi AES-256-GCM gagal.');
        }
        return self::MAGIC.$iv.$tag.$ciphertext;
    }

    public function decrypt(string $envelope, string $context): string
    {
        $offset = strlen(self::MAGIC);
        if (!str_starts_with($envelope, self::MAGIC) || strlen($envelope) < $offset + 28) {
            throw new RuntimeException('Format data terenkripsi tidak valid.');
        }
        $plaintext = openssl_decrypt(substr($envelope, $offset + 28), 'aes-256-gcm',
            $this->key, OPENSSL_RAW_DATA, substr($envelope, $offset, 12),
            substr($envelope, $offset + 12, 16), self::MAGIC."\0".$context);
        if ($plaintext === false) {
            throw new RuntimeException('Autentikasi data gagal: kunci salah atau data telah berubah.');
        }
        return $plaintext;
    }

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }
}
