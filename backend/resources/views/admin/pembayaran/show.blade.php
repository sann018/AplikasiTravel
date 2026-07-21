@extends('admin.layouts.app')

@section('title', 'Detail Pembayaran')
@section('page-title', 'Detail Pembayaran')
@section('page-description', 'Lihat rincian transaksi tanpa mengubah status pembayaran digital.')

@section('content')
    @php
        $booking = $payment->pemesanan;
        $routeName = $booking?->jadwal?->rute
            ? $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan
            : 'Rute tidak tersedia';
        $formatRupiah = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
    @endphp

    <section class="travs-detail-stack">
        <article class="travs-section-card card">
            <div class="travs-panel-title">
                <h3>Ringkasan Transaksi</h3>
                <p>Data ini dipakai admin untuk audit pembayaran, bukan untuk mengubah status gateway.</p>
            </div>

            <dl class="travs-description-list">
                <div>
                    <dt>Kode Pemesanan</dt>
                    <dd>{{ $booking?->kode_booking ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Penumpang</dt>
                    <dd>{{ $booking?->pengguna?->nama ?: 'Penumpang tidak tersedia' }}</dd>
                </div>
                <div>
                    <dt>Rute</dt>
                    <dd>{{ $routeName }}</dd>
                </div>
                <div>
                    <dt>Metode Bayar</dt>
                    <dd>{{ strtoupper($payment->metode_bayar) }}</dd>
                </div>
                <div>
                    <dt>Channel</dt>
                    <dd>{{ $payment->paid_channel ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Status Bayar</dt>
                    <dd>
                        <span class="travs-status-pill travs-status-{{ $payment->status_bayar }} badge rounded-pill">
                            {{ ucwords(str_replace('_', ' ', $payment->status_bayar)) }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt>Status Pemesanan</dt>
                    <dd>{{ ucwords(str_replace('_', ' ', $booking?->status_pemesanan ?? 'draft')) }}</dd>
                </div>
            </dl>
        </article>

        <article class="travs-section-card card">
            <div class="travs-panel-title">
                <h3>Nominal dan Waktu</h3>
                <p>Informasi nominal, batas pembayaran, dan waktu pembayaran yang tercatat.</p>
            </div>

            <dl class="travs-description-list">
                <div>
                    <dt>Jumlah Bayar</dt>
                    <dd>{{ $formatRupiah($payment->jumlah_bayar) }}</dd>
                </div>
                <div>
                    <dt>Batas Pembayaran</dt>
                    <dd>{{ $payment->expired_at ? $payment->expired_at->translatedFormat('d F Y H:i') : '-' }}</dd>
                </div>
                <div>
                    <dt>Dibayar Pada</dt>
                    <dd>{{ $payment->dibayar_pada ? $payment->dibayar_pada->translatedFormat('d F Y H:i') : '-' }}</dd>
                </div>
                <div>
                    <dt>Webhook Diverifikasi</dt>
                    <dd>{{ $payment->webhook_verified_at ? $payment->webhook_verified_at->translatedFormat('d F Y H:i') : '-' }}</dd>
                </div>
                <div>
                    <dt>Dibuat Pada</dt>
                    <dd>{{ $payment->created_at?->translatedFormat('d F Y H:i') }}</dd>
                </div>
                <div>
                    <dt>Diperbarui Pada</dt>
                    <dd>{{ $payment->updated_at?->translatedFormat('d F Y H:i') }}</dd>
                </div>
            </dl>
        </article>

        <div class="travs-form-actions travs-inline d-flex justify-content-end align-items-center flex-wrap">
            @if ($booking)
                <a href="{{ route('admin.pemesanan.show', $booking) }}" class="travs-button-secondary btn btn-secondary">Kembali ke Pemesanan</a>
            @endif
            <a href="{{ route('admin.pembayaran.index') }}" class="travs-button-secondary btn btn-secondary">Daftar Pembayaran</a>

            @if ($payment->can_confirm_cash && $booking)
                <form method="POST" action="{{ route('admin.pembayaran.confirm-cash', $booking) }}">
                    @csrf
                    <button type="submit" class="travs-button btn btn-primary">Konfirmasi Pembayaran Tunai</button>
                </form>
            @endif
        </div>
    </section>
@endsection
