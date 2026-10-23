<?php
/** Run only against a local disposable database server; creates/drops a random test DB. */
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Database\{EncryptedConnection, Vault};
use App\Encryption\Envelope;

$dsn = getenv('SIRUKUN_MYSQL_TEST_DSN');
if (!$dsn) { echo "SKIP: set SIRUKUN_MYSQL_TEST_DSN (server DSN without dbname).\n"; exit(0); }
$user = getenv('SIRUKUN_MYSQL_TEST_USER') ?: 'root';
$password = getenv('SIRUKUN_MYSQL_TEST_PASSWORD') ?: '';
$connect = fn(string $database = '') => new PDO($dsn.($database ? ';dbname='.$database : ''), $user, $password,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$open = fn(string $database, string $key) => new EncryptedConnection(new Vault($connect($database), new Envelope($key), $database));

if (($argv[1] ?? '') === '--worker') {
    $c = $open($argv[2], getenv('SIRUKUN_TEST_KEY'));
    for ($i = 0; $i < 3; $i++) $c->transaction(function() use ($c) {
        $value = $c->table('counter')->where('id',1)->value('value');
        usleep(10000);
        $c->table('counter')->where('id',1)->update(['value'=>$value+1]);
    });
    $c->disconnect(); exit(0);
}

$database = 'sirukun_crypto_test_'.bin2hex(random_bytes(8));
$server = $connect(); $server->exec('CREATE DATABASE `'.$database.'`');
$check = function(bool $condition, string $message) { if (!$condition) throw new RuntimeException($message); };
try {
    $key = Envelope::generateKey(); $c = $open($database, $key);
    $c->statement('CREATE TABLE counter (id integer primary key, value integer, secret text)');
    $c->table('counter')->insert(['id'=>1,'value'=>0,'secret'=>'NIK-3174000000000001-RAHASIA']);
    $c->disconnect(); unset($c);
    $physical = $connect($database);
    $check($physical->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) === ['sirukun_vault'], 'Unexpected plaintext tables');
    foreach ($physical->query('SELECT payload FROM sirukun_vault') as $row) $check(!str_contains($row[0], 'RAHASIA'), 'Plaintext value at rest');
    $processes = [];
    foreach ([1, 2] as $worker) {
        $proc = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __FILE__, '--worker', $database],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, ['SIRUKUN_MYSQL_TEST_DSN'=>$dsn,'SIRUKUN_MYSQL_TEST_USER'=>$user,'SIRUKUN_MYSQL_TEST_PASSWORD'=>$password,'SIRUKUN_TEST_KEY'=>$key] + getenv());
        $processes[] = [$proc, $pipes];
    }
    foreach ($processes as [$proc, $pipes]) {
        fclose($pipes[0]); $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $check(proc_close($proc) === 0, 'Concurrent writer failed: '.$output);
    }
    $c = $open($database, $key);
    $check($c->table('counter')->value('value') === 6, 'Lost update between concurrent writers');
    $c->disconnect(); unset($c);
    try { $open($database, Envelope::generateKey()); throw new LogicException('Wrong key accepted'); } catch (RuntimeException $e) { $check(!($e instanceof LogicException), $e->getMessage()); }
    $raw = $physical->query('SELECT payload FROM sirukun_vault WHERE slot=1')->fetchColumn();
    $raw[strlen($raw)-1] = chr(ord($raw[strlen($raw)-1]) ^ 1);
    $physical->prepare('UPDATE sirukun_vault SET payload=? WHERE slot=1')->execute([$raw]);
    try { $open($database, $key); throw new LogicException('Tampering accepted'); } catch (RuntimeException $e) { $check(!($e instanceof LogicException), $e->getMessage()); }
    echo "PASS: MariaDB ciphertext, durable reopen, two concurrent writers, wrong-key and tampering rejection.\n";
} finally { $server->exec('DROP DATABASE `'.$database.'`'); }
