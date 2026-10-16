<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class Access {
 public static function ids():array{return auth()->user()->rtIds();}
 public static function rt(int $id):void{abort_unless(auth()->user()->isAdmin()||in_array($id,array_map('intval',self::ids()),true),403,'Data berada di luar wilayah Anda.');}
 public static function warga(int $id,bool $family=false):object {
  $w=DB::table('warga as w')->leftJoin('kartu_keluarga as k','k.id','=','w.kartu_keluarga_id')->leftJoin('hunian as h','h.id','=','k.hunian_id')->where('w.id',$id)->whereNull('w.deleted_at')->select('w.*','h.rt_id','h.alamat','k.nomor_kk')->first();abort_unless($w,404);
  $u=auth()->user();if($u->roleCode()==='WARGA'){$own=DB::table('warga')->find($u->warga_id);abort_unless($id===$u->warga_id||($family&&$own?->kartu_keluarga_id&&$own->kartu_keluarga_id===$w->kartu_keluarga_id),403);}else self::rt((int)$w->rt_id);return $w;
 }
 public static function hunian(int $id):object {
  $h=DB::table('hunian')->whereNull('deleted_at')->find($id);abort_unless($h,404);self::rt((int)$h->rt_id);
  if(auth()->user()->roleCode()==='WARGA'){$w=self::warga(auth()->user()->warga_id);abort_unless(DB::table('kartu_keluarga')->where('id',$w->kartu_keluarga_id)->value('hunian_id')===$id,403);}return $h;
 }
 public static function kk(int $id):object {$k=DB::table('kartu_keluarga')->whereNull('deleted_at')->find($id);abort_unless($k,404);self::hunian($k->hunian_id);return $k;}
 public static function menus(){return DB::table('menu as m')->join('hak_akses_menu as h','h.menu_id','=','m.id')->where('h.peran_id',auth()->user()->peran_id)->where('h.lihat',true)->where('m.aktif',true)->whereNull('m.deleted_at')->orderBy('m.urutan')->select('m.*')->get();}
 public static function menu(string $code,string $action='lihat'):void{
  $u=auth()->user();if($u->isAdmin())return;
  $ok=DB::table('menu as m')->join('hak_akses_menu as h','h.menu_id','=','m.id')->where('m.kode',$code)->where('h.peran_id',$u->peran_id)->where('m.aktif',true)->whereNull('m.deleted_at')->where('h.'.$action,true)->exists();abort_unless($ok,403,'Menu atau tindakan tidak diizinkan.');
 }
}
