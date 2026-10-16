<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class Audit {
 public static function record(string $action,string $table,$id=null,$before=null,$after=null):void {
  $clean=function($value){if(!$value)return null;$v=(array)$value;foreach(['password','password_hash','token_qr_hash','token','remember_token','password_confirmation','current_password'] as $key)unset($v[$key]);return json_encode($v,JSON_UNESCAPED_UNICODE);};
  DB::table('log_aktivitas')->insert(['pengguna_id'=>auth()->id(),'aksi'=>$action,'tabel'=>$table,'record_id'=>$id,'sebelum'=>$clean($before),'sesudah'=>$clean($after),'ip_address'=>app()->runningInConsole()?null:request()->ip(),'terjadi_pada'=>now()]);
 }
}
