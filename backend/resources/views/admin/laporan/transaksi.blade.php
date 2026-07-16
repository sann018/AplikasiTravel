@extends('admin.layouts.app')

@section('title', 'Laporan Transaksi')
@section('page-title', 'Laporan Transaksi')
@section('page-description', 'Pantau pembayaran berdasarkan status, metode bayar, dan periode untuk kebutuhan rekap transaksi.')

@section('content')
    <section class="travs-summary-grid">
        <article class="travs-summary-card">
            <small>Total Transaksi</small>
            <strong>{{ number_format($summary['total_transaksi']) }}</strong>
            <span>Pada hasil filter</span>
        </article>
        <article class="travs-summary-card">
            <small>Transaksi Berhasil</small>
            <strong>{{ number_format($summary['berhasil']) }}</strong>
            <span>Status pembayaran berhasil</span>
        </article>
        <article class="travs-summary-card">
            <small>Nilai Berhasil</small>
            <strong>Rp{{ number_format($summary['nilai_berhasil'], 0, ',', '.') }}</strong>
            <span>Akumulasi pembayaran berhasil</span>
        </article>
    </section>

    <article class="travs-section-card">
        <div class="travs-panel-header">
            <div class="travs-panel-title">
                <h3>Filter Laporan</h3>
                <p>Gunakan filter di bawah untuk menyusun laporan transaksi berdasarkan status dan metode bayar.</p>
            </div>

            <details class="travs-export-menu">
                <summary>Export Data</summary>
                <div class="travs-export-menu__list">
                    <a href="{{ route('admin.laporan.transaksi.export', array_merge(request()->query(), ['format' => 'xls'])) }}">Export Excel</a>
                    <a href="{{ route('admin.laporan.transaksi.export', array_merge(request()->query(), ['format' => 'csv'])) }}">Export CSV</a>
                </div>
            </details>
        </div>

        <form method="GET" class="travs-filter-grid">
            <div class="travs-field">
                <label for="status">Status Pembayaran</label>
                <select id="status" name="status" class="travs-select">
                    <option value="">Semua status</option>
                    @foreach (['menunggu' => 'Menunggu', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'kedaluwarsa' => 'Kedaluwarsa'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="travs-field">
                <label for="metode_bayar">Metode Bayar</label>
                <select id="metode_bayar" name="metode_bayar" class="travs-select">
                    <option value="">Semua metode</option>
                    @foreach (['transfer' => 'Transfer', 'qris' => 'QRIS', 'cash' => 'Cash'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['metode_bayar'] ?? '') === $value)>{{ $label }}</option>
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
                <a href="{{ route('admin.laporan.transaksi') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap">
            <table class="travs-table">
                <thead>
                    <tr>
                        <th>Order Gateway</th>
                        <th>Kode Pemesanan</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Nominal</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->order_id_gateway ?: '-' }}</td>
                            <td>
                                {{ $transaction->pemesanan?->kode_booking ?: '-' }}
                                <div class="travs-table-subcopy">{{ $transaction->pemesanan?->pengguna?->nama ?: 'Tanpa penumpang' }}</div>
                            </td>
                            <td>
                                <span class="travs-status-pill travs-status-{{ strtolower($transaction->metode_bayar) }}">
                                    {{ strtoupper($transaction->metode_bayar) }}
                                </span>
                            </td>
                            <td>
                                <span class="travs-status-pill travs-status-{{ $transaction->status_bayar }}">
                                    {{ ucwords(str_replace('_', ' ', $transaction->status_bayar)) }}
                                </span>
                            </td>
                            <td>Rp{{ number_format($transaction->jumlah_bayar, 0, ',', '.') }}</td>
                            <td>{{ $transaction->created_at->translatedFormat('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="travs-table-empty">Belum ada data transaksi pada filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $transactions->links() }}
    </article>
@endsection
