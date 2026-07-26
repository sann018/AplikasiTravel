@extends('admin.layouts.app')

@section('title', 'Tambah Biaya Operasional')
@section('page-title', 'Tambah Biaya Operasional')
@section('page-description', 'Catat biaya operasional perjalanan untuk keperluan rekap dan laporan.')

@section('content')
    @include('admin.partials.page-back', [
        'url' => route('admin.biaya.index'),
        'label' => 'Kembali ke Daftar Biaya Operasional',
    ])

    <section class="travs-section-card card">
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.biaya.store') }}" class="travs-form-grid d-grid">
            @csrf

            <div class="travs-form-grid-2 d-grid">
                <div class="travs-field">
                    <label for="jadwal_id" class="form-label">Jadwal Perjalanan <span class="text-danger">*</span></label>
                    <select id="jadwal_id" name="jadwal_id" class="travs-select @error('jadwal_id') is-invalid @enderror form-select" required>
                        <option value="">Pilih Jadwal</option>
                        @foreach($jadwalOptions as $jadwal)
                            <option value="{{ $jadwal->id }}" @selected(old('jadwal_id') == $jadwal->id)>
                                {{ $jadwal->rute->kota_asal }} - {{ $jadwal->rute->kota_tujuan }} | {{ $jadwal->waktu_berangkat->format('d M Y H:i') }}
                            </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'jadwal_id'])
                </div>

                <div class="travs-field">
                    <label for="jenis_biaya" class="form-label">Jenis Biaya <span class="text-danger">*</span></label>
                    <input
                        id="jenis_biaya"
                        name="jenis_biaya"
                        class="travs-input @error('jenis_biaya') is-invalid @enderror form-control"
                        value="{{ old('jenis_biaya') }}"
                        required
                        placeholder="Contoh: BBM, Tol, Parkir"
                    >
                    @include('admin.partials.field-error', ['name' => 'jenis_biaya'])
                </div>

                <div class="travs-field">
                    <label for="nominal" class="form-label">Nominal <span class="text-danger">*</span></label>
                    <input
                        id="nominal"
                        name="nominal"
                        type="number"
                        class="travs-input @error('nominal') is-invalid @enderror form-control"
                        value="{{ old('nominal') }}"
                        required
                        placeholder="Contoh: 500000"
                        min="0"
                    >
                    @include('admin.partials.field-error', ['name' => 'nominal'])
                </div>

                <div class="travs-field">
                    <label for="tanggal_biaya" class="form-label">Tanggal Biaya <span class="text-danger">*</span></label>
                    <input
                        id="tanggal_biaya"
                        name="tanggal_biaya"
                        type="datetime-local"
                        class="travs-input @error('tanggal_biaya') is-invalid @enderror form-control"
                        value="{{ old('tanggal_biaya', now()->format('Y-m-d\TH:i')) }}"
                        required
                    >
                    @include('admin.partials.field-error', ['name' => 'tanggal_biaya'])
                </div>

                <div class="travs-field travs-field-span-2">
                    <label for="keterangan" class="form-label">Keterangan <span class="text-danger">*</span></label>
                    <textarea
                        id="keterangan"
                        name="keterangan"
                        class="travs-textarea @error('keterangan') is-invalid @enderror form-control"
                        rows="3"
                        required
                        placeholder="Detail tambahan tentang biaya ini"
                    >{{ old('keterangan') }}</textarea>
                    @include('admin.partials.field-error', ['name' => 'keterangan'])
                </div>

                <div class="travs-field travs-field-span-2">
                    <label for="bukti_nota" class="form-label">Foto Bukti Nota <span class="text-danger">*</span></label>
                    <input
                        id="bukti_nota"
                        name="bukti_nota"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="travs-input @error('bukti_nota') is-invalid @enderror form-control"
                        required
                    >
                    <div class="travs-field-meta">Bukti wajib berupa JPG, JPEG, PNG, atau WEBP dengan ukuran maksimal 3 MB.</div>
                    @include('admin.partials.field-error', ['name' => 'bukti_nota'])
                </div>
            </div>

            <div class="travs-form-actions travs-inline d-flex justify-content-end align-items-center flex-wrap">
                <button type="submit" class="travs-button btn btn-primary">
                    <i data-feather="save"></i>
                    Simpan Biaya
                </button>
                <a href="{{ route('admin.biaya.index') }}" class="travs-button-secondary btn btn-outline-secondary">
                    <i data-feather="x"></i>
                    Batal
                </a>
            </div>
        </form>
    </section>
@endsection
