<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,DashboardController,RecordController,MutationController,GuestController,LetterController,ReportController,FileController,AdminController};
Route::get('/masuk',[AuthController::class,'login'])->name('login');
Route::post('/masuk',[AuthController::class,'authenticate'])->middleware('throttle:20,1');
Route::get('/verifikasi/{token}',[LetterController::class,'verify'])->middleware('throttle:30,1')->name('verify');
Route::middleware('auth')->group(function(){
 Route::get('/',[DashboardController::class,'index']);Route::post('/keluar',[AuthController::class,'logout']);
 Route::get('/profil',[AuthController::class,'profile']);Route::post('/profil/password',[AuthController::class,'password']);
 Route::get('/data/{kind}/baru',[RecordController::class,'form']);Route::post('/data/{kind}/baru',[RecordController::class,'save']);
 Route::get('/data/{kind}/{id}/ubah',[RecordController::class,'form'])->whereNumber('id');Route::post('/data/{kind}/{id}/ubah',[RecordController::class,'save'])->whereNumber('id');
 Route::post('/data/{kind}/{id}/hapus',[RecordController::class,'destroy'])->middleware('role:ADMINISTRATOR')->whereNumber('id');Route::get('/data/{kind}',[RecordController::class,'index']);
 Route::post('/data/keluarga/{id}/kepala',[RecordController::class,'setHead'])->whereNumber('id');
 Route::get('/mutasi',[MutationController::class,'index']);Route::get('/mutasi/baru',[MutationController::class,'form']);Route::post('/mutasi/baru',[MutationController::class,'store']);
 Route::get('/mutasi/{id}',[MutationController::class,'show'])->whereNumber('id');Route::post('/mutasi/{id}/terapkan',[MutationController::class,'apply'])->whereNumber('id');Route::post('/mutasi/{id}/ubah',[MutationController::class,'update'])->middleware('role:ADMINISTRATOR')->whereNumber('id');Route::post('/mutasi/{id}/hapus',[MutationController::class,'destroy'])->middleware('role:ADMINISTRATOR')->whereNumber('id');
 Route::get('/{kind}/baru',[GuestController::class,'form'])->whereIn('kind',['tamu','sewa']);Route::post('/{kind}/baru',[GuestController::class,'save'])->whereIn('kind',['tamu','sewa']);
 Route::get('/{kind}/{id}/ubah',[GuestController::class,'form'])->whereIn('kind',['tamu','sewa'])->whereNumber('id');Route::post('/{kind}/{id}/ubah',[GuestController::class,'save'])->whereIn('kind',['tamu','sewa'])->whereNumber('id');Route::post('/{kind}/{id}/selesai',[GuestController::class,'end'])->whereIn('kind',['tamu','sewa'])->whereNumber('id');Route::post('/{kind}/{id}/hapus',[GuestController::class,'destroy'])->whereIn('kind',['tamu','sewa'])->middleware('role:ADMINISTRATOR')->whereNumber('id');Route::get('/{kind}',[GuestController::class,'index'])->whereIn('kind',['tamu','sewa']);
 Route::get('/surat',[LetterController::class,'index']);Route::get('/surat/baru',[LetterController::class,'form']);Route::post('/surat/baru',[LetterController::class,'save']);Route::get('/arsip',[LetterController::class,'archive']);
 Route::get('/surat/{id}/ubah',[LetterController::class,'form'])->whereNumber('id');Route::post('/surat/{id}/ubah',[LetterController::class,'save'])->whereNumber('id');Route::get('/surat/{id}',[LetterController::class,'show'])->whereNumber('id');Route::post('/surat/{id}/persetujuan',[LetterController::class,'approve'])->whereNumber('id');Route::post('/surat/{id}/cabut',[LetterController::class,'revoke'])->middleware('role:ADMINISTRATOR')->whereNumber('id');Route::post('/surat/{id}/hapus',[LetterController::class,'destroy'])->middleware('role:ADMINISTRATOR')->whereNumber('id');
 Route::get('/laporan',[ReportController::class,'index']);Route::get('/laporan/ekspor/{format}',[ReportController::class,'export']);
 Route::get('/berkas/{id}',[FileController::class,'download'])->whereNumber('id');
 Route::get('/notifikasi',[DashboardController::class,'notifications']);Route::post('/notifikasi/{id}/baca',[DashboardController::class,'readNotification'])->whereNumber('id');
 Route::middleware('role:ADMINISTRATOR')->group(function(){Route::get('/log',[AdminController::class,'logs']);Route::get('/backup',[AdminController::class,'backups']);Route::post('/backup',[AdminController::class,'backup']);Route::get('/backup/{id}/unduh',[AdminController::class,'downloadBackup'])->whereNumber('id');});
});
