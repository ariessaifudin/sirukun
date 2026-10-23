<?php
namespace App\Encryption;

use Closure;
use Illuminate\Cache\Lock;
use Illuminate\Cache\RetrievesMultipleKeys;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use RuntimeException;

final class EncryptedCacheStore implements Store, LockProvider
{
    use RetrievesMultipleKeys;
    public function __construct(private string $root, private Envelope $crypto) { if (!is_dir($root)) mkdir($root, 0700, true); }
    private function path(string $key): string { return $this->root.'/'.hash('sha256', $key).'.gcm'; }
    private function synchronized(string $key, Closure $callback): mixed
    {
        $lock = fopen($this->path($key).'.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Cache tidak dapat dikunci.');
        try { return $callback(); } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    private function read(string $key): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) return null;
        $data = unserialize($this->crypto->decrypt(file_get_contents($path), 'cache:v1:'.$key));
        if ($data['expires'] !== 0 && $data['expires'] <= time()) { @unlink($path); return null; }
        return $data['value'];
    }
    private function write(string $key, mixed $value, int $seconds): bool
    {
        $bytes = $this->crypto->encrypt(serialize(['expires' => $seconds > 0 ? time() + $seconds : 0, 'value' => $value]), 'cache:v1:'.$key);
        if (file_put_contents($this->path($key), $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('Cache terenkripsi gagal ditulis.');
        return true;
    }
    public function get($key) { return $this->synchronized($key, fn() => $this->read($key)); }
    public function put($key, $value, $seconds) { return $this->synchronized($key, fn() => $seconds <= 0 ? $this->remove($key) : $this->write($key, $value, $seconds)); }
    public function add($key, $value, $seconds) { return $this->synchronized($key, fn() => $this->read($key) !== null ? false : $this->write($key, $value, $seconds)); }
    public function increment($key, $value = 1) { return $this->synchronized($key, function() use ($key, $value) {
        $path = $this->path($key);
        $current = $this->read($key);
        if ($current === null) return false;
        $data = unserialize($this->crypto->decrypt(file_get_contents($path), 'cache:v1:'.$key));
        $next = (int)$current + $value;
        $this->write($key, $next, $data['expires'] === 0 ? 0 : max(1, $data['expires'] - time()));
        return $next;
    }); }
    public function decrement($key, $value = 1) { return $this->increment($key, -$value); }
    public function forever($key, $value) { return $this->synchronized($key, fn() => $this->write($key, $value, 0)); }
    private function remove(string $key): bool { return !is_file($this->path($key)) || unlink($this->path($key)); }
    public function forget($key) { return $this->synchronized($key, fn() => $this->remove($key)); }
    public function compareForget(string $key, string $owner): bool { return $this->synchronized($key, fn() => $this->read($key) === $owner ? $this->remove($key) : false); }
    public function flush() { foreach (glob($this->root.'/*.gcm') as $path) @unlink($path); return true; }
    public function getPrefix() { return 'sirukun_gcm'; }
    public function lock($name, $seconds = 0, $owner = null) { return new EncryptedCacheLock($this, $name, $seconds, $owner); }
    public function restoreLock($name, $owner) { return $this->lock($name, 0, $owner); }
}

final class EncryptedCacheLock extends Lock
{
    public function __construct(private EncryptedCacheStore $store, $name, $seconds, $owner) { parent::__construct($name, $seconds, $owner); }
    public function acquire() { return $this->store->add('lock:'.$this->name, $this->owner, $this->seconds); }
    public function release() { return $this->store->compareForget('lock:'.$this->name, $this->owner); }
    public function forceRelease() { $this->store->forget('lock:'.$this->name); }
    protected function getCurrentOwner() { return $this->store->get('lock:'.$this->name); }
}
