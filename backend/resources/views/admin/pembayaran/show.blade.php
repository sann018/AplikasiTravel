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
        $paymentStatusLabel = ucwords(str_replace('_', ' ', $payment->status_bayar));
        $bookingStatusLabel = ucwords(str_replace('_', ' ', $booking?->status_pemesanan ?? 'draft'));
        $paymentMethodLabel = \App\Support\PaymentMethodCatalog::label($payment->metode_bayar);
    @endphp

    @include('admin.partials.page-back', [
        'url' => $booking ? route('admin.pemesanan.show', $booking) : route('admin.pembayaran.index'),
        'label' => $booking ? 'Kembali ke Detail Pemesanan' : 'Kembali ke Daftar Pembayaran',
    ])

    <div class="travs-detail-page">
        <header class="travs-detail-page__header">
            <div>
                <span class="travs-detail-page__eyebrow">{{ $payment->order_id_gateway ?: 'Pembayaran #'.$payment->id }}</span>
                <h2>{{ $booking?->kode_booking ?: 'Pemesanan tidak tersedia' }}</h2>
                <p>{{ $routeName }}</p>
                <div class="travs-detail-statuses" aria-label="Status transaksi">
                    <span class="travs-status-pill travs-status-{{ $payment->status_bayar }} badge rounded-pill">
                        Pembayaran: {{ $paymentStatusLabel }}
                    </span>
                    <span class="travs-status-pill travs-status-{{ $booking?->status_pemesanan ?? 'draft' }} badge rounded-pill">
                        Pemesanan: {{ $bookingStatusLabel }}
                    </span>
                    @if ($payment->issue_label && $payment->issue_label !== 'Normal')
                        <span class="travs-status-pill travs-status-gagal badge rounded-pill">{{ $payment->issue_label }}</span>
                    @endif
                </div>
            </div>

            @if ($payment->can_confirm_cash && $booking)
                <div class="travs-detail-page__actions">
                    <form method="POST" action="{{ route('admin.pembayaran.confirm-cash', $booking) }}">
                        @csrf
                        <button type="submit" class="travs-button btn btn-primary">Konfirmasi Pembayaran Tunai</button>
                    </form>
                </div>
            @endif
        </header>

        <section class="travs-detail-card-grid">
            <article class="travs-section-card card">
                <div class="travs-panel-title">
                    <h3>Ringkasan Transaksi</h3>
                    <p>Identitas pemesanan dan channel pembayaran yang tercatat.</p>
                </div>

                <dl class="travs-detail-data-list">
                    <div>
                        <dt>Kode Pemesanan</dt>
                        <dd>{{ $booking?->kode_booking ?: '—' }}</dd>
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
                        <dt>Metode Pembayaran</dt>
                        <dd>{{ $paymentMethodLabel }}</dd>
                    </div>
                    <div>
                        <dt>Channel</dt>
                        <dd>{{ $payment->paid_channel ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Order ID Provider</dt>
                        <dd>{{ $payment->order_id_gateway ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Referensi Provider</dt>
                        <dd>{{ $payment->gateway_reference ?: '—' }}</dd>
                    </div>
                </dl>
            </article>

            <article class="travs-section-card card">
                <div class="travs-panel-title">
                    <h3>Rincian Pembayaran</h3>
                    <p>Nominal snapshot dan waktu transaksi dari database pembayaran.</p>
                </div>

                <dl class="travs-detail-data-list travs-detail-data-list-money">
                    <div>
                        <dt>Status Pembayaran</dt>
                        <dd>
                            <span class="travs-status-pill travs-status-{{ $payment->status_bayar }} badge rounded-pill">
                                {{ $paymentStatusLabel }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt>Nominal Transaksi</dt>
                        <dd class="travs-detail-value-end">{{ $payment->nominal_transaksi === null ? '—' : $formatRupiah($payment->nominal_transaksi) }}</dd>
                    </div>
                    <div>
                        <dt>Biaya Admin Provider</dt>
                        <dd class="travs-detail-value-end">{{ $payment->biaya_admin_provider === null ? '—' : $formatRupiah($payment->biaya_admin_provider) }}</dd>
                    </div>
                    <div class="is-total">
                        <dt>Total Dibayar Penumpang</dt>
                        <dd class="travs-detail-value-end">{{ $payment->total_dibayar_penumpang === null ? '—' : $formatRupiah($payment->total_dibayar_penumpang) }}</dd>
                    </div>
                    <div>
                        <dt>Batas Pembayaran</dt>
                        <dd>{{ $payment->expired_at?->translatedFormat('d F Y, H:i') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Dibayar Pada</dt>
                        <dd>{{ $payment->dibayar_pada?->translatedFormat('d F Y, H:i') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Webhook Diverifikasi</dt>
                        <dd>{{ $payment->webhook_verified_at?->translatedFormat('d F Y, H:i') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Dibuat Pada</dt>
                        <dd>{{ $payment->created_at?->translatedFormat('d F Y, H:i') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Diperbarui Pada</dt>
                        <dd>{{ $payment->updated_at?->translatedFormat('d F Y, H:i') ?: '—' }}</dd>
                    </div>
                </dl>
            </article>
        </section>

        <div class="travs-detail-footer-actions">
            <a href="{{ route('admin.pembayaran.index') }}" class="travs-button-secondary btn btn-outline-secondary">Lihat Daftar Pembayaran</a>
        </div>
    </div>
@endsection
