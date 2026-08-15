@extends('admin.layouts.app')

@section('title', 'Edit Staf Pelayanan')
@section('page-title', 'Edit Staf Pelayanan')
@section('page-description', 'Perbarui identitas atau status akun pelayanan loket.')

@section('content')
    @include('admin.partials.page-back', ['url' => route('admin.staf-pelayanan.index'), 'label' => 'Kembali ke Daftar Staf Pelayanan'])
    <div class="travs-page-frame card">
        <div class="travs-panel-header"><div class="travs-panel-title"><h3>Form Edit Staf Pelayanan</h3><p>Perubahan email mencabut sesi lama agar akses tetap aman.</p></div></div>
        @if ($errors->any())
            <div class="travs-alert travs-alert-danger alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ route('admin.staf-pelayanan.update', $stafPelayanan) }}" class="travs-form-grid d-grid">
            @csrf
            @method('PUT')
            @include('admin.staf-pelayanan.partials.form', ['stafPelayanan' => $stafPelayanan])
            <div class="travs-form-actions d-flex justify-content-end"><button type="submit" class="travs-button btn btn-primary">Simpan Perubahan</button></div>
        </form>
    </div>
@endsection
