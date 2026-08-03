@extends('admin.layouts.app')

@section('title', 'Tambah Jadwal')
@section('page-title', 'Tambah Jadwal Perjalanan')
@section('page-description', 'Buat jadwal keberangkatan baru dengan memilih rute, kendaraan, dan pengemudi.')

@section('content')
    @include('admin.partials.page-back', [
        'url' => route('admin.jadwal.index'),
        'label' => 'Kembali ke Daftar Jadwal',
    ])

    <div class="travs-page-frame card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between">
            <div class="travs-panel-title">
                <h3>Form Tambah Jadwal</h3>
                <p>Isi data jadwal perjalanan dengan lengkap untuk membuka pemesanan penumpang.</p>
            </div>
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

        <form method="POST" action="{{ route('admin.jadwal.store') }}" class="travs-form-grid d-grid">
            @csrf

            <div class="travs-form-grid-2 d-grid">
                <div class="travs-field">
                    <label for="rute_id" class="form-label">Rute Perjalanan</label>
                    <select id="rute_id" name="rute_id" class="travs-select @error('rute_id') is-invalid @enderror form-select" required>
                        <option value="">Pilih rute</option>
                        @foreach ($ruteOptions as $rute)
                            <option
                                value="{{ $rute->id }}"
                                data-duration="{{ (int) $rute->estimasi_durasi_menit }}"
                                @selected(old('rute_id') == $rute->id)
                            >
                                {{ $rute->kota_asal }} - {{ $rute->kota_tujuan }} (Rp{{ number_format($rute->harga_tiket, 0, ',', '.') }})
                            </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'rute_id'])
                </div>
                <div class="travs-field">
                    <label for="kendaraan_id" class="form-label">Kendaraan</label>
                    <select id="kendaraan_id" name="kendaraan_id" class="travs-select @error('kendaraan_id') is-invalid @enderror form-select" required>
                        <option value="">Pilih kendaraan</option>
                        @foreach ($kendaraanOptions as $kendaraan)
                            @php
                                $availability = $kendaraanAvailability[$kendaraan->id] ?? ['locked' => false, 'reason' => ''];
                                $isVehicleLocked = (bool) $availability['locked'];
                            @endphp
                            <option
                                value="{{ $kendaraan->id }}"
                                data-capacity="{{ (int) $kendaraan->kapasitas_kursi }}"
                                data-layout='@json($kendaraan->getSeatLayoutConfig())'
                                @selected(old('kendaraan_id') == $kendaraan->id)
                                @disabled($isVehicleLocked)
                            >
                                {{ $kendaraan->label_armada }} - {{ $kendaraan->kapasitas_kursi }} kursi{{ $isVehicleLocked ? ' - Sedang digunakan' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <small style="color:#64748b;">Sistem memeriksa interval, posisi kota terakhir, dan jeda minimal 2 jam sebelum kendaraan dipakai kembali.</small>
                    @include('admin.partials.field-error', ['name' => 'kendaraan_id'])
                </div>
                <div class="travs-field travs-field-span-2">
                    <label class="form-label">Kursi yang Ditutup</label>
                    <div class="travs-field-meta">Pilih kursi yang tidak dijual khusus untuk jadwal ini. Kursi yang ditutup tampil abu-abu di aplikasi dan tidak bisa dipilih penumpang.</div>
                    <div id="closed-seat-options" class="travs-seat-toggle-grid" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:12px;"></div>
                    <div id="closed-seat-empty" class="travs-field-meta" style="margin-top:10px;">Pilih kendaraan terlebih dahulu untuk menampilkan nomor kursi.</div>
                    @include('admin.partials.field-error', ['name' => 'kursi_ditutup'])
                </div>
                <div class="travs-field">
                    <label for="pengemudi_id" class="form-label">Pengemudi</label>
                    <select id="pengemudi_id" name="pengemudi_id" class="travs-select @error('pengemudi_id') is-invalid @enderror form-select" required>
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
                    <small style="color:#64748b;">Sistem memeriksa interval, posisi kota terakhir, dan jeda minimal 2 jam sebelum Pengemudi ditugaskan kembali.</small>
                    @include('admin.partials.field-error', ['name' => 'pengemudi_id'])
                </div>
                <div class="travs-field">
                    <label for="status" class="form-label">Status Jadwal</label>
                    <select id="status" name="status" class="travs-select @error('status') is-invalid @enderror form-select" required>
                        @foreach (['draft' => 'Draft', 'dibuka' => 'Dibuka', 'ditutup' => 'Ditutup', 'berangkat' => 'Berangkat', 'selesai' => 'Selesai'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'dibuka') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="travs-field-meta">Gunakan status Berangkat hanya jika perjalanan benar-benar sudah dimulai. Pengemudi juga dapat mengubahnya melalui tombol Mulai Perjalanan.</div>
                    @include('admin.partials.field-error', ['name' => 'status'])
                </div>
                <div class="travs-field">
                    <label for="waktu_berangkat" class="form-label">Waktu Berangkat</label>
                    <input id="waktu_berangkat" name="waktu_berangkat" type="hidden" value="{{ old('waktu_berangkat') }}">
                    <div class="travs-form-grid-2 d-grid" style="gap: 12px;">
                        <input id="tanggal_berangkat_display" type="date" class="travs-input @error('waktu_berangkat') is-invalid @enderror form-control" required>
                        <input id="jam_berangkat_display" type="text" class="travs-input @error('waktu_berangkat') is-invalid @enderror form-control" inputmode="numeric" maxlength="5" placeholder="17:00" required>
                    </div>
                    <small style="color:#64748b;">Gunakan format jam 24 jam, contoh 17:00 atau 01:30.</small>
                    @include('admin.partials.field-error', ['name' => 'waktu_berangkat'])
                </div>
                <div class="travs-field">
                    <label for="estimasi_tiba" class="form-label">Estimasi Tiba</label>
                    <input id="estimasi_tiba" name="estimasi_tiba" type="hidden" value="{{ old('estimasi_tiba') }}">
                    <input id="estimasi_tiba_display" type="text" class="travs-input @error('estimasi_tiba') is-invalid @enderror form-control" value="" readonly placeholder="Akan dihitung otomatis">
                    <small style="color:#64748b;">Estimasi dihitung otomatis dari waktu berangkat dan durasi rute. Backend tetap menghitung ulang saat disimpan.</small>
                    @include('admin.partials.field-error', ['name' => 'estimasi_tiba'])
                </div>
            </div>

            <div class="travs-form-actions d-flex justify-content-end">
                <button type="submit" class="travs-button btn btn-primary">Simpan Jadwal</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    @include('admin.jadwal.partials.skrip-tambah')
@endpush
