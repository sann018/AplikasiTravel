@extends('admin.layouts.app')

@section('title', 'Laporan Okupansi')
@section('page-title', 'Laporan Okupansi Kursi')
@section('page-description', 'Pantau tingkat keterisian kursi per jadwal untuk analisis performa rute dan perencanaan armada.')

@section('content')
    <section class="travs-summary-grid">
        <article class="travs-summary-card">
            <small>Total Jadwal</small>
            <strong>{{ number_format($summary['total_jadwal']) }}</strong>
            <span>Semua jadwal tercatat</span>
        </article>
        <article class="travs-summary-card">
            <small>Total Kursi</small>
            <strong>{{ number_format($summary['total_kursi']) }}</strong>
            <span>Keseluruhan kursi</span>
        </article>
        <article class="travs-summary-card">
            <small>Kursi Terisi</small>
            <strong>{{ number_format($summary['kursi_terisi']) }}</strong>
            <span>Sudah dipesan</span>
        </article>
        <article class="travs-summary-card">
            <small>Rata-rata Okupansi</small>
            <strong>{{ number_format($summary['rata_okupansi'], 1) }}%</strong>
            <span>Keseluruhan jadwal</span>
        </article>
    </section>

    <article class="travs-section-card">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Filter Laporan</h3>
                <p>Pilih status dan rentang tanggal untuk menampilkan data okupansi.</p>
            </div>

            <a href="{{ route('admin.laporan.okupansi.export', request()->query()) }}" class="travs-button">
                Export CSV
            </a>
        </div>

        <form method="GET" class="travs-filter-grid">
            <div class="travs-field">
                <label for="status">Status Jadwal</label>
                <select id="status" name="status" class="travs-select">
                    <option value="">Semua status</option>
                    @foreach (['dibuka' => 'Dibuka', 'berangkat' => 'Berangkat', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="travs-field">
                <label for="tanggal_mulai">Tanggal Mulai</label>
                <input id="tanggal_mulai" name="tanggal_mulai" type="date" class="travs-input" value="{{ $filters['tanggal_mulai'] ?? '' }}">
            </div>
            <div class="travs-field">
                <label for="tanggal_selesai">Tanggal Selesai</label>
                <input id="tanggal_selesai" name="tanggal_selesai" type="date" class="travs-input" value="{{ $filters['tanggal_selesai'] ?? '' }}">
            </div>
            <div class="travs-filter-actions">
                <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                <a href="{{ route('admin.laporan.okupansi') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap">
            <table class="travs-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Rute</th>
                        <th>Kendaraan</th>
                        <th>Pengemudi</th>
                        <th>Status</th>
                        <th>Kursi</th>
                        <th>Okupansi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jadwals as $jadwal)
                        <tr>
                            <td>
                                <strong>{{ $jadwal->waktu_berangkat?->format('d/m/Y') }}</strong>
                                <div class="travs-table-subcopy">{{ $jadwal->waktu_berangkat?->format('H:i') }} WIB</div>
                            </td>
                            <td>
                                {{ $jadwal->rute?->kota_asal }} - {{ $jadwal->rute?->kota_tujuan }}
                            </td>
                            <td>
                                {{ $jadwal->kendaraan?->nomor_plat ?: '-' }}
                                <div class="travs-table-subcopy">{{ $jadwal->kendaraan?->merk_kendaraan ?: '' }}</div>
                            </td>
                            <td>{{ $jadwal->pengemudi?->pengguna?->nama ?: '-' }}</td>
                            <td>
                                <span class="travs-status-pill travs-status-{{ $jadwal->status }}">
                                    {{ ucwords(str_replace('_', ' ', $jadwal->status)) }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ $jadwal->kursi_terisi }}/{{ $jadwal->total_kursi }}</strong>
                                <div class="travs-table-subcopy">
                                    Tersedia: {{ $jadwal->kursi_tersedia }} | Terkunci: {{ $jadwal->kursi_terkunci }}
                                </div>
                            </td>
                            <td>
                                @php
                                    $color = $jadwal->persen_okupansi >= 80 ? 'terbayar' : ($jadwal->persen_okupansi >= 50 ? 'menunggu' : 'kedaluwarsa');
                                @endphp
                                <span class="travs-status-pill travs-status-{{ $color }}">
                                    {{ number_format($jadwal->persen_okupansi, 1) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="travs-table-empty">Belum ada data jadwal pada filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $jadwals->links() }}
    </article>
@endsection
