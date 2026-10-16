@extends('layout') @section('title','Laporkan mutasi') @section('content')
<div class="pagehead"><div><div class="eyebrow">PERUBAHAN KEPENDUDUKAN</div><h1>Laporkan mutasi</h1><p>Pilih jenis peristiwa dan lengkapi dokumen pendukung.</p></div><a class="btn secondary" href="{{ url('/mutasi') }}">← Kembali</a></div><section class="card formcard"><form method="post" enctype="multipart/form-data" action="{{ url('/mutasi/baru') }}">@csrf
<div class="input-group"><div class="field"><label for="mutasi-jenis">Jenis mutasi *</label><select id="mutasi-jenis" name="jenis" required>@foreach(['masuk'=>'Pindah masuk','keluar'=>'Pindah keluar','lahir'=>'Kelahiran','mati'=>'Kematian'] as $k=>$v)<option value="{{ $k }}" @selected(old('jenis')===$k)>{{ $v }}</option>@endforeach</select></div>@include('input',['name'=>'tanggal_peristiwa','label'=>'Tanggal peristiwa','type'=>'date','value'=>today()->toDateString(),'required'=>true])</div>
@foreach(['masuk','keluar','lahir','mati'] as $kind)<fieldset class="mutasi-fields" data-mutasi="{{ $kind }}"><div class="input-group">
@if(in_array($kind,['masuk','lahir']))
@include('input',['name'=>'kk_tujuan_id','label'=>'Kartu keluarga tujuan','choices'=>$options['kk'],'required'=>true,'prefix'=>$kind])
@include('input',['name'=>'nama','label'=>$kind==='lahir'?'Nama bayi':'Nama lengkap','required'=>true,'prefix'=>$kind])
@include('input',['name'=>'nik','label'=>$kind==='lahir'?'NIK bayi (boleh kosong)':'NIK','required'=>$kind==='masuk','prefix'=>$kind])
@include('input',['name'=>'jenis_kelamin','label'=>'Jenis kelamin','choices'=>['L'=>'Laki-laki','P'=>'Perempuan'],'required'=>true,'prefix'=>$kind])
@if($kind==='masuk')
@include('input',['name'=>'tanggal_lahir','label'=>'Tanggal lahir','type'=>'date','required'=>true,'prefix'=>$kind])
@include('input',['name'=>'hubungan_keluarga','label'=>'Hubungan dalam KK','choices'=>$options['hubungan'],'required'=>true,'prefix'=>$kind])
@include('input',['name'=>'telepon','label'=>'Nomor ponsel','required'=>true,'prefix'=>$kind])
@include('input',['name'=>'status_rumah','label'=>'Status kepemilikan rumah','choices'=>['milik'=>'Milik sendiri','sewa'=>'Sewa','kontrak'=>'Kontrak'],'required'=>true,'prefix'=>$kind])
@include('input',['name'=>'alamat_asal','label'=>'Alamat asal','type'=>'textarea','required'=>true,'wide'=>true,'prefix'=>$kind])
@include('input',['name'=>'ktp','label'=>'Foto KTP','type'=>'file','required'=>true,'prefix'=>$kind])
@include('input',['name'=>'kkfile','label'=>'Foto kartu keluarga','type'=>'file','required'=>true,'prefix'=>$kind])
@else
<input type="hidden" name="hubungan_keluarga" value="anak">
@include('input',['name'=>'nama_ayah','label'=>'Nama ayah','required'=>true,'prefix'=>$kind])
@include('input',['name'=>'nama_ibu','label'=>'Nama ibu','required'=>true,'prefix'=>$kind])
@include('input',['name'=>'surat_lahir','label'=>'Surat keterangan lahir','type'=>'file','required'=>true,'wide'=>true,'prefix'=>$kind])
@endif
@else
@include('input',['name'=>'warga_id','label'=>'Warga yang dilaporkan','choices'=>$options['warga'],'required'=>true,'wide'=>true,'prefix'=>$kind])
@if($kind==='keluar')
@include('input',['name'=>'alamat_tujuan','label'=>'Alamat tujuan','type'=>'textarea','required'=>true,'wide'=>true,'prefix'=>$kind])
@include('input',['name'=>'alasan','label'=>'Alasan pindah','type'=>'textarea','required'=>true,'wide'=>true,'prefix'=>$kind])
@else
@include('input',['name'=>'penyebab','label'=>'Penyebab singkat','choices'=>['sakit'=>'Sakit','usia'=>'Usia','lainnya'=>'Lainnya'],'required'=>true,'prefix'=>$kind])
@include('input',['name'=>'keterangan','label'=>'Keterangan tambahan','prefix'=>$kind])
@include('input',['name'=>'surat_mati','label'=>'Surat keterangan kematian','type'=>'file','required'=>true,'wide'=>true,'prefix'=>$kind])
@endif
@endif</div></fieldset>@endforeach
@include('input',['name'=>'catatan','label'=>'Catatan tambahan','type'=>'textarea'])
<div class="notice">Untuk pendatang atau kelahiran, siapkan KK tujuan terlebih dahulu. Apabila belum tersedia, hubungi pengurus RT.</div><div class="formactions"><button class="btn">✓ Simpan laporan</button><a class="btn secondary" href="{{ url('/mutasi') }}">Batal</a></div></form></section>@endsection
