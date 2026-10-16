@extends('layout') @section('title',$kind==='tamu'?'Laporan tamu menginap':'Penghuni kontrakan / kos') @section('content')
<div class="pagehead"><div><div class="eyebrow">PENCATATAN LINGKUNGAN</div><h1>{{ $row?'Ubah':'Tambah' }} {{ $kind==='tamu'?'tamu menginap':'penghuni sementara' }}</h1><p>NIK atau unggahan identitas wajib tersedia.</p></div><a class="btn secondary" href="{{ url('/'.$kind) }}">← Kembali</a></div><section class="card formcard"><form method="post" enctype="multipart/form-data" action="{{ url('/'.$kind.($row?'/'.$row->id.'/ubah':'/baru')) }}">@csrf<div class="input-group">
@include('input',['name'=>'nama','label'=>'Nama lengkap','value'=>$row?->nama,'required'=>true])
@include('input',['name'=>'nik','label'=>'NIK (boleh kosong jika identitas diunggah)','value'=>$row?->nik])
@include('input',['name'=>'hunian_id','label'=>'Alamat rumah yang ditempati','choices'=>$options['hunian'],'value'=>$row?->hunian_id,'required'=>true,'wide'=>true])
@if($kind==='tamu')
@include('input',['name'=>'mulai','label'=>'Mulai menginap','type'=>'datetime-local','value'=>$row?->mulai?str_replace(' ','T',substr($row->mulai,0,16)):now()->format('Y-m-d\TH:i'),'required'=>true])
@include('input',['name'=>'rencana_selesai','label'=>'Rencana selesai','type'=>'datetime-local','value'=>$row?->rencana_selesai?str_replace(' ','T',substr($row->rencana_selesai,0,16)):now()->addDay()->format('Y-m-d\TH:i'),'required'=>true])
@include('input',['name'=>'alamat_asal','label'=>'Alamat asal','type'=>'textarea','value'=>$row?->alamat_asal,'required'=>true])
@include('input',['name'=>'tujuan','label'=>'Tujuan kunjungan','type'=>'textarea','value'=>$row?->tujuan,'required'=>true])
@else
@include('input',['name'=>'tanggal_masuk','label'=>'Tanggal masuk','type'=>'date','value'=>$row?->tanggal_masuk??today()->toDateString(),'required'=>true])
@include('input',['name'=>'tanggal_selesai','label'=>'Tanggal akhir sewa','type'=>'date','value'=>$row?->tanggal_selesai,'required'=>true])
@include('input',['name'=>'nama_kontak_darurat','label'=>'Nama kontak darurat','value'=>$row?->nama_kontak_darurat,'required'=>true])
@include('input',['name'=>'telepon_darurat','label'=>'Nomor kontak darurat','value'=>$row?->telepon_darurat,'required'=>true])
@endif
@include('input',['name'=>'identitas','label'=>$row?->identitas_berkas_id?'Ganti dokumen identitas (opsional)':'Foto identitas','type'=>'file','wide'=>true])</div><div class="formactions"><button class="btn">✓ Simpan laporan</button><a class="btn secondary" href="{{ url('/'.$kind) }}">Batal</a></div></form></section>@endsection
