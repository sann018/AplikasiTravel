@extends('admin.layouts.app')

@section('title', 'Pemesanan')
@section('page-title', 'Monitoring Pemesanan')
@section('page-description', 'Pantau booking yang masuk, status pembayaran, dan arahkan admin ke detail pemesanan untuk tindak lanjut operasional.')

@section('content')
    <section class="travs-summary-grid">
        <article class="travs-summary-card">
            <small>Total Booking</small>
            <strong>{{ number_format($stats['total']) }}</strong>
            <span>Seluruh transaksi</span>
        </article>
        <article class="travs-summary-card">
            <small>Menunggu Pembayaran</small>
            <strong>{{ number_format($stats['menunggu']) }}</strong>
            <span>Perlu dipantau admin</span>
        </article>
        <article class="travs-summary-card">
            <small>Terbayar</small>
            <strong>{{ number_format($stats['terbayar']) }}</strong>
            <span>Tiket telah valid</span>
        </article>
        <article class="travs-summary-card">
            <small>Selesai</small>
            <strong>{{ number_format($stats['selesai']) }}</strong>
            <span>Trip sudah tuntas</span>
        </article>
    </section>

    <article class="travs-section-card">
        <div class="travs-panel-title">
            <h3>Filter Booking</h3>
            <p>Cari booking berdasarkan kode, penumpang, rute, rentang tanggal, atau status pembayaran.</p>
        </div>

        <form method="GET" class="travs-filter-grid travs-filter-grid-wide">
            <div class="travs-field">
                <label for="search">Cari</label>
                <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Kode booking, nama penumpang, atau rute">
            </div>
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
                <label for="payment_status">Status Pembayaran</label>
                <select id="payment_status" name="payment_status" class="travs-select">
                    <option value="">Semua status</option>
                    @foreach (['menunggu' => 'Menunggu', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'kedaluwarsa' => 'Kedaluwarsa'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>
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
                <a href="{{ route('admin.pemesanan.index') }}" class="travs-button-secondary">Reset</a>
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
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pemesanan as $booking)
                        <tr>
                            <td>
                                <strong>{{ $booking->kode_booking }}</strong>
                                <div class="travs-table-subcopy">{{ $booking->created_at->translatedFormat('d M Y H:i') }}</div>
                            </td>
                            <td>
                                <div class="travs-person-cell">
                                    <div class="travs-avatar">
                                        @if ($booking->pengguna->foto_profil)
                                            <img class="travs-avatar-image travs-photo-preview-trigger" src="/storage/{{ ltrim($booking->pengguna->foto_profil, '/') }}" alt="Foto {{ $booking->pengguna->nama }}">
                                        @else
                                            <span>{{ strtoupper(mb_substr($booking->pengguna->nama, 0, 1)) }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <strong>{{ $booking->pengguna->nama }}</strong>
                                        <div class="travs-table-subcopy">{{ $booking->detail_pemesanan_count }} kursi</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $booking->jadwal->rute->kota_asal }} - {{ $booking->jadwal->rute->kota_tujuan }}</td>
                            <td>
                                <div class="travs-status-stack">
                                    <span class="travs-status-pill travs-status-{{ $booking->status_pemesanan }}">
                                        {{ ucwords(str_replace('_', ' ', $booking->status_pemesanan)) }}
                                    </span>
                                    <span class="travs-status-pill travs-status-{{ $booking->pembayaran?->status_bayar ?? 'draft' }}">
                                        {{ ucwords(str_replace('_', ' ', $booking->pembayaran?->status_bayar ?? 'draft')) }}
                                    </span>
                                    <span class="travs-status-pill travs-status-{{ $booking->penumpang_hadir_count >= $booking->detail_pemesanan_count && $booking->detail_pemesanan_count > 0 ? 'hadir' : 'menunggu' }}">
                                        Hadir {{ $booking->penumpang_hadir_count }}/{{ $booking->detail_pemesanan_count }}
                                    </span>
                                    <span class="travs-status-pill travs-status-{{ $booking->penumpang_turun_count >= $booking->detail_pemesanan_count && $booking->detail_pemesanan_count > 0 ? 'selesai' : 'menunggu' }}">
                                        Turun {{ $booking->penumpang_turun_count }}/{{ $booking->detail_pemesanan_count }}
                                    </span>
                                    <span class="travs-status-pill travs-status-{{ $booking->item_bagasi_count > 0 ? 'qris' : 'draft' }}">
                                        Bagasi {{ $booking->item_bagasi_count }} item
                                    </span>
                                </div>
                            </td>
                            <td>Rp{{ number_format($booking->total_harga, 0, ',', '.') }}</td>
                            <td>
                                <a href="{{ route('admin.pemesanan.show', $booking) }}" class="travs-button-link">Lihat Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="travs-table-empty">Belum ada data pemesanan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $pemesanan->links() }}
    </article>
@endsection
