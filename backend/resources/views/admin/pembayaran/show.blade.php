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
        $gatewayResponse = is_array($payment->gateway_response)
            ? $payment->gateway_response
            : json_decode((string) $payment->gateway_response, true);
        $gatewayResponse = is_array($gatewayResponse) ? $gatewayResponse : [];
        $rawPayload = is_array($payment->raw_webhook_payload) ? $payment->raw_webhook_payload : [];
        $gatewayProvider = data_get($gatewayResponse, 'provider', 'Pakasir');
        $gatewayStatus = data_get($gatewayResponse, 'transaction_detail.status')
            ?? data_get($gatewayResponse, 'status')
            ?? $payment->status_bayar;
        $gatewayMethod = data_get($gatewayResponse, 'transaction_detail.payment_method')
            ?? data_get($gatewayResponse, 'payment_method')
            ?? $payment->metode_bayar;
        $gatewayFee = data_get($gatewayResponse, 'create_payment.fee')
            ?? data_get($gatewayResponse, 'fee');
        $isSandbox = data_get($gatewayResponse, 'transaction_detail.is_sandbox')
            ?? data_get($gatewayResponse, 'is_sandbox');
    @endphp

    <section class="travs-detail-stack">
        <article class="travs-section-card">
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
                        <span class="travs-status-pill travs-status-{{ $payment->status_bayar }}">
                            {{ ucwords(str_replace('_', ' ', $payment->status_bayar)) }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt>Status Pemesanan</dt>
                    <dd>{{ ucwords(str_replace('_', ' ', $booking?->status_pemesanan ?? 'draft')) }}</dd>
                </div>
                <div>
                    <dt>Catatan Status</dt>
                    <dd>{{ $payment->issue_label }}</dd>
                </div>
            </dl>
        </article>

        <article class="travs-section-card">
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

        <article class="travs-section-card">
            <div class="travs-panel-title">
                <h3>Akses Pembayaran</h3>
                <p>Referensi teknis gateway disimpan di sistem dan tidak ditampilkan agar data transaksi tetap aman.</p>
            </div>

            <dl class="travs-description-list">
                <div>
                    <dt>Invoice / Link Pembayaran</dt>
                    <dd>
                        @php
                            $invoiceUrl = data_get($gatewayResponse, 'payment_url')
                                ?? data_get($gatewayResponse, 'invoice_url')
                                ?? data_get($gatewayResponse, 'redirect_url');
                        @endphp

                        @if ($invoiceUrl)
                            <a href="{{ $invoiceUrl }}" target="_blank" rel="noopener" class="travs-button-link">Buka invoice</a>
                        @else
                            -
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Referensi Teknis</dt>
                    <dd>Disimpan aman di sistem</dd>
                </div>
            </dl>
        </article>

        <article class="travs-section-card">
            <div class="travs-panel-title">
                <h3>Data Teknis Gateway</h3>
                <p>Ringkasan aman untuk audit. Payload mentah tidak ditampilkan agar data teknis pembayaran tidak bocor.</p>
            </div>

            <dl class="travs-description-list">
                <div>
                    <dt>Provider</dt>
                    <dd>{{ strtoupper((string) $gatewayProvider) }}</dd>
                </div>
                <div>
                    <dt>Status Gateway</dt>
                    <dd>{{ ucwords(str_replace('_', ' ', (string) $gatewayStatus)) }}</dd>
                </div>
                <div>
                    <dt>Metode Gateway</dt>
                    <dd>{{ strtoupper((string) $gatewayMethod) }}</dd>
                </div>
                <div>
                    <dt>Biaya Gateway</dt>
                    <dd>{{ $gatewayFee !== null ? $formatRupiah($gatewayFee) : '-' }}</dd>
                </div>
                <div>
                    <dt>Mode Simulasi</dt>
                    <dd>{{ $isSandbox ? 'Ya' : 'Tidak' }}</dd>
                </div>
                <div>
                    <dt>Payload Teknis</dt>
                    <dd>{{ $gatewayResponse === [] && $rawPayload === [] ? 'Tidak ada data' : 'Tersimpan di sistem' }}</dd>
                </div>
            </dl>
        </article>

        <div class="travs-form-actions travs-inline">
            @if ($booking)
                <a href="{{ route('admin.pemesanan.show', $booking) }}" class="travs-button-secondary">Kembali ke Pemesanan</a>
            @endif
            <a href="{{ route('admin.pembayaran.index') }}" class="travs-button-secondary">Daftar Pembayaran</a>

            @if ($payment->can_confirm_cash && $booking)
                <form method="POST" action="{{ route('admin.pembayaran.confirm-cash', $booking) }}">
                    @csrf
                    <button type="submit" class="travs-button">Konfirmasi Pembayaran Tunai</button>
                </form>
            @endif
        </div>
    </section>
@endsection
