<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder {
 public function run():void {
  foreach(['WARGA'=>'Warga','RT'=>'RT','RW'=>'RW','ADMINISTRATOR'=>'Administrator'] as $kode=>$nama)DB::table('peran')->updateOrInsert(['kode'=>$kode],['nama'=>$nama,'created_at'=>now(),'updated_at'=>now()]);
  $menus=['dashboard'=>'Dashboard','warga'=>'Data warga','keluarga'=>'Kartu keluarga','hunian'=>'Data hunian','mutasi'=>'Mutasi penduduk','tamu'=>'Tamu menginap','sewa'=>'Penghuni kontrakan / kos','surat'=>'Pelayanan surat','arsip'=>'Arsip surat','laporan'=>'Laporan bulanan','notifikasi'=>'Notifikasi','pengguna'=>'Kelola pengguna','wilayah'=>'Wilayah RT / RW','menu'=>'Menu aplikasi','log'=>'Log aktivitas','backup'=>'Pencadangan'];
  $i=0;foreach($menus as $kode=>$nama){DB::table('menu')->updateOrInsert(['kode'=>$kode],['nama'=>$nama,'route'=>$kode,'urutan'=>++$i,'aktif'=>true,'created_at'=>now(),'updated_at'=>now()]);}
  foreach(DB::table('peran')->get() as $role)foreach(DB::table('menu')->get() as $menu){$admin=in_array($menu->kode,['pengguna','wilayah','menu','log','backup']);$citizen=in_array($menu->kode,['dashboard','keluarga','mutasi','tamu','sewa','surat','notifikasi']);$allowed=$role->kode==='ADMINISTRATOR'||(!$admin&&($role->kode!=='WARGA'||$citizen));DB::table('hak_akses_menu')->updateOrInsert(['peran_id'=>$role->id,'menu_id'=>$menu->id],['lihat'=>$allowed,'tambah'=>$allowed,'ubah'=>$allowed,'hapus'=>$role->kode==='ADMINISTRATOR']);}
  foreach(['KTP_KK'=>'Pengantar KTP / KK Baru','SKTM'=>'Surat Keterangan Tidak Mampu','DOMISILI'=>'Surat Keterangan Domisili','NIKAH'=>'Surat Pengantar Nikah','USAHA'=>'Surat Keterangan Usaha'] as $kode=>$nama)DB::table('jenis_surat')->updateOrInsert(['kode'=>$kode],['nama'=>$nama,'template'=>'surat','aktif'=>true,'created_at'=>now(),'updated_at'=>now()]);
 }
}
