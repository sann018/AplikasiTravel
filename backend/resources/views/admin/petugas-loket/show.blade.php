@extends('admin.layouts.app')

@section('title', 'Detail Petugas Loket')
@section('page-title', 'Detail Petugas Loket')
@section('page-description', 'Informasi akun dan ringkasan pemesanan yang dicatat petugas.')

@section('content')
    @include('admin.partials.page-back', [
        'url' => route('admin.petugas-loket.index'),
        'label' => 'Kembali ke Daftar Petugas Loket',
    ])

    <div class="travs-detail-page">
        <header class="travs-detail-page__header">
            <div>
                <span class="travs-detail-page__eyebrow">Akun Petugas Loket</span>
                <h2>{{ $petugas->nama }}</h2>
                <p>Terdaftar sejak {{ $petugas->created_at?->translatedFormat('d F Y, H:i') }}.</p>
                <div class="travs-detail-statuses">
                    <span class="travs-status-pill travs-status-{{ $petugas->status_aktif ? 'aktif' : 'nonaktif' }} badge rounded-pill">
                        {{ $petugas->status_aktif ? 'Aktif' : 'Nonaktif' }}
                    </span>
                    <span class="travs-status-pill badge rounded-pill">
                        {{ $petugas->email_verified_at ? 'Email Terverifikasi' : 'Aktivasi Belum Selesai' }}
                    </span>
                </div>
            </div>
            <div class="travs-detail-page__actions">
                <a href="{{ route('admin.petugas-loket.edit', $petugas) }}" class="travs-button-secondary btn btn-outline-secondary">Edit Petugas</a>
            </div>
        </header>

        <section class="travs-summary-grid">
            <article class="travs-summary-card card">
                <span>Total Pemesanan</span>
                <strong>{{ number_format($statistik['total_pemesanan']) }}</strong>
                <small>Transaksi yang dicatat petugas ini.</small>
            </article>
            <article class="travs-summary-card card">
                <span>Kursi Dipesankan</span>
                <strong>{{ number_format($statistik['total_kursi']) }}</strong>
                <small>Total kursi untuk calon penumpang.</small>
            </article>
            <article class="travs-summary-card card">
                <span>Pemesanan Berhasil</span>
                <strong>{{ number_format($statistik['berhasil']) }}</strong>
                <small>Pemesanan berstatus terbayar atau selesai.</small>
            </article>
            <article class="travs-summary-card card">
                <span>Menunggu Pembayaran</span>
                <strong>{{ number_format($statistik['menunggu_pembayaran']) }}</strong>
                <small>Pemesanan yang masih perlu diselesaikan.</small>
            </article>
        </section>

        <section class="travs-detail-grid">
            <article class="travs-section-card card">
                <div class="travs-panel-title">
                    <span>INFORMASI AKUN</span>
                    <h3>Identitas Petugas</h3>
                </div>
                <dl class="travs-detail-data-list">
                    <div><dt>Nama Lengkap</dt><dd>{{ $petugas->nama }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $petugas->email }}</dd></div>
                    <div><dt>Nomor HP</dt><dd>{{ $petugas->no_hp }}</dd></div>
                    <div><dt>Status Akun</dt><dd>{{ $petugas->status_aktif ? 'Aktif' : 'Nonaktif' }}</dd></div>
                    <div><dt>Verifikasi Email</dt><dd>{{ $petugas->email_verified_at?->translatedFormat('d F Y, H:i') ?: 'Belum diverifikasi' }}</dd></div>
                    <div><dt>Pembaruan Terakhir</dt><dd>{{ $petugas->updated_at?->translatedFormat('d F Y, H:i') ?: '—' }}</dd></div>
                </dl>
            </article>
        </section>

        <section class="travs-section-card card">
            <div class="travs-panel-header d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div class="travs-panel-title">
                    <h3>Pemesanan Terbaru</h3>
                    <p>Maksimal 10 pemesanan terakhir yang dicatat oleh petugas ini.</p>
                </div>
                <a href="{{ route('admin.pemesanan.index', ['source' => 'loket', 'search' => $petugas->nama]) }}" class="travs-button-secondary btn btn-outline-secondary">Lihat Semua Pemesanan</a>
            </div>

            <div class="travs-table-wrap table-responsive">
                <table class="travs-table table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Kode Pemesanan</th>
                            <th>Rute</th>
                            <th class="text-center">Kursi</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pemesananTerbaru as $booking)
                            <tr>
                                <td>
                                    <strong>{{ $booking->kode_booking }}</strong>
                                    <div class="travs-table-subcopy">{{ $booking->created_at?->translatedFormat('d M Y H:i') }}</div>
                                </td>
                                <td>{{ $booking->jadwal?->rute?->kota_asal ?? '—' }} - {{ $booking->jadwal?->rute?->kota_tujuan ?? '—' }}</td>
                                <td class="text-center">{{ number_format($booking->jumlah_kursi) }}</td>
                                <td class="text-center">
                                    <span class="travs-status-pill travs-status-{{ $booking->status_pemesanan }} badge rounded-pill">
                                        {{ ucwords(str_replace('_', ' ', $booking->status_pemesanan)) }}
                                    </span>
                                </td>
                                <td class="text-end"><a href="{{ route('admin.pemesanan.show', $booking) }}" class="travs-button-link btn btn-link">Detail Pemesanan</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="travs-table-empty">Petugas ini belum mencatat pemesanan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
