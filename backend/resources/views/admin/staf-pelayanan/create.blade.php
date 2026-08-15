@extends('admin.layouts.app')

@section('title', 'Tambah Staf Pelayanan')
@section('page-title', 'Tambah Staf Pelayanan')
@section('page-description', 'Buat akun internal untuk pelayanan pemesanan melalui aplikasi mobile.')

@section('content')
    @include('admin.partials.page-back', ['url' => route('admin.staf-pelayanan.index'), 'label' => 'Kembali ke Daftar Staf Pelayanan'])
    <div class="travs-page-frame card">
        <div class="travs-panel-header"><div class="travs-panel-title"><h3>Form Tambah Staf Pelayanan</h3><p>Staf Pelayanan akan membuat kata sandinya sendiri melalui tautan aktivasi email.</p></div></div>
        @if ($errors->any())
            <div class="travs-alert travs-alert-danger alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ route('admin.staf-pelayanan.store') }}" class="travs-form-grid d-grid">
            @csrf
            @include('admin.staf-pelayanan.partials.form')
            <div class="travs-form-actions d-flex justify-content-end"><button type="submit" class="travs-button btn btn-primary">Simpan dan Kirim Aktivasi</button></div>
        </form>
    </div>
@endsection
