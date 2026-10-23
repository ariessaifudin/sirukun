<?php
namespace App\Http\Middleware;

use App\Encryption\Envelope;
use Closure;

final class RequireEncryptedStorage
{
    public function handle($request, Closure $next)
    {
        if (!app()->runningUnitTests() && (config('database.default') !== 'encrypted' || !config('encryption.key'))) {
            return response('Pembaruan keamanan belum selesai. Administrator harus menjalankan migrasi AES-256-GCM sesuai PANDUAN_AES_GCM.md.', 503);
        }
        app(Envelope::class); // Validate key before sessions, authentication, cache, or queries.
        return $next($request);
    }
}
