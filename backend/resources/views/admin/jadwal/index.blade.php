@extends('admin.layouts.app')

@section('title', 'Jadwal Perjalanan')
@section('page-title', 'Jadwal Perjalanan')
@section('page-description', 'Susun jadwal keberangkatan, hubungkan rute, armada, pengemudi, dan bangun denah kursi otomatis untuk setiap trip.')

@section('content')
    <section class="travs-summary-grid">
        <article class="travs-summary-card">
            <small>Total Jadwal</small>
            <strong>{{ number_format($stats['total']) }}</strong>
            <span>Seluruh jadwal tercatat</span>
        </article>
        <article class="travs-summary-card">
            <small>Status Dibuka</small>
            <strong>{{ number_format($stats['dibuka']) }}</strong>
            <span>Siap dipesan penumpang</span>
        </article>
        <article class="travs-summary-card">
            <small>Sedang Berangkat</small>
            <strong>{{ number_format($stats['berangkat']) }}</strong>
            <span>Trip dalam operasional</span>
        </article>
        <article class="travs-summary-card">
            <small>Selesai</small>
            <strong>{{ number_format($stats['selesai']) }}</strong>
            <span>Trip telah rampung</span>
        </article>
    </section>

    <section class="travs-split-grid">
        <article class="travs-section-card travs-form-card">
            <div class="travs-panel-title">
                <h3>{{ $editingJadwal ? 'Edit Jadwal' : 'Tambah Jadwal' }}</h3>
                <p>Saat jadwal dibuat atau diubah, kursi akan digenerate otomatis menyesuaikan kapasitas armada aktif.</p>
            </div>

            <form method="POST" action="{{ $editingJadwal ? route('admin.jadwal.update', $editingJadwal) : route('admin.jadwal.store') }}" class="travs-form-grid">
                @csrf
                @if ($editingJadwal)
                    @method('PUT')
                @endif

                <div class="travs-form-grid-2">
                    <div class="travs-field travs-field-span-2">
                        <label for="rute_id">Rute</label>
                        <select id="rute_id" name="rute_id" class="travs-select" required>
                            <option value="">Pilih rute aktif</option>
                            @foreach ($ruteOptions as $rute)
                                <option value="{{ $rute->id }}" @selected((string) old('rute_id', $editingJadwal?->rute_id) === (string) $rute->id)>
                                    {{ $rute->kota_asal }} - {{ $rute->kota_tujuan }} &middot; Rp{{ number_format($rute->harga_tiket, 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="travs-field">
                        <label for="kendaraan_id">Armada</label>
                        <select id="kendaraan_id" name="kendaraan_id" class="travs-select" required>
                            <option value="">Pilih armada aktif</option>
                            @foreach ($kendaraanOptions as $kendaraan)
                                <option value="{{ $kendaraan->id }}" @selected((string) old('kendaraan_id', $editingJadwal?->kendaraan_id) === (string) $kendaraan->id)>
                                    {{ $kendaraan->nomor_plat }} &middot; {{ $kendaraan->merk_kendaraan }} &middot; {{ $kendaraan->kapasitas_kursi }} kursi
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="travs-field">
                        <label for="pengemudi_id">Pengemudi</label>
                        <select id="pengemudi_id" name="pengemudi_id" class="travs-select" required>
                            <option value="">Pilih pengemudi aktif</option>
                            @foreach ($pengemudiOptions as $pengemudi)
                                <option value="{{ $pengemudi->id }}" @selected((string) old('pengemudi_id', $editingJadwal?->pengemudi_id) === (string) $pengemudi->id)>
                                    {{ $pengemudi->pengguna->nama }} &middot; {{ $pengemudi->nomor_sim }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="travs-field">
                        <label for="waktu_berangkat">Waktu Berangkat</label>
                        <input id="waktu_berangkat" name="waktu_berangkat" type="datetime-local" class="travs-input" value="{{ old('waktu_berangkat', optional($editingJadwal?->waktu_berangkat)->format('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="travs-field">
                        <label for="estimasi_tiba">Estimasi Tiba</label>
                        <input id="estimasi_tiba" name="estimasi_tiba" type="datetime-local" class="travs-input" value="{{ old('estimasi_tiba', optional($editingJadwal?->estimasi_tiba)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="travs-field travs-field-span-2">
                        <label for="status">Status Jadwal</label>
                        <select id="status" name="status" class="travs-select" required>
                            @foreach (['draft' => 'Draft', 'dibuka' => 'Dibuka', 'berangkat' => 'Berangkat', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $editingJadwal?->status ?? 'draft') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="travs-form-actions travs-inline">
                    @if ($editingJadwal)
                        <a href="{{ route('admin.jadwal.index') }}" class="travs-button-secondary">Batal Edit</a>
                    @endif
                    <button type="submit" class="travs-button">{{ $editingJadwal ? 'Simpan Perubahan' : 'Tambah Jadwal' }}</button>
                </div>
            </form>
        </article>

        <article class="travs-section-card">
            <div class="travs-panel-header">
                <div class="travs-panel-title">
                    <h3>Daftar Jadwal</h3>
                    <p>Monitoring jadwal mencakup armada, driver, kursi tersedia, dan jumlah booking yang sudah masuk.</p>
                </div>
            </div>

            <form method="GET" class="travs-filter-grid">
                <div class="travs-field">
                    <label for="search">Cari Jadwal</label>
                    <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Rute, armada, atau pengemudi">
                </div>
                <div class="travs-field">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="travs-select">
                        <option value="">Semua status</option>
                        @foreach (['draft' => 'Draft', 'dibuka' => 'Dibuka', 'berangkat' => 'Berangkat', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travs-field">
                    <label for="tanggal">Tanggal</label>
                    <input id="tanggal" name="tanggal" type="date" class="travs-input" value="{{ $filters['tanggal'] ?? '' }}">
                </div>
                <div class="travs-filter-actions">
                    <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                    <a href="{{ route('admin.jadwal.index') }}" class="travs-button-secondary">Reset</a>
                </div>
            </form>

            <div class="travs-table-wrap">
                <table class="travs-table">
                    <thead>
                        <tr>
                            <th>Keberangkatan</th>
                            <th>Rute</th>
                            <th>Armada</th>
                            <th>Pengemudi</th>
                            <th>Kursi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jadwal as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->waktu_berangkat->translatedFormat('d M Y') }}</strong>
                                    <div class="travs-table-subcopy">{{ $item->waktu_berangkat->format('H:i') }} &middot; {{ ucfirst($item->status) }}</div>
                                </td>
                                <td>{{ $item->rute->kota_asal }} - {{ $item->rute->kota_tujuan }}</td>
                                <td>{{ $item->kendaraan->nomor_plat }}</td>
                                <td>{{ $item->pengemudi->pengguna->nama }}</td>
                                <td>{{ $item->kursi_tersedia }} tersedia / {{ $item->pemesanan_count }} booking</td>
                                <td>
                                    <div class="travs-table-actions">
                                        <a href="{{ route('admin.jadwal.index', ['edit' => $item->id] + request()->query()) }}" class="travs-button-link">Edit</a>
                                        <form method="POST" action="{{ route('admin.jadwal.destroy', $item) }}" onsubmit="return confirm('Hapus jadwal ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="travs-button-link travs-button-link-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="travs-table-empty">Belum ada jadwal perjalanan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $jadwal->links() }}
        </article>
    </section>
@endsection
