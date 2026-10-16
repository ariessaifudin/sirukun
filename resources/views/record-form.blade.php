@extends('layout') @section('title',($row?'Ubah ':'Tambah ').$d['title']) @section('content')
<div class="pagehead"><div><div class="eyebrow">{{ $d['title'] }}</div><h1>{{ $row?'Ubah':'Tambah' }} data</h1><p>Isian bertanda * wajib diisi.</p></div><a class="btn secondary" href="{{ url('/data/'.$kind) }}">← Kembali</a></div><section class="card formcard"><form method="post" action="{{ url('/data/'.$kind.($row?'/'.$row->id.'/ubah':'/baru')) }}">@csrf<div class="input-group">
@foreach($d['fields'] as $name=>$spec)
@php([$label,$type]=explode('|',$spec)); @php($val=$row?->{$name}??($name==='aktif'?1:''))
@if($name==='peran_id')<div class="field"><label for="peran_id">Peran pengguna *</label><select id="peran_id" name="peran_id" required>@foreach(Illuminate\Support\Facades\DB::table('peran')->get() as $p)<option value="{{ $p->id }}" data-code="{{ $p->kode }}" @selected((string)old($name,$val)===(string)$p->id)>{{ $p->nama }}</option>@endforeach</select></div>
@else
@php($req=!in_array($name,['nik','tanggal_lahir','telepon','password','jabatan','rt_id','rw_id','warga_id','parent_id','nama_pemilik','kecamatan','kota'])||($kind==='hunian'&&$name==='rt_id')||($kind==='rt'&&$name==='rw_id')||($kind==='pengguna'&&$name==='password'&&!$row))
@include('input',['name'=>$name,'label'=>$label,'type'=>isset($options[$type])?'select':$type,'choices'=>$options[$type]??null,'value'=>$val,'required'=>$req,'wide'=>$type==='textarea'])
@endif
@endforeach</div>
@if($kind==='menu')<h3>Hak akses setiap peran</h3><p class="muted">Hak modul tidak dapat melampaui batas peran inti. Contoh: Warga tetap tidak dapat mengelola akun.</p><div class="tablewrap"><table><thead><tr><th>PERAN</th><th>LIHAT</th><th>TAMBAH</th><th>UBAH</th><th>HAPUS</th></tr></thead><tbody>@foreach(Illuminate\Support\Facades\DB::table('peran')->get() as $role)<tr><td>{{ $role->nama }}</td>@foreach(['lihat','tambah','ubah','hapus'] as $a)<td><input type="checkbox" name="grants[{{ $role->id }}][{{ $a }}]" value="1" aria-label="{{ $role->nama.' '.$a }}" @checked(old('grants.'.$role->id.'.'.$a,$grants->get($role->id)?->{$a}??($role->kode==='ADMINISTRATOR')))></td>@endforeach</tr>@endforeach</tbody></table></div>@endif
<div class="formactions"><button class="btn">✓ Simpan data</button><a class="btn secondary" href="{{ url('/data/'.$kind) }}">Batal</a></div></form></section>
@if($kind==='keluarga'&&$row)<section class="card formcard" style="margin-top:20px"><h3>Ganti kepala keluarga</h3><p class="muted">Pilih anggota aktif. Kepala keluarga lama diubah menjadi anggota lainnya dalam transaksi yang sama.</p><form method="post" action="{{ url('/data/keluarga/'.$row->id.'/kepala') }}">@csrf
@include('input',['name'=>'kepala_id','label'=>'Kepala keluarga baru','choices'=>Illuminate\Support\Facades\DB::table('warga')->where('kartu_keluarga_id',$row->id)->where('status','aktif')->whereNull('deleted_at')->pluck('nama','id')->all(),'required'=>true])
<button class="btn secondary">Tetapkan kepala keluarga</button></form></section>@endif
@endsection
