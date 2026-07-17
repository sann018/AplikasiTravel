@extends('admin.layouts.app')

@section('title', 'Tambah Armada')
@section('page-title', 'Tambah Armada Kendaraan')
@section('page-description', 'Lengkapi data kendaraan baru agar jadwal dan kapasitas kursi bisa disusun otomatis.')

@section('content')
    <div class="travs-page-frame">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Form Tambah Armada</h3>
                <p>Isi data kendaraan dengan lengkap untuk memudahkan pengelolaan jadwal dan kapasitas kursi.</p>
            </div>
            <a href="{{ route('admin.kendaraan.index') }}" class="travs-button-secondary">Kembali</a>
        </div>

        @if ($errors->any())
            <div class="travs-alert travs-alert-danger">
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.kendaraan.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <label for="nomor_plat" class="form-label">Nomor Plat</label>
                    <input id="nomor_plat" name="nomor_plat" class="form-control @error('nomor_plat') is-invalid @enderror" value="{{ old('nomor_plat') }}" required placeholder="Contoh: KB 1234 AB">
                    @include('admin.partials.field-error', ['name' => 'nomor_plat', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="merk_kendaraan" class="form-label">Merk Kendaraan</label>
                    <input id="merk_kendaraan" name="merk_kendaraan" class="form-control @error('merk_kendaraan') is-invalid @enderror" value="{{ old('merk_kendaraan') }}" required placeholder="Contoh: Toyota Avanza">
                    @include('admin.partials.field-error', ['name' => 'merk_kendaraan', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="warna" class="form-label">Warna</label>
                    <input id="warna" name="warna" class="form-control @error('warna') is-invalid @enderror" value="{{ old('warna') }}" required placeholder="Contoh: Putih">
                    @include('admin.partials.field-error', ['name' => 'warna', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="tahun_kendaraan" class="form-label">Tahun</label>
                    <input id="tahun_kendaraan" name="tahun_kendaraan" type="number" class="form-control @error('tahun_kendaraan') is-invalid @enderror" value="{{ old('tahun_kendaraan') }}" placeholder="Contoh: 2023">
                    @include('admin.partials.field-error', ['name' => 'tahun_kendaraan', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="tipe_kendaraan" class="form-label">Tipe Kendaraan</label>
                    <input id="tipe_kendaraan" name="tipe_kendaraan" class="form-control @error('tipe_kendaraan') is-invalid @enderror" value="{{ old('tipe_kendaraan') }}" placeholder="Contoh: MPV, Minibus, atau Travel Shuttle">
                    @include('admin.partials.field-error', ['name' => 'tipe_kendaraan', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="kapasitas_kursi" class="form-label">Kapasitas Kursi</label>
                    <input id="kapasitas_kursi" name="kapasitas_kursi" type="number" min="5" max="6" class="form-control @error('kapasitas_kursi') is-invalid @enderror" value="{{ old('kapasitas_kursi', 6) }}" required placeholder="Contoh: 6">
                    <div class="form-text">Kapasitas kursi adalah jumlah kursi penumpang. Kursi sopir tidak dihitung.</div>
                    @include('admin.partials.field-error', ['name' => 'kapasitas_kursi', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="seat_layout" class="form-label">Susunan Kursi Default</label>
                    <input id="seat_layout" name="seat_layout" class="form-control @error('seat_layout') is-invalid @enderror" value="{{ old('seat_layout') }}" placeholder="Contoh: 1,3,2">
                    <div class="form-text">Gunakan 1,3,2 untuk 6 kursi. Kursi yang ditutup untuk ruang bagasi diatur pada saat membuat jadwal.</div>
                    @include('admin.partials.field-error', ['name' => 'seat_layout', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="total_kilometer" class="form-label">Total Kilometer</label>
                    <input id="total_kilometer" name="total_kilometer" type="number" class="form-control @error('total_kilometer') is-invalid @enderror" value="{{ old('total_kilometer', 0) }}">
                    @include('admin.partials.field-error', ['name' => 'total_kilometer', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="km_servis_berikutnya" class="form-label">KM Servis Berikutnya</label>
                    <input id="km_servis_berikutnya" name="km_servis_berikutnya" type="number" class="form-control @error('km_servis_berikutnya') is-invalid @enderror" value="{{ old('km_servis_berikutnya') }}">
                    @include('admin.partials.field-error', ['name' => 'km_servis_berikutnya', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="tgl_terakhir_servis" class="form-label">Tanggal Servis Terakhir</label>
                    <input id="tgl_terakhir_servis" name="tgl_terakhir_servis" type="date" class="form-control @error('tgl_terakhir_servis') is-invalid @enderror" value="{{ old('tgl_terakhir_servis') }}">
                    @include('admin.partials.field-error', ['name' => 'tgl_terakhir_servis', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="tgl_pajak_tahunan" class="form-label">Tanggal Pajak Tahunan</label>
                    <input id="tgl_pajak_tahunan" name="tgl_pajak_tahunan" type="date" class="form-control @error('tgl_pajak_tahunan') is-invalid @enderror" value="{{ old('tgl_pajak_tahunan') }}">
                    @include('admin.partials.field-error', ['name' => 'tgl_pajak_tahunan', 'bootstrap' => true])
                </div>
                <div class="col-12">
                    <label for="fasilitas" class="form-label">Fasilitas</label>
                    <textarea id="fasilitas" name="fasilitas" class="form-control @error('fasilitas') is-invalid @enderror" rows="4" placeholder="Contoh: AC, WiFi, Charger, Bagasi Luas">{{ old('fasilitas') }}</textarea>
                    @include('admin.partials.field-error', ['name' => 'fasilitas', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="status_kendaraan" class="form-label">Status Kendaraan</label>
                    <select id="status_kendaraan" name="status_kendaraan" class="form-select @error('status_kendaraan') is-invalid @enderror" required>
                        @foreach (['aktif' => 'Aktif', 'perawatan' => 'Perawatan', 'nonaktif' => 'Nonaktif'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status_kendaraan', 'aktif') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Perawatan untuk armada yang sementara diservis. Nonaktif untuk armada yang tidak digunakan dalam operasional.</div>
                    @include('admin.partials.field-error', ['name' => 'status_kendaraan', 'bootstrap' => true])
                </div>
                <div class="col-12 col-lg-6">
                    <label for="foto_kendaraan" class="form-label">Foto Kendaraan</label>
                    <input id="foto_kendaraan" name="foto_kendaraan" type="file" class="form-control @error('foto_kendaraan') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="form-text">Format: JPG, PNG, WEBP. Maks 3MB.</div>
                    @include('admin.partials.field-error', ['name' => 'foto_kendaraan', 'bootstrap' => true])
                </div>
            </div>

            <div class="travs-form-actions">
                <button type="submit" class="travs-button">Simpan Armada</button>
            </div>
        </form>
    </div>
@endsection
