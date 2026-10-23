<?php
namespace App\Services;

use App\Database\EncryptedConnection;
use App\Encryption\Envelope;
use Illuminate\Support\Facades\{DB, Storage};
use RuntimeException;
use Throwable;

class Backup {
 public static function create(): int {
  $connection = DB::connection();
  if (!$connection instanceof EncryptedConnection) throw new RuntimeException('Backup memerlukan database AES-256-GCM.');
  $path = 'backups/SIRUKUN-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(8)).'.sirukun';
  $id = DB::table('riwayat_backup')->insertGetId(['lokasi'=>$path,'status'=>'proses','mulai_pada'=>now()]);
  try {
   DB::table('riwayat_backup')->where('id',$id)->update(['status'=>'berhasil','selesai_pada'=>now()]);
   $encrypted = app(Envelope::class)->encrypt($connection->snapshot(), 'backup:v1');
   Storage::disk('local')->put($path, $encrypted);
   DB::table('riwayat_backup')->where('id',$id)->update(['checksum'=>hash('sha256',$encrypted)]);
   return $id;
  } catch (Throwable $e) {
   DB::table('riwayat_backup')->where('id',$id)->update(['status'=>'gagal','selesai_pada'=>now()]);
   throw $e;
  }
 }
}
