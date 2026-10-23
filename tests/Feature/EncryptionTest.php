<?php
namespace Tests\Feature;

use App\Database\{EncryptedConnection, Snapshot, Vault};
use App\Encryption\{Envelope, EncryptedCacheStore};
use App\Filesystem\EncryptedLocalAdapter;
use Illuminate\Encryption\Encrypter;
use League\Flysystem\{Filesystem, Config};
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncryptionTest extends TestCase
{
    private Envelope $crypto;
    private string $root;
    protected function setUp(): void
    {
        $this->crypto = new Envelope(Envelope::generateKey());
        $this->root = sys_get_temp_dir().'/sirukun-gcm-tests-'.bin2hex(random_bytes(12));
        mkdir($this->root, 0700);
    }
    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }
    private function physical(): PDO { return new PDO('sqlite:'.$this->root.'/vault.sqlite', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); }
    private function connection(?Envelope $crypto = null, int $limit = 16777216): EncryptedConnection
    {
        return new EncryptedConnection(new Vault($this->physical(), $crypto ?? $this->crypto, 'test', $limit));
    }
    public function test_randomized_roundtrip_including_null_bytes_and_empty_string(): void
    {
        foreach (['', 'Nama warga — 日本語', "\x00\xffPDF\n", str_repeat('a', 200000)] as $value) {
            $first = $this->crypto->encrypt($value, 'test');
            $second = $this->crypto->encrypt($value, 'test');
            self::assertNotSame($first, $second);
            self::assertSame($value, $this->crypto->decrypt($first, 'test'));
        }
    }
    public function test_tampering_iv_tag_ciphertext_context_and_wrong_key_are_rejected(): void
    {
        $ciphertext = $this->crypto->encrypt('sensitive', 'test');
        foreach ([0, 12, 25, strlen($ciphertext)-1] as $offset) {
            $bad = $ciphertext; $bad[$offset] = chr(ord($bad[$offset]) ^ 1);
            try { $this->crypto->decrypt($bad, 'test'); self::fail('Tampering accepted'); } catch (RuntimeException) { self::assertTrue(true); }
        }
        foreach ([[$this->crypto, 'other'], [new Envelope(Envelope::generateKey()), 'test']] as [$crypto, $context]) {
            try { $crypto->decrypt($ciphertext, $context); self::fail('Wrong key/context accepted'); } catch (RuntimeException) { self::assertTrue(true); }
        }
    }
    public function test_invalid_key_and_plaintext_are_never_accepted(): void
    {
        foreach (['', 'base64:invalid', 'base64:'.base64_encode(random_bytes(16))] as $key) {
            try { new Envelope($key); self::fail('Invalid key accepted'); } catch (RuntimeException) { self::assertTrue(true); }
        }
        $this->expectException(RuntimeException::class); $this->crypto->decrypt('plaintext', 'test');
    }
    public function test_every_database_value_is_ciphertext_on_disk_and_readable_after_reopen(): void
    {
        $connection = $this->connection();
        $connection->statement('CREATE TABLE citizens (id integer primary key autoincrement, name text unique, status text, born text, extra text)');
        $id = $connection->table('citizens')->insertGetId(['name'=>'RAHASIA-3174000000000001', 'status'=>'aktif', 'born'=>'2000-01-02','extra'=>'{"alamat":"Jl. Rahasia"}']);
        self::assertSame(1, $id);
        $rows = $this->physical()->query('SELECT payload FROM sirukun_vault')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $row) {
            self::assertStringNotContainsString('RAHASIA', $row);
            self::assertStringNotContainsString('aktif', $row);
            self::assertStringNotContainsString('2000-01-02', $row);
        }
        self::assertStringNotContainsString('RAHASIA', file_get_contents($this->root.'/vault.sqlite'));
        $again = $this->connection();
        self::assertSame('RAHASIA-3174000000000001', $again->table('citizens')->where('status','aktif')->where('name','like','%3174%')->first()->name);
        self::assertSame(2, $again->table('citizens')->insertGetId(['name'=>'Second']));
    }
    public function test_transactions_and_nested_rollbacks_are_durable(): void
    {
        $c = $this->connection(); $c->statement('CREATE TABLE counter (id integer primary key, value integer)');
        $c->table('counter')->insert(['id'=>1,'value'=>0]);
        try { $c->transaction(function() use ($c) { $c->table('counter')->where('id',1)->update(['value'=>9]); throw new RuntimeException('rollback'); }); } catch (RuntimeException) {}
        self::assertSame(0, $this->connection()->table('counter')->value('value'));
        $c->transaction(function() use ($c) {
            $c->table('counter')->where('id',1)->update(['value'=>1]);
            try { $c->transaction(function() use ($c) { $c->table('counter')->where('id',1)->update(['value'=>2]); throw new RuntimeException('rollback nested'); }); } catch (RuntimeException) {}
        });
        self::assertSame(1, $this->connection()->table('counter')->value('value'));
    }
    public function test_size_limit_fails_without_overwriting_durable_data(): void
    {
        $c = $this->connection(null, 1500); $c->statement('CREATE TABLE small (value text)');
        try { $c->table('small')->insert(['value'=>str_repeat('x',3000)]); self::fail('Limit ignored'); } catch (RuntimeException) {}
        self::assertSame(0, $this->connection()->table('small')->count());
    }
    public function test_missing_chunk_and_wrong_key_never_open_a_new_empty_database(): void
    {
        $c = $this->connection(); $c->statement('CREATE TABLE a (value text)');
        try { $this->connection(new Envelope(Envelope::generateKey())); self::fail('Wrong key opened DB'); } catch (RuntimeException) { self::assertTrue(true); }
        $this->physical()->exec('DELETE FROM sirukun_vault WHERE slot=1');
        $this->expectException(RuntimeException::class); $this->connection();
    }
    public function test_multichunk_snapshots_and_chunk_substitution_are_authenticated(): void
    {
        $c = $this->connection(); $c->statement('CREATE TABLE large (value text)');
        $c->table('large')->insert(['value'=>str_repeat('Z',600000)]);
        self::assertSame(600000, strlen($this->connection()->table('large')->value('value')));
        $pdo = $this->physical(); $old = $pdo->query('SELECT payload FROM sirukun_vault WHERE slot=1')->fetchColumn();
        $c->table('large')->update(['value'=>str_repeat('Y',600000)]);
        $pdo->prepare('UPDATE sirukun_vault SET payload=? WHERE slot=1')->execute([$old]);
        $this->expectException(RuntimeException::class); $this->connection();
    }
    public function test_files_stream_copy_move_and_metadata_do_not_expose_plaintext(): void
    {
        $adapter = new EncryptedLocalAdapter($this->root, $this->crypto); $fs = new Filesystem($adapter);
        $bytes = "%PDF-1.4\nNIK RAHASIA 3174000000000001";
        $stream = fopen('php://memory','w+b'); fwrite($stream,$bytes); rewind($stream);
        $fs->writeStream('document.pdf', $stream); fclose($stream);
        self::assertStringNotContainsString('RAHASIA', file_get_contents($this->root.'/document.pdf'));
        self::assertSame($bytes, $fs->read('document.pdf')); self::assertSame(strlen($bytes), $fs->fileSize('document.pdf'));
        $fs->copy('document.pdf','copy.pdf'); $fs->move('copy.pdf','moved.pdf');
        $stream = $fs->readStream('moved.pdf'); self::assertSame($bytes, stream_get_contents($stream)); fclose($stream);
        // Moving raw ciphertext to a different path must not bypass AAD binding.
        copy($this->root.'/document.pdf', $this->root.'/forged.pdf');
        $this->expectException(RuntimeException::class); $fs->read('forged.pdf');
    }
    public function test_cache_values_rate_limit_counters_and_lock_owners_are_encrypted(): void
    {
        $store = new EncryptedCacheStore($this->root, $this->crypto);
        $store->put('private', 'RAHASIA-CACHE', 60); self::assertSame('RAHASIA-CACHE', $store->get('private'));
        $store->put('attempts', 1, 60); self::assertSame(2,$store->increment('attempts'));
        self::assertFalse($store->add('attempts',9,60));
        $lock = $store->lock('scheduler', 60, 'RAHASIA-OWNER'); self::assertTrue($lock->get());
        self::assertFalse($store->lock('scheduler',60,'other')->get()); self::assertTrue($lock->release());
        foreach (glob($this->root.'/*.gcm') as $path) self::assertStringNotContainsString('RAHASIA',file_get_contents($path));
    }
    public function test_laravel_gcm_session_payload_is_authenticated(): void
    {
        $encrypter = new Encrypter(random_bytes(32), 'AES-256-GCM');
        $ciphertext = $encrypter->encryptString('RAHASIA-SESSION');
        self::assertSame('RAHASIA-SESSION',$encrypter->decryptString($ciphertext));
        self::assertSame(16, strlen(base64_decode(json_decode(base64_decode($ciphertext),true)['tag'])));
    }
}
