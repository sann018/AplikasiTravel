@extends('admin.layouts.app')

@section('title', 'Pembayaran')
@section('page-title', 'Pembayaran')
@section('page-description', 'Pantau pembayaran tunai, QRIS, dan transfer.')

@section('content')
    <section class="travs-summary-grid">
        <article class="travs-summary-card">
            <small>Total Pembayaran</small>
            <strong>{{ number_format($summary['total']) }}</strong>
            <span>Semua metode bayar</span>
        </article>
        <article class="travs-summary-card">
            <small>Menunggu</small>
            <strong>{{ number_format($summary['menunggu']) }}</strong>
            <span>Belum selesai diproses</span>
        </article>
        <article class="travs-summary-card">
            <small>Verifikasi Cash</small>
            <strong>{{ number_format($summary['cash_pending']) }}</strong>
            <span>Butuh konfirmasi admin</span>
        </article>
        <article class="travs-summary-card">
            <small>Perlu Dicek</small>
            <strong>{{ number_format($summary['exceptions']) }}</strong>
            <span>Status belum normal</span>
        </article>
    </section>

    <article class="travs-section-card">
        <div class="travs-panel-title">
            <h3>Filter Pembayaran</h3>
            <p>Cari transaksi berdasarkan kode, penumpang, rute, metode, atau status.</p>
        </div>

        <form method="GET" class="travs-filter-grid travs-filter-grid-wide">
            <div class="travs-field">
                <label for="search">Cari</label>
                <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Kode pemesanan, order, penumpang, rute">
            </div>
            <div class="travs-field">
                <label for="metode_bayar">Metode</label>
                <select id="metode_bayar" name="metode_bayar" class="travs-select">
                    <option value="">Semua metode</option>
                    @foreach (['transfer' => 'Transfer', 'qris' => 'QRIS', 'cash' => 'Cash'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['metode_bayar'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="travs-field">
                <label for="status_bayar">Status</label>
                <select id="status_bayar" name="status_bayar" class="travs-select">
                    <option value="">Semua status</option>
                    @foreach (['menunggu' => 'Menunggu', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'kedaluwarsa' => 'Kedaluwarsa'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status_bayar'] ?? '') === $value)>{{ $label }}</option>
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
                <a href="{{ route('admin.pembayaran.index') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap">
            <table class="travs-table">
                <thead>
                    <tr>
                        <th>Kode Pemesanan</th>
                        <th>Penumpang & Rute</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Nominal</th>
                        <th>Catatan Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        @php
                            $booking = $payment->pemesanan;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $booking?->kode_booking ?: '-' }}</strong>
                                <div class="travs-table-subcopy">{{ $payment->order_id_gateway ?: 'Tanpa order gateway' }}</div>
                            </td>
                            <td>
                                {{ $booking?->pengguna?->nama ?: 'Tanpa penumpang' }}
                                <div class="travs-table-subcopy">
                                    {{ $booking?->jadwal?->rute ? $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan : 'Rute tidak tersedia' }}
                                </div>
                            </td>
                            <td>
                                <span class="travs-status-pill travs-status-{{ strtolower($payment->metode_bayar) }}">
                                    {{ strtoupper($payment->metode_bayar) }}
                                </span>
                            </td>
                            <td>
                                <div class="travs-status-stack">
                                    <span class="travs-status-pill travs-status-{{ $payment->status_bayar }}">
                                        {{ ucwords(str_replace('_', ' ', $payment->status_bayar)) }}
                                    </span>
                                    <span class="travs-status-pill travs-status-{{ $booking?->status_pemesanan ?? 'draft' }}">
                                        {{ ucwords(str_replace('_', ' ', $booking?->status_pemesanan ?? 'draft')) }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                Rp{{ number_format($payment->jumlah_bayar, 0, ',', '.') }}
                                <div class="travs-table-subcopy">
                                    {{ $payment->expired_at ? 'Batas '.$payment->expired_at->translatedFormat('d M Y H:i') : 'Tanpa batas aktif' }}
                                </div>
                            </td>
                            <td>
                                <span class="travs-status-pill travs-status-{{ $payment->issue_label === 'Normal' ? 'selesai' : 'menunggu' }}">
                                    {{ $payment->issue_label }}
                                </span>
                            </td>
                            <td>
                                <div class="travs-table-actions">
                                    <a href="{{ route('admin.pembayaran.show', $payment) }}" class="travs-button-link">Detail Pembayaran</a>
                                    @if ($booking)
                                        <a href="{{ route('admin.pemesanan.show', $booking) }}" class="travs-button-link">Pemesanan</a>
                                    @endif

                                    @if ($payment->can_confirm_cash && $booking)
                                        <form method="POST" action="{{ route('admin.pembayaran.confirm-cash', $booking) }}">
                                            @csrf
                                            <button type="submit" class="travs-button-link">Verifikasi Cash</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="travs-table-empty">Tidak ada data pembayaran pada filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $payments->links() }}
    </article>
@endsection
