<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class Backup {
 public static function create():int {
  abort_unless(DB::getDriverName()==='mysql',422,'Backup SQL ini digunakan pada MySQL/MariaDB.');$name='backups/SIRUKUN-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';$id=DB::table('riwayat_backup')->insertGetId(['lokasi'=>$name,'status'=>'proses','mulai_pada'=>now()]);$full=Storage::disk('local')->path($name);if(!is_dir(dirname($full)))mkdir(dirname($full),0750,true);$fp=fopen($full,'wb');if(!$fp)throw new \RuntimeException('Folder backup tidak dapat ditulis.');
  try{DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');DB::beginTransaction();fwrite($fp,"-- SIRUKUN backup ".now()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");$tables=\Illuminate\Support\Facades\Schema::getTableListing(null,false);foreach($tables as $table){$safe='`'.str_replace('`','``',$table).'`';$create=(array)DB::selectOne('SHOW CREATE TABLE '.$safe);fwrite($fp,"\nDROP TABLE IF EXISTS $safe;\n".array_values($create)[1].";\n");$cols=DB::select('SHOW COLUMNS FROM '.$safe);$names=[];foreach($cols as $col)if(!str_contains(strtoupper($col->Extra),'GENERATED'))$names[]=$col->Field;$columnList=implode(',',array_map(fn($x)=>'`'.str_replace('`','``',$x).'`',$names));foreach(DB::table($table)->select($names)->cursor() as $row){$values=[];foreach($names as $col)$values[]=$row->$col===null?'NULL':DB::getPdo()->quote((string)$row->$col);fwrite($fp,"INSERT INTO $safe ($columnList) VALUES (".implode(',',$values).");\n");}}fwrite($fp,"UPDATE `riwayat_backup` SET `status`='berhasil', `selesai_pada`=".DB::getPdo()->quote((string)now())." WHERE `id`=".(int)$id.";\nSET FOREIGN_KEY_CHECKS=1;\n");DB::commit();fclose($fp);DB::table('riwayat_backup')->where('id',$id)->update(['status'=>'berhasil','selesai_pada'=>now(),'checksum'=>hash_file('sha256',$full)]);return $id;
  }catch(\Throwable $e){if(DB::transactionLevel())DB::rollBack();fclose($fp);@unlink($full);DB::table('riwayat_backup')->where('id',$id)->update(['status'=>'gagal','selesai_pada'=>now()]);throw $e;}
 }
}
