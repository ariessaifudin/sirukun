<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\Access;
class FileController {
 public function download(int $id){$f=DB::table('berkas')->find($id);abort_unless($f,404);$u=auth()->user();$allowed=$u->isAdmin()||($u->roleCode()==='WARGA'&&$f->pengunggah_id===$u->id);
  if(!$allowed){$m=DB::table('mutasi as m')->leftJoin('mutasi_masuk as a','a.mutasi_id','=','m.id')->leftJoin('mutasi_lahir as l','l.mutasi_id','=','m.id')->leftJoin('mutasi_mati as d','d.mutasi_id','=','m.id')->whereNull('m.deleted_at')->where(function($q)use($id){$q->where('a.ktp_berkas_id',$id)->orWhere('a.kk_berkas_id',$id)->orWhere('l.surat_lahir_berkas_id',$id)->orWhere('d.surat_mati_berkas_id',$id);})->select('m.*')->first();if($m)$allowed=$u->roleCode()==='WARGA'?$m->pelapor_id===$u->id:in_array($m->rt_id,$u->rtIds());}
  if(!$allowed)foreach(['tamu_menginap','sewa_penghuni'] as $table){$g=DB::table($table.' as g')->join('hunian as h','h.id','=','g.hunian_id')->where('g.identitas_berkas_id',$id)->whereNull('g.deleted_at')->select('g.*','h.rt_id')->first();if($g)$allowed=$u->roleCode()==='WARGA'?$g->pelapor_id===$u->id:in_array($g->rt_id,$u->rtIds());}
  if(!$allowed){$s=DB::table('surat_terbit as s')->join('permohonan_surat as p','p.id','=','s.permohonan_id')->where('s.berkas_id',$id)->whereNull('p.deleted_at')->select('p.*')->first();if($s)$allowed=$u->roleCode()==='WARGA'?$s->pemohon_id===$u->id:in_array($s->rt_id,$u->rtIds());}
  abort_unless($allowed,403);abort_unless(Storage::disk('local')->exists($f->path),404,'Berkas tidak ditemukan.');return Storage::disk('local')->download($f->path,basename($f->nama_asli),['Content-Type'=>$f->mime_type,'Cache-Control'=>'no-store, private']);
 }
}
