@extends('admin.layouts.app')

@section('title', 'Detail Pemesanan')
@section('page-title', 'Detail Pemesanan')
@section('page-description', 'Lihat rincian pemesanan, pembayaran, dan penumpang.')

@section('content')
    @php
        $pembayaran = $pemesanan->pembayaran;
        $metodeBayar = $pembayaran?->metode_bayar;
        $metodeManualTransfer = ['transfer_manual', 'manual_transfer', 'bank_manual'];
        $isCashPayment = $metodeBayar === 'cash';
        $isGatewayPayment = $pembayaran && ! in_array($metodeBayar, array_merge(['cash'], $metodeManualTransfer), true);
        $cashVerificationReady = $isCashPayment
            && $pembayaran?->status_bayar === 'menunggu'
            && $pemesanan->status_pemesanan === 'menunggu_pembayaran';
        $paymentDetailLabel = match (true) {
            $isCashPayment && $pembayaran?->status_bayar === 'menunggu' => 'Lihat Detail Tunai',
            $isCashPayment => 'Lihat Detail Tunai',
            in_array($metodeBayar, $metodeManualTransfer, true) => 'Lihat Bukti Pembayaran',
            $isGatewayPayment => 'Lihat Detail Pembayaran',
            default => 'Lihat Detail Pembayaran',
        };
        $paymentDetailUrl = $pembayaran
            ? route('admin.pembayaran.show', $pembayaran)
            : route('admin.pembayaran.index', ['search' => $pemesanan->kode_booking]);
        $formatRupiah = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
        $storageUrl = fn (?string $path) => $path
            ? (str_starts_with($path, 'http') ? $path : asset('storage/'.ltrim($path, '/')))
            : null;
        $normalizeWhatsapp = function (?string $phone): ?string {
            $digits = preg_replace('/\D+/', '', (string) $phone);

            if ($digits === '') {
                return null;
            }

            if (str_starts_with($digits, '0')) {
                $digits = '62'.substr($digits, 1);
            } elseif (str_starts_with($digits, '8')) {
                $digits = '62'.$digits;
            }

            return strlen($digits) >= 10 ? $digits : null;
        };
        $bookingRoute = $pemesanan->jadwal->rute->kota_asal.' - '.$pemesanan->jadwal->rute->kota_tujuan;
        $totalManifest = $pemesanan->detailPemesanan->count();
        $totalHadir = $pemesanan->detailPemesanan
            ->where('status_kehadiran', 'hadir')
            ->count();
        $totalTurun = $pemesanan->detailPemesanan
            ->filter(fn ($detail) => $detail->diturunkan_pada !== null)
            ->count();
        $totalBagasi = $pemesanan->itemBagasi->count();
        $bagasiAman = $totalBagasi === 0 || $totalTurun >= $totalManifest;
        $kehadiranLengkap = $totalManifest > 0 && $totalHadir >= $totalManifest;
        $turunLengkap = $totalManifest > 0 && $totalTurun >= $totalManifest;
        $seatNumbers = $pemesanan->detailPemesanan
            ->pluck('nomor_kursi')
            ->filter()
            ->sort()
            ->implode(', ');
        $bookingStatusLabel = ucwords(str_replace('_', ' ', $pemesanan->status_pemesanan));
        $paymentStatusLabel = ucwords(str_replace('_', ' ', $pembayaran?->status_bayar ?? 'belum_tersedia'));
        $paymentMethodLabel = $pembayaran
            ? \App\Support\PaymentMethodCatalog::label($metodeBayar)
            : 'Belum dipilih';
        // Deadline hanya memakai waktu yang sudah tersimpan pada transaksi atau pemesanan.
        $paymentDeadline = $pembayaran?->expired_at ?? $pemesanan->batas_pembayaran;
        $departureTimezone = $pemesanan->jadwal->waktu_berangkat->timezoneName ?: config('app.timezone');
        $departureTimezoneLabel = match ($departureTimezone) {
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => 'WIB',
        };
        // Jika dibuka dari detail jadwal, tombol kembali tetap menuju jadwal asal.
        $backToSchedule = (int) request()->query('jadwal_id') === (int) $pemesanan->jadwal_id;
        $backUrl = $backToSchedule
            ? route('admin.jadwal.show', $pemesanan->jadwal)
            : route('admin.pemesanan.index');
        $backLabel = $backToSchedule
            ? 'Kembali ke Detail Jadwal'
            : 'Kembali ke Daftar Pemesanan';
    @endphp

    @include('admin.partials.page-back', [
        'url' => $backUrl,
        'label' => $backLabel,
    ])

    <div class="travs-admin-detail-simple travs-detail-page">
        <header class="travs-detail-page__header">
            <div>
                <span class="travs-detail-page__eyebrow">Kode {{ $pemesanan->kode_booking }}</span>
                <h2>{{ $bookingRoute }}</h2>
                <p>Dibuat {{ $pemesanan->created_at?->translatedFormat('d F Y, H:i') }}</p>
                <div class="travs-detail-statuses" aria-label="Status pemesanan dan pembayaran">
                    <span class="travs-status-pill travs-status-{{ $pemesanan->status_pemesanan }} badge rounded-pill">
                        Status Pemesanan: {{ $bookingStatusLabel }}
                    </span>
                    <span class="travs-status-pill travs-status-{{ $pembayaran?->status_bayar ?? 'draft' }} badge rounded-pill">
                        Status Pembayaran: {{ $paymentStatusLabel }}
                    </span>
                </div>
            </div>

            @if ($pembayaran)
                <div class="travs-detail-page__actions">
                    <a href="{{ $paymentDetailUrl }}" class="travs-button-secondary btn btn-outline-secondary">{{ $paymentDetailLabel }}</a>
                </div>
            @endif
        </header>

        @if ($pemesanan->isPemesananLoket())
            <div class="travs-staf-pelayanan-source-banner alert mb-0" role="status">
                <strong>Pemesanan Loket</strong>
                <span>Dicatat oleh Staf Pelayanan: {{ $pemesanan->dibuatOleh?->nama ?: 'Staf Pelayanan tidak tersedia' }}.</span>
            </div>
        @endif

        @include('admin.pemesanan.partials.informasi-utama')
        @include('admin.pemesanan.partials.rincian-pembayaran')
        @include('admin.pemesanan.partials.kapasitas-kursi')
        <section class="travs-detail-stack">
            @include('admin.pemesanan.partials.daftar-penumpang')
            @include('admin.pemesanan.partials.barang-bawaan')
        </section>
    </div>
@endsection
