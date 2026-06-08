@extends('admin.layouts.app')

@section('title', 'Tambah Biaya Operasional')
@section('page-title', 'Tambah Biaya Operasional')
@section('page-description', 'Catat biaya operasional perjalanan untuk keperluan rekap dan laporan.')

@section('content')
    <section class="travs-section-card">
        <form method="POST" action="{{ route('admin.biaya.store') }}" class="travs-form-grid">
            @csrf

            <div class="travs-form-grid-2">
                <div class="travs-field">
                    <label for="jadwal_id">Jadwal Perjalanan <span class="text-danger">*</span></label>
                    <select id="jadwal_id" name="jadwal_id" class="travs-select @error('jadwal_id') is-invalid @enderror" required>
                        <option value="">Pilih Jadwal</option>
                        @foreach($jadwalOptions as $jadwal)
                            <option value="{{ $jadwal->id }}" @selected(old('jadwal_id') == $jadwal->id)>
                                {{ $jadwal->rute->kota_asal }} - {{ $jadwal->rute->kota_tujuan }} | {{ $jadwal->waktu_berangkat->format('d M Y H:i') }}
                            </option>
                        @endforeach
                    </select>
                    @error('jadwal_id')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="travs-field">
                    <label for="jenis_biaya">Jenis Biaya <span class="text-danger">*</span></label>
                    <input
                        id="jenis_biaya"
                        name="jenis_biaya"
                        class="travs-input @error('jenis_biaya') is-invalid @enderror"
                        value="{{ old('jenis_biaya') }}"
                        required
                        placeholder="Contoh: BBM, Tol, Parkir"
                    >
                    @error('jenis_biaya')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="travs-field">
                    <label for="nominal">Nominal <span class="text-danger">*</span></label>
                    <input
                        id="nominal"
                        name="nominal"
                        type="number"
                        class="travs-input @error('nominal') is-invalid @enderror"
                        value="{{ old('nominal') }}"
                        required
                        placeholder="Contoh: 500000"
                        min="0"
                    >
                    @error('nominal')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="travs-field">
                    <label for="tanggal_biaya">Tanggal Biaya <span class="text-danger">*</span></label>
                    <input
                        id="tanggal_biaya"
                        name="tanggal_biaya"
                        type="datetime-local"
                        class="travs-input @error('tanggal_biaya') is-invalid @enderror"
                        value="{{ old('tanggal_biaya', now()->format('Y-m-d\TH:i')) }}"
                        required
                    >
                    @error('tanggal_biaya')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="travs-field travs-field-span-2">
                    <label for="keterangan">Keterangan</label>
                    <textarea
                        id="keterangan"
                        name="keterangan"
                        class="travs-textarea @error('keterangan') is-invalid @enderror"
                        rows="3"
                        placeholder="Detail tambahan tentang biaya ini"
                    >{{ old('keterangan') }}</textarea>
                    @error('keterangan')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>
            </div>

            <div class="travs-form-actions travs-inline">
                <button type="submit" class="travs-button">
                    <i data-feather="save"></i>
                    Simpan Biaya
                </button>
                <a href="{{ route('admin.biaya.index') }}" class="travs-button-secondary">
                    <i data-feather="x"></i>
                    Batal
                </a>
            </div>
        </form>
    </section>
@endsection
