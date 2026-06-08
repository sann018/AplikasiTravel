@extends('admin.layouts.app')

@section('title', 'Edit Jadwal')
@section('page-title', 'Edit Jadwal Perjalanan')
@section('page-description', 'Perbarui data jadwal perjalanan untuk menjaga informasi tetap akurat.')

@section('content')
    <div class="travs-page-frame">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Form Edit Jadwal</h3>
                <p>Perbarui data jadwal perjalanan.</p>
            </div>
            <a href="{{ route('admin.jadwal.index') }}" class="travs-button-secondary">Kembali</a>
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

        <form method="POST" action="{{ route('admin.jadwal.update', $jadwal) }}" class="travs-form-grid">
            @csrf
            @method('PUT')

            <div class="travs-form-grid-2">
                <div class="travs-field">
                    <label for="rute_id">Rute Perjalanan</label>
                    <select id="rute_id" name="rute_id" class="travs-select" required>
                        <option value="">Pilih rute</option>
                        @foreach ($ruteOptions as $rute)
                            <option value="{{ $rute->id }}" @selected(old('rute_id', $jadwal->rute_id) == $rute->id)>
                                {{ $rute->kota_asal }} - {{ $rute->kota_tujuan }} (Rp{{ number_format($rute->harga_tiket, 0, ',', '.') }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="travs-field">
                    <label for="kendaraan_id">Kendaraan</label>
                    <select id="kendaraan_id" name="kendaraan_id" class="travs-select" required>
                        <option value="">Pilih kendaraan</option>
                        @foreach ($kendaraanOptions as $kendaraan)
                            <option value="{{ $kendaraan->id }}" @selected(old('kendaraan_id', $jadwal->kendaraan_id) == $kendaraan->id)>
                                {{ $kendaraan->merk_kendaraan }} ({{ $kendaraan->nomor_plat }}) - {{ $kendaraan->kapasitas_kursi }} kursi
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="travs-field">
                    <label for="pengemudi_id">Pengemudi</label>
                    <select id="pengemudi_id" name="pengemudi_id" class="travs-select" required>
                        <option value="">Pilih pengemudi</option>
                        @foreach ($pengemudiOptions as $pengemudi)
                            <option value="{{ $pengemudi->id }}" @selected(old('pengemudi_id', $jadwal->pengemudi_id) == $pengemudi->id)>
                                {{ $pengemudi->pengguna->nama }} (SIM {{ $pengemudi->nomor_sim }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="travs-field">
                    <label for="status">Status Jadwal</label>
                    <select id="status" name="status" class="travs-select" required>
                        @foreach (['dibuka' => 'Dibuka', 'ditutup' => 'Ditutup', 'berangkat' => 'Berangkat', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $jadwal->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travs-field">
                    <label for="waktu_berangkat">Waktu Berangkat</label>
                    <input id="waktu_berangkat" name="waktu_berangkat" type="datetime-local" class="travs-input" value="{{ old('waktu_berangkat', $jadwal->waktu_berangkat?->format('Y-m-d\TH:i')) }}" required>
                </div>
                <div class="travs-field">
                    <label for="estimasi_tiba">Estimasi Tiba</label>
                    <input id="estimasi_tiba" name="estimasi_tiba" type="datetime-local" class="travs-input" value="{{ old('estimasi_tiba', $jadwal->estimasi_tiba?->format('Y-m-d\TH:i')) }}">
                </div>
            </div>

            <div class="travs-form-actions travs-inline">
                <a href="{{ route('admin.jadwal.index') }}" class="travs-button-secondary">Batal</a>
                <button type="submit" class="travs-button">Simpan Perubahan</button>
            </div>
        </form>
    </div>
@endsection
