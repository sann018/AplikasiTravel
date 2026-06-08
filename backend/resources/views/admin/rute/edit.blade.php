@extends('admin.layouts.app')

@section('title', 'Edit Rute')
@section('page-title', 'Edit Rute Perjalanan')
@section('page-description', 'Perbarui data rute perjalanan untuk menjaga informasi tetap akurat.')

@section('content')
    <div class="travs-page-frame">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Form Edit Rute</h3>
                <p>Perbarui data rute {{ $rute->kota_asal }} - {{ $rute->kota_tujuan }}.</p>
            </div>
            <a href="{{ route('admin.rute.index') }}" class="travs-button-secondary">Kembali</a>
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

        <form method="POST" action="{{ route('admin.rute.update', $rute) }}" class="travs-form-grid">
            @csrf
            @method('PUT')

            <div class="travs-form-grid-2">
                <div class="travs-field">
                    <label for="kota_asal">Kota Asal</label>
                    <input id="kota_asal" name="kota_asal" class="travs-input" value="{{ old('kota_asal', $rute->kota_asal) }}" required>
                </div>
                <div class="travs-field">
                    <label for="kota_tujuan">Kota Tujuan</label>
                    <input id="kota_tujuan" name="kota_tujuan" class="travs-input" value="{{ old('kota_tujuan', $rute->kota_tujuan) }}" required>
                </div>
                <div class="travs-field">
                    <label for="harga_tiket">Harga Tiket (Rp)</label>
                    <input id="harga_tiket" name="harga_tiket" type="number" class="travs-input" value="{{ old('harga_tiket', $rute->harga_tiket) }}" required>
                </div>
                <div class="travs-field">
                    <label for="estimasi_durasi_menit">Estimasi Durasi (menit)</label>
                    <input id="estimasi_durasi_menit" name="estimasi_durasi_menit" type="number" class="travs-input" value="{{ old('estimasi_durasi_menit', $rute->estimasi_durasi_menit) }}" required>
                </div>
                <div class="travs-field travs-field-span-2">
                    <label for="deskripsi_jalur">Deskripsi Jalur</label>
                    <textarea id="deskripsi_jalur" name="deskripsi_jalur" class="travs-textarea">{{ old('deskripsi_jalur', $rute->deskripsi_jalur) }}</textarea>
                </div>
                <div class="travs-field">
                    <label for="jarak_km">Jarak (km)</label>
                    <input id="jarak_km" name="jarak_km" type="number" class="travs-input" value="{{ old('jarak_km', $rute->jarak_km) }}">
                </div>
                <div class="travs-field">
                    <label for="status">Status Rute</label>
                    <select id="status" name="status" class="travs-select" required>
                        <option value="aktif" @selected(old('status', $rute->status) === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status', $rute->status) === 'nonaktif')>Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="travs-form-actions travs-inline">
                <a href="{{ route('admin.rute.index') }}" class="travs-button-secondary">Batal</a>
                <button type="submit" class="travs-button">Simpan Perubahan</button>
            </div>
        </form>
    </div>
@endsection
