<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\{Backup,Audit};
class AdminController {
 public function logs(Request $r){$q=DB::table('log_aktivitas as l')->leftJoin('pengguna as u','u.id','=','l.pengguna_id')->select('l.*','u.nama');if($r->filled('q'))$q->where('l.aksi','like','%'.mb_substr($r->string('q'),0,100).'%');return view('logs',['rows'=>$q->orderByDesc('l.id')->paginate(25)->withQueryString()]);}
 public function backups(){return view('backups',['rows'=>DB::table('riwayat_backup')->orderByDesc('id')->paginate(15)]);}
 public function backup(){set_time_limit(300);$id=Backup::create();Audit::record('buat_backup','riwayat_backup',$id);return back()->with('success','Backup database berhasil dibuat. Simpan salinannya di perangkat terpisah.');}
 public function downloadBackup(int $id){$b=DB::table('riwayat_backup')->find($id);abort_unless($b&&$b->status==='berhasil',404);abort_unless(Storage::disk('local')->exists($b->lokasi),404);Audit::record('unduh_backup','riwayat_backup',$id);$bytes=Storage::disk('local')->get($b->lokasi);$filename=basename($b->lokasi);if(str_ends_with($filename,'.sql')){$bytes=app(\App\Encryption\Envelope::class)->encrypt($bytes,'legacy-sql-backup:v1');$filename.='.gcm';}return response($bytes,200,['Content-Type'=>'application/octet-stream','Content-Disposition'=>'attachment; filename="'.$filename.'"','Cache-Control'=>'no-store, private']);}
}
