@extends('admin.layouts.app')

@section('title', 'Laporan Pemesanan')
@section('page-title', 'Laporan Pemesanan')
@section('page-description', 'Pantau pemesanan berdasarkan periode.')

@section('content')
    <section class="travs-summary-grid">
        <article class="travs-summary-card">
            <small>Total Booking</small>
            <strong>{{ number_format($summary['total_booking']) }}</strong>
            <span>Pada hasil filter saat ini</span>
        </article>
        <article class="travs-summary-card">
            <small>Total Kursi</small>
            <strong>{{ number_format($summary['total_kursi']) }}</strong>
            <span>Kursi terjual / dipesan</span>
        </article>
        <article class="travs-summary-card">
            <small>Total Nilai</small>
            <strong>Rp{{ number_format($summary['total_nilai'], 0, ',', '.') }}</strong>
            <span>Akumulasi nominal booking</span>
        </article>
    </section>

    <article class="travs-section-card">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Filter Laporan</h3>
                <p>Pilih status dan rentang tanggal untuk menampilkan laporan booking yang dibutuhkan.</p>
            </div>

            <details class="travs-export-menu">
                <summary>Export Data</summary>
                <div class="travs-export-menu__list">
                    <a href="{{ route('admin.laporan.pemesanan.export', array_merge(request()->query(), ['format' => 'xls'])) }}">Export Excel</a>
                    <a href="{{ route('admin.laporan.pemesanan.export', array_merge(request()->query(), ['format' => 'csv'])) }}">Export CSV</a>
                </div>
            </details>
        </div>

        <form method="GET" class="travs-filter-grid">
            <div class="travs-field">
                <label for="status">Status Booking</label>
                <select id="status" name="status" class="travs-select">
                    <option value="">Semua status</option>
                    @foreach (['menunggu_pembayaran' => 'Menunggu Pembayaran', 'terbayar' => 'Terbayar', 'dibatalkan' => 'Dibatalkan', 'selesai' => 'Selesai'] as $value => $label)
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
                <a href="{{ route('admin.laporan.pemesanan') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap">
            <table class="travs-table">
                <thead>
                    <tr>
                        <th>Kode Booking</th>
                        <th>Penumpang</th>
                        <th>Rute</th>
                        <th>Status</th>
                        <th>Kursi</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        <tr>
                            <td>{{ $booking->kode_booking }}</td>
                            <td>{{ $booking->pengguna->nama }}</td>
                            <td>{{ $booking->jadwal->rute->kota_asal }} - {{ $booking->jadwal->rute->kota_tujuan }}</td>
                            <td>
                                <span class="travs-status-pill travs-status-{{ $booking->status_pemesanan }}">
                                    {{ ucwords(str_replace('_', ' ', $booking->status_pemesanan)) }}
                                </span>
                            </td>
                            <td>{{ number_format($booking->detail_pemesanan_count) }}</td>
                            <td>Rp{{ number_format($booking->total_harga, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="travs-table-empty">Belum ada data booking pada filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $bookings->links() }}
    </article>
@endsection
