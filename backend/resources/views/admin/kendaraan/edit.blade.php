@extends('admin.layouts.app')

@section('title', 'Edit Armada')
@section('page-title', 'Edit Armada Kendaraan')
@section('page-description', 'Perbarui data kendaraan untuk menjaga informasi armada tetap akurat.')

@section('content')
    <div class="travs-page-frame">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Form Edit Armada</h3>
                <p>Perbarui data kendaraan {{ $kendaraan->merk_kendaraan }} ({{ $kendaraan->nomor_plat }}).</p>
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

        <form method="POST" action="{{ route('admin.kendaraan.update', $kendaraan) }}" enctype="multipart/form-data" class="travs-form-grid">
            @csrf
            @method('PUT')

            <div class="travs-form-grid-2">
                <div class="travs-field">
                    <label for="nomor_plat">Nomor Plat</label>
                    <input id="nomor_plat" name="nomor_plat" class="travs-input @error('nomor_plat') is-invalid @enderror" value="{{ old('nomor_plat', $kendaraan->nomor_plat) }}" required>
                    @include('admin.partials.field-error', ['name' => 'nomor_plat'])
                </div>
                <div class="travs-field">
                    <label for="merk_kendaraan">Merk Kendaraan</label>
                    <input id="merk_kendaraan" name="merk_kendaraan" class="travs-input @error('merk_kendaraan') is-invalid @enderror" value="{{ old('merk_kendaraan', $kendaraan->merk_kendaraan) }}" required>
                    @include('admin.partials.field-error', ['name' => 'merk_kendaraan'])
                </div>
                <div class="travs-field">
                    <label for="warna">Warna</label>
                    <input id="warna" name="warna" class="travs-input @error('warna') is-invalid @enderror" value="{{ old('warna', $kendaraan->warna) }}" required>
                    @include('admin.partials.field-error', ['name' => 'warna'])
                </div>
                <div class="travs-field">
                    <label for="tahun_kendaraan">Tahun</label>
                    <input id="tahun_kendaraan" name="tahun_kendaraan" type="number" class="travs-input @error('tahun_kendaraan') is-invalid @enderror" value="{{ old('tahun_kendaraan', $kendaraan->tahun_kendaraan) }}">
                    @include('admin.partials.field-error', ['name' => 'tahun_kendaraan'])
                </div>
                <div class="travs-field">
                    <label for="tipe_kendaraan">Tipe Kendaraan</label>
                    <select id="tipe_kendaraan" name="tipe_kendaraan" class="travs-select @error('tipe_kendaraan') is-invalid @enderror">
                        <option value="">Pilih tipe kendaraan</option>
                        @foreach ($vehicleTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('tipe_kendaraan', $kendaraan->tipe_kendaraan) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'tipe_kendaraan'])
                </div>
                <div class="travs-field">
                    <label for="kapasitas_kursi">Kapasitas Kursi</label>
                    <input id="kapasitas_kursi" name="kapasitas_kursi" type="number" class="travs-input @error('kapasitas_kursi') is-invalid @enderror" value="{{ old('kapasitas_kursi', $kendaraan->kapasitas_kursi) }}" required>
                    @include('admin.partials.field-error', ['name' => 'kapasitas_kursi'])
                </div>
                <div class="travs-field">
                    <label for="total_kilometer">Total Kilometer</label>
                    <input id="total_kilometer" name="total_kilometer" type="number" class="travs-input @error('total_kilometer') is-invalid @enderror" value="{{ old('total_kilometer', $kendaraan->total_kilometer) }}">
                    @include('admin.partials.field-error', ['name' => 'total_kilometer'])
                </div>
                <div class="travs-field">
                    <label for="km_servis_berikutnya">KM Servis Berikutnya</label>
                    <input id="km_servis_berikutnya" name="km_servis_berikutnya" type="number" class="travs-input @error('km_servis_berikutnya') is-invalid @enderror" value="{{ old('km_servis_berikutnya', $kendaraan->km_servis_berikutnya) }}">
                    @include('admin.partials.field-error', ['name' => 'km_servis_berikutnya'])
                </div>
                <div class="travs-field">
                    <label for="tgl_terakhir_servis">Tanggal Servis Terakhir</label>
                    <input id="tgl_terakhir_servis" name="tgl_terakhir_servis" type="date" class="travs-input @error('tgl_terakhir_servis') is-invalid @enderror" value="{{ old('tgl_terakhir_servis', optional($kendaraan->tgl_terakhir_servis)->format('Y-m-d')) }}">
                    @include('admin.partials.field-error', ['name' => 'tgl_terakhir_servis'])
                </div>
                <div class="travs-field">
                    <label for="tgl_pajak_tahunan">Tanggal Pajak Tahunan</label>
                    <input id="tgl_pajak_tahunan" name="tgl_pajak_tahunan" type="date" class="travs-input @error('tgl_pajak_tahunan') is-invalid @enderror" value="{{ old('tgl_pajak_tahunan', optional($kendaraan->tgl_pajak_tahunan)->format('Y-m-d')) }}">
                    @include('admin.partials.field-error', ['name' => 'tgl_pajak_tahunan'])
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="fasilitas">Fasilitas</label>
                    <textarea id="fasilitas" name="fasilitas" class="travs-textarea @error('fasilitas') is-invalid @enderror">{{ old('fasilitas', $kendaraan->fasilitas) }}</textarea>
                    @include('admin.partials.field-error', ['name' => 'fasilitas'])
                </div>
                <div class="travs-field">
                    <label for="status_kendaraan">Status Kendaraan</label>
                    <select id="status_kendaraan" name="status_kendaraan" class="travs-select @error('status_kendaraan') is-invalid @enderror" required>
                        @foreach (['aktif' => 'Aktif', 'perawatan' => 'Perawatan', 'nonaktif' => 'Nonaktif'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status_kendaraan', $kendaraan->status_kendaraan) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'status_kendaraan'])
                </div>
                <div class="travs-field">
                    <label for="foto_kendaraan">Foto Kendaraan</label>
                    @if ($kendaraan->foto_kendaraan)
                        <div class="travs-current-photo travs-current-vehicle-photo">
                            <img class="travs-photo-preview-trigger" src="/storage/{{ ltrim($kendaraan->foto_kendaraan, '/') }}" alt="Foto {{ $kendaraan->merk_kendaraan }}">
                            <span>Foto kendaraan saat ini</span>
                        </div>
                    @endif
                    <input id="foto_kendaraan" name="foto_kendaraan" type="file" class="travs-input @error('foto_kendaraan') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="travs-field-meta">Format: JPG, PNG, WEBP. Maks 3MB. Kosongkan jika tidak ingin mengubah foto.</div>
                    @include('admin.partials.field-error', ['name' => 'foto_kendaraan'])
                </div>
            </div>

            <div class="travs-form-actions travs-inline">
                <a href="{{ route('admin.kendaraan.index') }}" class="travs-button-secondary">Batal</a>
                <button type="submit" class="travs-button">Simpan Perubahan</button>
            </div>
        </form>
    </div>
@endsection
