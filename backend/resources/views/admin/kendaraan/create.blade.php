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
                <strong>Terjadi kesalahan:</strong>
                <ul style="margin: 8px 0 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.kendaraan.store') }}" enctype="multipart/form-data" class="travs-form-grid">
            @csrf

            <div class="travs-form-grid-2">
                <div class="travs-field">
                    <label for="nomor_plat">Nomor Plat</label>
                    <input id="nomor_plat" name="nomor_plat" class="travs-input" value="{{ old('nomor_plat') }}" required placeholder="Contoh: KB 1234 AB">
                </div>
                <div class="travs-field">
                    <label for="merk_kendaraan">Merk Kendaraan</label>
                    <input id="merk_kendaraan" name="merk_kendaraan" class="travs-input" value="{{ old('merk_kendaraan') }}" required placeholder="Contoh: Toyota Avanza">
                </div>
                <div class="travs-field">
                    <label for="warna">Warna</label>
                    <input id="warna" name="warna" class="travs-input" value="{{ old('warna') }}" required placeholder="Contoh: Putih">
                </div>
                <div class="travs-field">
                    <label for="tahun_kendaraan">Tahun</label>
                    <input id="tahun_kendaraan" name="tahun_kendaraan" type="number" class="travs-input" value="{{ old('tahun_kendaraan') }}" placeholder="Contoh: 2023">
                </div>
                <div class="travs-field">
                    <label for="tipe_kendaraan">Tipe Kendaraan</label>
                    <input id="tipe_kendaraan" name="tipe_kendaraan" class="travs-input" value="{{ old('tipe_kendaraan') }}" placeholder="Contoh: MPV, SUV, Van">
                </div>
                <div class="travs-field">
                    <label for="kapasitas_kursi">Kapasitas Kursi</label>
                    <input id="kapasitas_kursi" name="kapasitas_kursi" type="number" class="travs-input" value="{{ old('kapasitas_kursi') }}" required placeholder="Contoh: 7">
                </div>
                <div class="travs-field">
                    <label for="total_kilometer">Total Kilometer</label>
                    <input id="total_kilometer" name="total_kilometer" type="number" class="travs-input" value="{{ old('total_kilometer', 0) }}">
                </div>
                <div class="travs-field">
                    <label for="km_servis_berikutnya">KM Servis Berikutnya</label>
                    <input id="km_servis_berikutnya" name="km_servis_berikutnya" type="number" class="travs-input" value="{{ old('km_servis_berikutnya') }}">
                </div>
                <div class="travs-field">
                    <label for="tgl_terakhir_servis">Tanggal Servis Terakhir</label>
                    <input id="tgl_terakhir_servis" name="tgl_terakhir_servis" type="date" class="travs-input" value="{{ old('tgl_terakhir_servis') }}">
                </div>
                <div class="travs-field">
                    <label for="tgl_pajak_tahunan">Tanggal Pajak Tahunan</label>
                    <input id="tgl_pajak_tahunan" name="tgl_pajak_tahunan" type="date" class="travs-input" value="{{ old('tgl_pajak_tahunan') }}">
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="fasilitas">Fasilitas</label>
                    <textarea id="fasilitas" name="fasilitas" class="travs-textarea" placeholder="Contoh: AC, WiFi, Charger, Bagasi Luas">{{ old('fasilitas') }}</textarea>
                </div>
                <div class="travs-field">
                    <label for="status_kendaraan">Status Kendaraan</label>
                    <select id="status_kendaraan" name="status_kendaraan" class="travs-select" required>
                        @foreach (['aktif' => 'Aktif', 'perawatan' => 'Perawatan', 'nonaktif' => 'Nonaktif'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status_kendaraan', 'aktif') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travs-field">
                    <label for="foto_kendaraan">Foto Kendaraan</label>
                    <input id="foto_kendaraan" name="foto_kendaraan" type="file" class="travs-input" accept="image/*">
                    <div class="travs-field-meta">Format: JPG, PNG, WEBP. Maks 3MB.</div>
                </div>
            </div>

            <div class="travs-form-actions">
                <button type="submit" class="travs-button">Simpan Armada</button>
            </div>
        </form>
    </div>
@endsection
