<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class Reminders {
 public static function run():int{$count=0;$roles=DB::table('peran')->pluck('kode','id');$users=DB::table('pengguna')->where('aktif',true)->whereNull('deleted_at')->get();
  foreach(['tamu_menginap','sewa_penghuni'] as $table){$isGuest=$table==='tamu_menginap';$q=DB::table($table.' as g')->join('hunian as h','h.id','=','g.hunian_id')->join('rt as r','r.id','=','h.rt_id')->whereNull('g.deleted_at')->whereNull($isGuest?'g.selesai_aktual':'g.tanggal_keluar_aktual')->select('g.*','h.rt_id','r.rw_id');
   if($isGuest)$q->where('g.rencana_selesai','<',now());else $q->where('g.tanggal_selesai','<=',today()->addDays(7)->toDateString());
   foreach($q->get() as $g){$due=$isGuest?$g->rencana_selesai:$g->tanggal_selesai;$message=$isGuest?'Tamu '.$g->nama.' melewati batas menginap '.$due.'.':'Masa sewa '.$g->nama.' berakhir pada '.$due.'.';foreach($users as $u){$role=$roles[$u->peran_id];if(!($role==='ADMINISTRATOR'||($role==='RT'&&$u->rt_id===$g->rt_id)||($role==='RW'&&$u->rw_id===$g->rw_id)))continue;$key=$table.':'.$g->id.':'.$due.':'.$u->id;
    $count+=DB::table('notifikasi')->insertOrIgnore(['penerima_id'=>$u->id,'tamu_id'=>$isGuest?$g->id:null,'sewa_id'=>$isGuest?null:$g->id,'kunci_peristiwa'=>$key,'jenis'=>$isGuest?'tamu_terlambat':'sewa_berakhir','pesan'=>$message,'dikirim_pada'=>now(),'created_at'=>now(),'updated_at'=>now()]);}
   }
  }return $count;
 }
}
