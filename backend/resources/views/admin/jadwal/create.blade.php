@extends('admin.layouts.app')

@section('title', 'Tambah Jadwal')
@section('page-title', 'Tambah Jadwal Perjalanan')
@section('page-description', 'Buat jadwal keberangkatan baru dengan memilih rute, kendaraan, dan pengemudi.')

@section('content')
    <div class="travs-page-frame">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Form Tambah Jadwal</h3>
                <p>Isi data jadwal perjalanan dengan lengkap untuk membuka pemesanan penumpang.</p>
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

        <form method="POST" action="{{ route('admin.jadwal.store') }}" class="travs-form-grid">
            @csrf

            <div class="travs-form-grid-2">
                <div class="travs-field">
                    <label for="rute_id">Rute Perjalanan</label>
                    <select id="rute_id" name="rute_id" class="travs-select @error('rute_id') is-invalid @enderror" required>
                        <option value="">Pilih rute</option>
                        @foreach ($ruteOptions as $rute)
                            <option value="{{ $rute->id }}" @selected(old('rute_id') == $rute->id)>
                                {{ $rute->kota_asal }} - {{ $rute->kota_tujuan }} (Rp{{ number_format($rute->harga_tiket, 0, ',', '.') }})
                            </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'rute_id'])
                </div>
                <div class="travs-field">
                    <label for="kendaraan_id">Kendaraan</label>
                    <select id="kendaraan_id" name="kendaraan_id" class="travs-select @error('kendaraan_id') is-invalid @enderror" required>
                        <option value="">Pilih kendaraan</option>
                        @foreach ($kendaraanOptions as $kendaraan)
                            <option value="{{ $kendaraan->id }}" @selected(old('kendaraan_id') == $kendaraan->id)>
                                {{ $kendaraan->merk_kendaraan }} ({{ $kendaraan->nomor_plat }}) - {{ $kendaraan->kapasitas_kursi }} kursi
                            </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'kendaraan_id'])
                </div>
                <div class="travs-field">
                    <label for="pengemudi_id">Pengemudi</label>
                    <select id="pengemudi_id" name="pengemudi_id" class="travs-select @error('pengemudi_id') is-invalid @enderror" required>
                        <option value="">Pilih pengemudi</option>
                        @foreach ($pengemudiOptions as $pengemudi)
                            @php
                                $availability = $pengemudiAvailability[$pengemudi->id] ?? ['locked' => false, 'reason' => ''];
                                $isDriverLocked = (bool) $availability['locked'];
                            @endphp
                            <option
                                value="{{ $pengemudi->id }}"
                                @selected(old('pengemudi_id') == $pengemudi->id)
                                @disabled($isDriverLocked)
                            >
                                {{ $pengemudi->pengguna->nama }} (SIM {{ $pengemudi->nomor_sim }}){{ $isDriverLocked ? ' - Terkunci' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <small style="color:#64748b;">Pengemudi terkunci jika masih punya jadwal aktif pada tanggal yang sama.</small>
                    @include('admin.partials.field-error', ['name' => 'pengemudi_id'])
                </div>
                <div class="travs-field">
                    <label for="status">Status Jadwal</label>
                    <select id="status" name="status" class="travs-select @error('status') is-invalid @enderror" required>
                        @foreach (['draft' => 'Draft', 'dibuka' => 'Dibuka', 'ditutup' => 'Ditutup', 'berangkat' => 'Berangkat', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'dibuka') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'status'])
                </div>
                <div class="travs-field">
                    <label for="waktu_berangkat">Waktu Berangkat</label>
                    <input id="waktu_berangkat" name="waktu_berangkat" type="datetime-local" class="travs-input @error('waktu_berangkat') is-invalid @enderror" value="{{ old('waktu_berangkat') }}" required>
                    @include('admin.partials.field-error', ['name' => 'waktu_berangkat'])
                </div>
                <div class="travs-field">
                    <label for="estimasi_tiba">Estimasi Tiba</label>
                    <input id="estimasi_tiba" name="estimasi_tiba" type="datetime-local" class="travs-input @error('estimasi_tiba') is-invalid @enderror" value="{{ old('estimasi_tiba') }}">
                    @include('admin.partials.field-error', ['name' => 'estimasi_tiba'])
                </div>
            </div>

            <div class="travs-form-actions">
                <button type="submit" class="travs-button">Simpan Jadwal</button>
            </div>
        </form>
    </div>
@endsection
