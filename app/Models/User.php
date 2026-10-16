<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
class User extends Authenticatable {
 use SoftDeletes;
 protected $table='pengguna';
 protected $guarded=['id'];
 protected $hidden=['password_hash'];
 protected function casts(): array{return ['aktif'=>'boolean'];}
 public function getAuthPasswordName(){return 'password_hash';}
 public function getAuthPassword(){return $this->password_hash;}
 public function peran(){return $this->belongsTo(Role::class,'peran_id');}
 public function roleCode(): string{return $this->peran?->kode??'';}
 public function isAdmin():bool{return $this->roleCode()==='ADMINISTRATOR';}
 public function isOperator():bool{return in_array($this->roleCode(),['RT','ADMINISTRATOR']);}
 public function rtIds():array {
  if($this->isAdmin())return \Illuminate\Support\Facades\DB::table('rt')->whereNull('deleted_at')->pluck('id')->all();
  if($this->roleCode()==='RW')return \Illuminate\Support\Facades\DB::table('rt')->where('rw_id',$this->rw_id)->whereNull('deleted_at')->pluck('id')->all();
  if($this->roleCode()==='RT')return [$this->rt_id];
  $id=\Illuminate\Support\Facades\DB::table('warga as w')->join('kartu_keluarga as k','k.id','=','w.kartu_keluarga_id')->join('hunian as h','h.id','=','k.hunian_id')->where('w.id',$this->warga_id)->value('h.rt_id');
  return $id?[$id]:[];
 }
 public function canApprove(string $stage):bool{return $this->roleCode()===$stage && $this->jabatan===('Ketua '.$stage);}
}
