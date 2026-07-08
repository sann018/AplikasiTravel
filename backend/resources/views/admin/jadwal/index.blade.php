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
            <small>Ditutup</small>
            <strong>{{ number_format($stats['ditutup']) }}</strong>
            <span>Tidak tampil di aplikasi</span>
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

    <section class="travs-section-card">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Daftar Jadwal</h3>
                <p>Monitoring jadwal mencakup armada, driver, kursi tersedia, dan jumlah booking yang sudah masuk.</p>
            </div>
            <a href="{{ route('admin.jadwal.create') }}" class="travs-button">Tambah Jadwal</a>
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
                    @foreach (['draft' => 'Draft', 'dibuka' => 'Dibuka', 'ditutup' => 'Ditutup', 'berangkat' => 'Berangkat', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
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
                            <td>{{ $item->kendaraan->label_armada }}</td>
                            <td>{{ $item->pengemudi->pengguna->nama }}</td>
                            <td>{{ $item->kursi_tersedia }} tersedia / {{ $item->pemesanan_count }} booking</td>
                            <td>
                                <div class="travs-table-actions">
                                    <a href="{{ route('admin.jadwal.edit', $item) }}" class="travs-button-link">Edit</a>
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
    </section>
@endsection
