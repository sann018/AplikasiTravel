@extends('admin.layouts.app')

@section('title', 'Tambah Petugas Loket')
@section('page-title', 'Tambah Petugas Loket')
@section('page-description', 'Buat akun internal untuk pelayanan pemesanan melalui aplikasi mobile.')

@section('content')
    @include('admin.partials.page-back', ['url' => route('admin.petugas-loket.index'), 'label' => 'Kembali ke Daftar Petugas Loket'])
    <div class="travs-page-frame card">
        <div class="travs-panel-header"><div class="travs-panel-title"><h3>Form Tambah Petugas</h3><p>Petugas akan membuat kata sandinya sendiri melalui tautan aktivasi email.</p></div></div>
        @if ($errors->any())
            <div class="travs-alert travs-alert-danger alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ route('admin.petugas-loket.store') }}" class="travs-form-grid d-grid">
            @csrf
            @include('admin.petugas-loket.partials.form')
            <div class="travs-form-actions d-flex justify-content-end"><button type="submit" class="travs-button btn btn-primary">Simpan dan Kirim Aktivasi</button></div>
        </form>
    </div>
@endsection
