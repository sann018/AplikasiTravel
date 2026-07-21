@extends('admin.layouts.app')

@section('title', 'Tambah Rute')
@section('page-title', 'Tambah Rute Perjalanan')
@section('page-description', 'Buat rute perjalanan baru untuk menghubungkan kota asal dan tujuan.')

@section('content')
    <div class="travs-page-frame card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between">
            <div class="travs-panel-title">
                <h3>Form Tambah Rute</h3>
                <p>Isi data rute perjalanan dengan lengkap untuk memudahkan pembuatan jadwal.</p>
            </div>
            <a href="{{ route('admin.rute.index') }}" class="travs-button-secondary btn btn-secondary">Kembali</a>
        </div>

        @if ($errors->any())
            <div class="travs-alert travs-alert-danger alert alert-danger">
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.rute.store') }}" class="travs-form-grid d-grid">
            @csrf

            <div class="travs-form-grid-2 d-grid">
                <div class="travs-field">
                    <label for="kota_asal" class="form-label">Kota Asal</label>
                    <input id="kota_asal" name="kota_asal" class="travs-input @error('kota_asal') is-invalid @enderror form-control" value="{{ old('kota_asal') }}" required placeholder="Contoh: Pontianak">
                    @include('admin.partials.field-error', ['name' => 'kota_asal'])
                </div>
                <div class="travs-field">
                    <label for="kota_tujuan" class="form-label">Kota Tujuan</label>
                    <input id="kota_tujuan" name="kota_tujuan" class="travs-input @error('kota_tujuan') is-invalid @enderror form-control" value="{{ old('kota_tujuan') }}" required placeholder="Contoh: Sanggau">
                    @include('admin.partials.field-error', ['name' => 'kota_tujuan'])
                </div>
                <div class="travs-field">
                    <label for="harga_tiket" class="form-label">Harga Tiket (Rp)</label>
                    <input id="harga_tiket" name="harga_tiket" type="number" class="travs-input @error('harga_tiket') is-invalid @enderror form-control" value="{{ old('harga_tiket') }}" required placeholder="Contoh: 150000">
                    @include('admin.partials.field-error', ['name' => 'harga_tiket'])
                </div>
                <div class="travs-field">
                    <label for="estimasi_durasi_menit" class="form-label">Estimasi Durasi (menit)</label>
                    <input id="estimasi_durasi_menit" name="estimasi_durasi_menit" type="number" class="travs-input @error('estimasi_durasi_menit') is-invalid @enderror form-control" value="{{ old('estimasi_durasi_menit') }}" required placeholder="Contoh: 180">
                    @include('admin.partials.field-error', ['name' => 'estimasi_durasi_menit'])
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="deskripsi_jalur" class="form-label">Deskripsi Jalur</label>
                    <textarea id="deskripsi_jalur" name="deskripsi_jalur" class="travs-textarea @error('deskripsi_jalur') is-invalid @enderror form-control" placeholder="Deskripsi jalur atau titik-titik penting di sepanjang rute">{{ old('deskripsi_jalur') }}</textarea>
                    @include('admin.partials.field-error', ['name' => 'deskripsi_jalur'])
                </div>
                <div class="travs-field">
                    <label for="jarak_km" class="form-label">Jarak (km)</label>
                    <input id="jarak_km" name="jarak_km" type="number" class="travs-input @error('jarak_km') is-invalid @enderror form-control" value="{{ old('jarak_km') }}" placeholder="Contoh: 220">
                    @include('admin.partials.field-error', ['name' => 'jarak_km'])
                </div>
                <div class="travs-field">
                    <label for="status" class="form-label">Status Rute</label>
                    <select id="status" name="status" class="travs-select @error('status') is-invalid @enderror form-select" required>
                        <option value="aktif" @selected(old('status', 'aktif') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status') === 'nonaktif')>Nonaktif</option>
                    </select>
                    @include('admin.partials.field-error', ['name' => 'status'])
                </div>
            </div>

            <div class="travs-form-actions d-flex justify-content-end">
                <button type="submit" class="travs-button btn btn-primary">Simpan Rute</button>
            </div>
        </form>
    </div>
@endsection
