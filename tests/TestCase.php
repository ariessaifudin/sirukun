<?php
namespace Tests;
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase {
 public function createApplication(){ $app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();if (getenv('SIRUKUN_TEST_ENCRYPTED') === '1') {
  \Illuminate\Support\Facades\DB::extend('test-gcm', function($config, $name) {
   $store = new \App\Database\Vault(new \PDO('sqlite::memory:'), app(\App\Encryption\Envelope::class), 'application-tests');
   return new \App\Database\EncryptedConnection($store, ['name'=>$name]);
  });
  config(['database.default'=>'test_gcm', 'database.connections.test_gcm'=>['driver'=>'test-gcm','database'=>':memory:']]);
 }return $app; }
}
