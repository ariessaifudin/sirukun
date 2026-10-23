<?php
namespace App\Providers;

use App\Database\{EncryptedConnection, Vault};
use App\Encryption\{Envelope, EncryptedCacheStore};
use App\Filesystem\EncryptedLocalAdapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\{Cache, DB, Storage};
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use PDO;

final class EncryptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Envelope::class, fn() => new Envelope((string)config('encryption.key')));
    }

    public function boot(): void
    {
        DB::extend('sirukun-gcm', function ($config, $name) {
            $backend = $config['backend'];
            $pdo = new PDO('mysql:host='.$backend['host'].';port='.$backend['port'].';dbname='.$backend['database'].';charset=utf8mb4',
                $backend['username'], $backend['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_PERSISTENT => false]);
            $vault = new Vault($pdo, app(Envelope::class), $backend['database'], config('encryption.max_snapshot_bytes'));
            return new EncryptedConnection($vault, ['name' => $name]);
        });
        Storage::extend('sirukun-gcm', function ($app, $config) {
            $adapter = new EncryptedLocalAdapter($config['root'], $app->make(Envelope::class));
            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
        Cache::extend('sirukun-gcm', fn($app) => Cache::repository(new EncryptedCacheStore(storage_path('framework/cache/gcm'), $app->make(Envelope::class))));
    }
}
