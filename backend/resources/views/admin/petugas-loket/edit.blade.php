@extends('admin.layouts.app')

@section('title', 'Edit Petugas Loket')
@section('page-title', 'Edit Petugas Loket')
@section('page-description', 'Perbarui identitas atau status akun pelayanan loket.')

@section('content')
    @include('admin.partials.page-back', ['url' => route('admin.petugas-loket.index'), 'label' => 'Kembali ke Daftar Petugas Loket'])
    <div class="travs-page-frame card">
        <div class="travs-panel-header"><div class="travs-panel-title"><h3>Form Edit Petugas</h3><p>Perubahan email mencabut sesi lama agar akses tetap aman.</p></div></div>
        @if ($errors->any())
            <div class="travs-alert travs-alert-danger alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ route('admin.petugas-loket.update', $petugas) }}" class="travs-form-grid d-grid">
            @csrf
            @method('PUT')
            @include('admin.petugas-loket.partials.form', ['petugas' => $petugas])
            <div class="travs-form-actions d-flex justify-content-end"><button type="submit" class="travs-button btn btn-primary">Simpan Perubahan</button></div>
        </form>
    </div>
@endsection
