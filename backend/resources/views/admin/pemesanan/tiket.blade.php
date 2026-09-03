@extends('admin.layouts.app')

@section('title', 'Tiket Elektronik')
@section('page-title', 'Tiket Elektronik Penumpang')
@section('page-description', 'Lihat dan unduh salinan tiket dari pemesanan penumpang.')

@section('content')
    @php
        $jadwal = $pemesanan->jadwal;
        $rute = $jadwal?->rute;
        $kendaraan = $jadwal?->kendaraan;
    @endphp

    <div class="travs-page-frame travs-ticket-admin-page">
        <div class="travs-detail-back-row">
            <a class="travs-back-link" href="{{ route('admin.pemesanan.show', $pemesanan) }}">
                <i data-feather="arrow-left" aria-hidden="true"></i>
                Kembali ke Detail Pemesanan
            </a>
        </div>

        <div class="travs-ticket-admin-heading">
            <div>
                <span class="travs-eyebrow">TIKET PENUMPANG</span>
                <h2>{{ $detail->nama_penumpang }}</h2>
                <p>{{ $tiket->kode_qr }}</p>
            </div>
            <button type="button" class="travs-button travs-button-primary btn btn-primary" data-ticket-download>
                <i data-feather="download" aria-hidden="true"></i>
                Unduh Tiket PNG
            </button>
        </div>

        <section class="travs-admin-ticket" data-admin-ticket data-auto-download="{{ $autoDownload ? 'true' : 'false' }}">
            <div class="travs-admin-ticket__route">
                <span class="travs-admin-ticket__label">Rusly Travel</span>
                <h3>{{ $rute?->kota_asal ?? '—' }} <span aria-hidden="true">→</span> {{ $rute?->kota_tujuan ?? '—' }}</h3>
                <dl>
                    <div>
                        <dt>Tanggal</dt>
                        <dd>{{ $jadwal?->waktu_berangkat?->translatedFormat('d F Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Waktu</dt>
                        <dd>{{ $jadwal?->waktu_berangkat?->format('H:i') ?? '—' }} WIB</dd>
                    </div>
                </dl>
            </div>

            <div class="travs-admin-ticket__detail">
                <span class="travs-admin-ticket__scan-status {{ $tiket->sudah_dipindai ? 'is-scanned' : '' }}">
                    {{ $tiket->sudah_dipindai ? 'Sudah dipindai' : 'Belum dipindai' }}
                </span>
                <h3>{{ $detail->nama_penumpang }}</h3>
                <div class="travs-admin-ticket__info-grid">
                    <div><span>Kursi</span><strong>{{ $detail->nomor_kursi }}</strong></div>
                    <div><span>Kode Pemesanan</span><strong>{{ $pemesanan->kode_booking }}</strong></div>
                    <div><span>Kendaraan</span><strong>{{ $kendaraan?->merk_kendaraan ?? '—' }}</strong></div>
                    <div><span>Nomor Polisi</span><strong>{{ $kendaraan?->nomor_plat ?? '—' }}</strong></div>
                </div>
            </div>

            <div class="travs-admin-ticket__qr">
                <canvas width="260" height="260" data-ticket-qr aria-label="QR Code tiket {{ $detail->nama_penumpang }}"></canvas>
                <strong>QR verifikasi tiket</strong>
                <small>Tunjukkan kepada pengemudi saat keberangkatan.</small>
            </div>
        </section>

        <p class="travs-ticket-download-error" data-ticket-error hidden>
            Tiket belum dapat dibuat menjadi gambar. Silakan muat ulang halaman dan coba lagi.
        </p>

        <script type="application/json" data-admin-ticket-payload>@json($downloadPayload)</script>
    </div>
@endsection
