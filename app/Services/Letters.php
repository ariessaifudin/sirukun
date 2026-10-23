<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class Letters {
 public static function query(){
  $q=DB::table('permohonan_surat as p')->join('warga as w','w.id','=','p.warga_id')->join('jenis_surat as j','j.id','=','p.jenis_surat_id')->join('rt as r','r.id','=','p.rt_id')->leftJoin('surat_terbit as s','s.permohonan_id','=','p.id')->whereNull('p.deleted_at')->select('p.*','w.nama','w.nik','j.nama as jenis_nama','j.kode as jenis_kode','r.nomor as rt_nomor','s.id as surat_id','s.nomor_surat','s.dicabut_pada');
  $u=auth()->user();if(!$u->isAdmin())$q->whereIn('p.rt_id',$u->rtIds());if($u->roleCode()==='WARGA')$q->where('p.pemohon_id',$u->id);return $q;
 }
 public static function get(int $id):object{$p=self::query()->where('p.id',$id)->first();abort_unless($p,404);return $p;}
 public static function approve(int $id,string $status,?string $note):?int {
  $u=auth()->user();$stage=$u->roleCode();abort_unless($u->canApprove($stage),403,'Persetujuan hanya dilakukan oleh Ketua RT atau Ketua RW sesuai tahapnya.');$newPath=null;
  try{return DB::transaction(function()use($id,$u,$stage,$status,$note,&$newPath){
   $p=DB::table('permohonan_surat')->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first();abort_unless($p,404);Access::rt($p->rt_id);
   abort_unless($p->status===($stage==='RT'?'pending_rt':'pending_rw'),422,'Tahap permohonan sudah berubah. Muat ulang halaman.');
   DB::table('persetujuan_surat')->updateOrInsert(['permohonan_id'=>$id,'tahap'=>$stage,'revisi'=>$p->revisi],['pemeriksa_id'=>$u->id,'status'=>$status,'catatan'=>$note,'diproses_pada'=>now(),'created_at'=>now(),'updated_at'=>now()]);
   if($stage==='RT')DB::table('permohonan_surat')->where('id',$id)->update(['catatan_rt'=>$note,'status'=>$status==='approved'?'pending_rw':'pending_rt','updated_at'=>now()]);
   Audit::record('persetujuan_'.$stage,'permohonan_surat',$id,null,['status'=>$status,'catatan'=>$note]);
   if($stage!=='RW'||$status!=='approved')return null;
   $rtApproval=DB::table('persetujuan_surat')->where('permohonan_id',$id)->where('revisi',$p->revisi)->where('tahap','RT')->where('status','approved')->first();abort_unless($rtApproval,422,'Persetujuan RT belum lengkap.');
   $rt=DB::table('rt')->find($p->rt_id);$rw=DB::table('rw')->find($rt->rw_id);$w=DB::table('warga')->find($p->warga_id);$j=DB::table('jenis_surat')->find($p->jenis_surat_id);$kk=DB::table('kartu_keluarga')->find($w->kartu_keluarga_id);$h=$kk?DB::table('hunian')->find($kk->hunian_id):null;
   $year=(int)now()->format('Y');DB::table('penomoran_surat')->insertOrIgnore(['rw_id'=>$rw->id,'tahun'=>$year,'nomor_terakhir'=>0]);$counter=DB::table('penomoran_surat')->where('rw_id',$rw->id)->where('tahun',$year)->lockForUpdate()->first();$num=$counter->nomor_terakhir+1;
   DB::table('penomoran_surat')->where('rw_id',$rw->id)->where('tahun',$year)->update(['nomor_terakhir'=>$num]);$nomor=str_pad((string)$num,4,'0',STR_PAD_LEFT).'/RT'.$rt->nomor.'/RW'.$rw->kode.'/'.now()->format('m').'/'.$year;
   $token=bin2hex(random_bytes(32));$verify=rtrim(config('app.url'),'/').'/verifikasi/'.$token;
   $snapshot=['nama'=>$w->nama,'nik'=>$w->nik,'nomor_kk'=>$kk?->nomor_kk,'alamat'=>$h?->alamat,'rt'=>$rt->nomor,'rw'=>$rw->kode,'lingkungan'=>$rw->nama,'kelurahan'=>$rw->kelurahan,'kecamatan'=>$rw->kecamatan,'kota'=>$rw->kota,'jenis'=>$j->nama,'kode'=>$j->kode,'nomor'=>$nomor,'keperluan'=>$p->keperluan,'isian'=>json_decode($p->isian,true),'catatan_rt'=>$p->catatan_rt,'ketua_rt'=>DB::table('pengguna')->where('id',$rtApproval->pemeriksa_id)->value('nama'),'ketua_rw'=>$u->nama,'tanggal'=>now()->format('d/m/Y'),'verifikasi'=>$verify];
   $fileId=Files::pdf(LetterPdf::create($snapshot),'Surat-'.str_replace('/','-',$nomor).'.pdf');$newPath=DB::table('berkas')->where('id',$fileId)->value('path');
   $letterId=DB::table('surat_terbit')->insertGetId(['permohonan_id'=>$id,'rw_id'=>$rw->id,'berkas_id'=>$fileId,'penerbit_id'=>$u->id,'tahun'=>$year,'nomor_urut'=>$num,'nomor_surat'=>$nomor,'token_qr_hash'=>hash('sha256',$token),'snapshot'=>json_encode($snapshot),'terbit_pada'=>now(),'created_at'=>now(),'updated_at'=>now()]);
   DB::table('permohonan_surat')->where('id',$id)->update(['status'=>'terbit','updated_at'=>now()]);Audit::record('terbit','surat_terbit',$letterId,null,['nomor_surat'=>$nomor]);return $letterId;
  });}catch(\Throwable $e){if($newPath)Storage::disk('local')->delete($newPath);throw $e;}
 }
}
