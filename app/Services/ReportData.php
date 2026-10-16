<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class ReportData {
 public static function residents(){return DB::table('warga as w')->join('kartu_keluarga as k','k.id','=','w.kartu_keluarga_id')->join('hunian as h','h.id','=','k.hunian_id')->whereNull('w.deleted_at')->whereNull('k.deleted_at')->whereNull('h.deleted_at')->whereIn('h.rt_id',Access::ids());}
 public static function collect(string $period,?int $rtId=null):array{
  $from=Carbon::createFromFormat('!Y-m',$period)->startOfMonth();$to=$from->copy()->endOfMonth();$ids=Access::ids();if($rtId){Access::rt($rtId);$ids=[$rtId];}
  $w=self::residents()->whereIn('h.rt_id',$ids)->where('w.status','aktif');$total=(clone $w)->count();$kk=(clone $w)->distinct()->count('k.id');
  $mutasi=DB::table('mutasi as m')->join('warga as w','w.id','=','m.warga_id')->join('rt as r','r.id','=','m.rt_id')->whereNull('m.deleted_at')->whereIn('m.rt_id',$ids)->whereBetween('m.tanggal_peristiwa',[$from->toDateString(),$to->toDateString()])->select('m.*','w.nama','r.nomor as rt_nomor')->get();
  $letters=Letters::query()->whereIn('p.rt_id',$ids)->whereBetween('p.created_at',[$from,$to])->get();$guests=[];foreach(['tamu_menginap','sewa_penghuni'] as $table){$g=DB::table($table.' as g')->join('hunian as h','h.id','=','g.hunian_id')->join('rt as r','r.id','=','h.rt_id')->whereNull('g.deleted_at')->whereIn('h.rt_id',$ids);if($table==='tamu_menginap')$g->where('g.mulai','<=',$to)->where(function($q)use($from){$q->whereNull('g.selesai_aktual')->orWhere('g.selesai_aktual','>=',$from);});else $g->where('g.tanggal_masuk','<=',$to->toDateString())->where(function($q)use($from){$q->whereNull('g.tanggal_keluar_aktual')->orWhere('g.tanggal_keluar_aktual','>=',$from->toDateString());});foreach($g->select('g.*','h.alamat','r.nomor as rt_nomor')->get() as $row){$row->tipe=$table==='tamu_menginap'?'Tamu':'Sewa';$guests[]=$row;}}
  return ['period'=>$period,'from'=>$from,'to'=>$to,'rtId'=>$rtId,'total'=>$total,'kk'=>$kk,'mutasi'=>$mutasi,'letters'=>$letters,'guests'=>collect($guests)];
 }
}
