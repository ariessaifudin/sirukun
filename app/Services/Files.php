<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class Files {
 public static function upload($file):int {
  $path=$file->store('dokumen','local');
  return DB::table('berkas')->insertGetId(['pengunggah_id'=>auth()->id(),'disk'=>'local','path'=>$path,'nama_asli'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'ukuran_byte'=>$file->getSize(),'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function pdf(string $bytes,string $filename):int {
  $path='surat/'.bin2hex(random_bytes(24)).'.pdf';Storage::disk('local')->put($path,$bytes);
  return DB::table('berkas')->insertGetId(['pengunggah_id'=>auth()->id(),'disk'=>'local','path'=>$path,'nama_asli'=>$filename,'mime_type'=>'application/pdf','ukuran_byte'=>strlen($bytes),'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function rule(bool $required=false):string{return ($required?'required':'nullable').'|file|mimes:jpg,jpeg,png,pdf|max:2048';}
}
