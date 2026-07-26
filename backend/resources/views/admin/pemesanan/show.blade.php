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

        <section class="travs-detail-card-grid">
            <article class="travs-section-card card">
                <div class="travs-panel-title">
                    <h3>Informasi Perjalanan</h3>
                    <p>Jadwal, armada, pengemudi, serta lokasi perjalanan.</p>
                </div>

                <dl class="travs-detail-data-list">
                    <div>
                        <dt>Keberangkatan</dt>
                        <dd>
                            {{ $pemesanan->jadwal->waktu_berangkat->locale('id')->translatedFormat('l, d F Y') }}
                            pukul {{ $pemesanan->jadwal->waktu_berangkat->format('H:i') }} {{ $departureTimezoneLabel }}
                        </dd>
                    </div>
                    <div>
                        <dt>Kendaraan</dt>
                        <dd>
                            <strong>{{ $pemesanan->jadwal->kendaraan->merk_kendaraan }}</strong>
                            <span>{{ $pemesanan->jadwal->kendaraan->nomor_plat }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>Pengemudi</dt>
                        <dd>
                            <strong>{{ $pemesanan->jadwal->pengemudi->pengguna->nama }}</strong>
                            <span>{{ $pemesanan->jadwal->pengemudi->pengguna->no_hp }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>Titik Jemput</dt>
                        <dd>
                            <strong>{{ $pemesanan->alamat_jemput ?: '—' }}</strong>
                            <span>{{ $pemesanan->catatan_jemput ?: 'Patokan belum diisi' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>Titik Turun</dt>
                        <dd>
                            <strong>{{ $pemesanan->alamat_turun ?: '—' }}</strong>
                            <span>{{ $pemesanan->catatan_turun ?: 'Patokan belum diisi' }}</span>
                        </dd>
                    </div>
                </dl>
            </article>

            <article class="travs-section-card card">
                <div class="travs-panel-title">
                    <h3>Informasi Pemesan</h3>
                    <p>Identitas pemesan dan kursi yang tercatat.</p>
                </div>

                <dl class="travs-detail-data-list">
                    <div>
                        <dt>Nama Pemesan</dt>
                        <dd>
                            <div class="travs-person-cell d-flex align-items-center">
                                <div class="travs-avatar">
                                    @if ($pemesanan->pengguna->foto_profil)
                                        <img class="travs-avatar-image travs-photo-preview-trigger" src="{{ $storageUrl($pemesanan->pengguna->foto_profil) }}" alt="Foto {{ $pemesanan->pengguna->nama }}">
                                    @else
                                        <span>{{ strtoupper(mb_substr($pemesanan->pengguna->nama, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <strong>{{ $pemesanan->pengguna->nama }}</strong>
                            </div>
                        </dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>{{ $pemesanan->pengguna->email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Nomor HP</dt>
                        <dd>{{ $pemesanan->pengguna->no_hp ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Jumlah Kursi</dt>
                        <dd>{{ number_format($pemesanan->jumlah_kursi) }} kursi</dd>
                    </div>
                    <div>
                        <dt>Nomor Kursi</dt>
                        <dd>{{ $seatNumbers !== '' ? $seatNumbers : '—' }}</dd>
                    </div>
                </dl>
            </article>
        </section>

        <section class="travs-section-card card">
            <div class="travs-panel-header d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="travs-panel-title">
                    <h3>Rincian Pembayaran</h3>
                    <p>Snapshot nominal dan waktu yang tersimpan pada transaksi ini.</p>
                </div>
            </div>

            <dl class="travs-detail-data-list travs-detail-payment-list">
                <div>
                    <dt>Metode Pembayaran</dt>
                    <dd>{{ $paymentMethodLabel }}</dd>
                </div>
                <div>
                    <dt>Status Pembayaran</dt>
                    <dd>
                        <span class="travs-status-pill travs-status-{{ $pembayaran?->status_bayar ?? 'draft' }} badge rounded-pill">
                            {{ $paymentStatusLabel }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt>Nominal Transaksi</dt>
                    <dd class="travs-detail-value-end">{{ $pembayaran?->nominal_transaksi === null ? '—' : $formatRupiah($pembayaran->nominal_transaksi) }}</dd>
                </div>
                <div>
                    <dt>Biaya Admin Provider</dt>
                    <dd class="travs-detail-value-end">{{ $pembayaran?->biaya_admin_provider === null ? '—' : $formatRupiah($pembayaran->biaya_admin_provider) }}</dd>
                </div>
                <div class="is-total">
                    <dt>Total Dibayar Penumpang</dt>
                    <dd class="travs-detail-value-end">{{ $pembayaran?->total_dibayar_penumpang === null ? '—' : $formatRupiah($pembayaran->total_dibayar_penumpang) }}</dd>
                </div>
                <div>
                    <dt>Batas Pembayaran</dt>
                    <dd>{{ $paymentDeadline?->translatedFormat('d F Y, H:i') ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Dibayar Pada</dt>
                    <dd>{{ $pembayaran?->dibayar_pada?->translatedFormat('d F Y, H:i') ?: '—' }}</dd>
                </div>
            </dl>

            @if ($cashVerificationReady)
                <div class="travs-subsection">
                    <strong>Konfirmasi Pembayaran Tunai</strong>
                    <p class="travs-empty-inline">Gunakan aksi ini setelah pembayaran tunai benar-benar diterima.</p>
                    <form method="POST" action="{{ route('admin.pembayaran.confirm-cash', $pemesanan) }}">
                        @csrf
                        <button type="submit" class="travs-button btn btn-primary">Konfirmasi Pembayaran Tunai</button>
                    </form>
                </div>
            @endif
        </section>

        <section class="travs-section-card card">
            <div class="travs-panel-title">
                <h3>Ringkasan Operasional</h3>
                <p>Ringkasan kehadiran, penurunan, dan barang bawaan.</p>
            </div>
            <div class="travs-detail-metric-grid">
                <div @class(['is-complete' => $kehadiranLengkap])>
                    <small>Kehadiran</small>
                    <strong>{{ $totalHadir }}/{{ $totalManifest }}</strong>
                    <span>{{ $kehadiranLengkap ? 'Semua hadir' : 'Belum lengkap' }}</span>
                </div>
                <div @class(['is-complete' => $turunLengkap])>
                    <small>Status Turun</small>
                    <strong>{{ $totalTurun }}/{{ $totalManifest }}</strong>
                    <span>{{ $turunLengkap ? 'Semua turun' : 'Belum lengkap' }}</span>
                </div>
                <div>
                    <small>Barang Bawaan</small>
                    <strong>{{ $totalBagasi }} catatan</strong>
                    <span>{{ $bagasiAman ? 'Aman' : 'Perlu diperiksa' }}</span>
                </div>
            </div>
        </section>

        <section class="travs-detail-stack">
            <article class="travs-section-card collapsible-section card" data-collapsible-section data-collapse-id="pemesanan-penumpang-kursi">
                <div class="collapsible-section__header" data-collapse-header>
                    <div class="travs-panel-title">
                        <h3>Penumpang per Kursi</h3>
                        <p>Daftar penumpang, status kehadiran, status turun, dan tiket.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['target' => 'pemesanan-penumpang-kursi-body'])
                </div>

                <div id="pemesanan-penumpang-kursi-body" class="collapsible-section__body" data-collapse-body>
                    <div class="travs-table-wrap table-responsive">
                        <table class="travs-table table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Kursi</th>
                                    <th>Penumpang</th>
                                    <th>No. HP</th>
                                    <th class="text-center">Kehadiran</th>
                                    <th>Status Turun</th>
                                    <th>Tiket</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pemesanan->detailPemesanan as $detail)
                                    @php
                                        $whatsappNumber = $normalizeWhatsapp($detail->no_hp_penumpang);
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $detail->nomor_kursi }}</strong></td>
                                        <td>{{ $detail->nama_penumpang }}</td>
                                        <td>
                                            @if ($whatsappNumber)
                                                <a class="travs-contact-link" href="https://wa.me/{{ $whatsappNumber }}" target="_blank" rel="noopener">{{ $detail->no_hp_penumpang }}</a>
                                            @else
                                                {{ $detail->no_hp_penumpang ?: '—' }}
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="travs-status-pill travs-status-{{ $detail->status_kehadiran }} badge rounded-pill">
                                                {{ ucfirst(str_replace('_', ' ', $detail->status_kehadiran)) }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ $detail->diturunkan_pada ? 'Sudah turun' : 'Belum turun' }}</strong>
                                            <div class="travs-table-subcopy">{{ $detail->diturunkan_pada?->format('d M Y H:i') ?: '—' }}</div>
                                        </td>
                                        <td>{{ $detail->tiketElektronik?->kode_qr ?: 'Belum terbit' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="travs-table-empty">Belum ada data penumpang pada pemesanan ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>

            <article class="travs-section-card collapsible-section card" data-collapsible-section data-collapse-id="pemesanan-pembayaran-bagasi">
                <div class="collapsible-section__header" data-collapse-header>
                    <div class="travs-panel-title">
                        <h3>Barang Bawaan</h3>
                        <p>Catatan dan foto barang yang dibawa penumpang.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['target' => 'pemesanan-pembayaran-bagasi-body'])
                </div>

                <div id="pemesanan-pembayaran-bagasi-body" class="collapsible-section__body" data-collapse-body>
                    <div class="travs-bagasi-list">
                        @forelse ($pemesanan->itemBagasi as $item)
                            @php
                                $placementLabel = ($item->posisi_barang ?? 'masuk_bagasi') === 'dipegang_sendiri' ? 'Dipegang sendiri' : 'Masuk bagasi';
                                $photos = collect($item->foto_urls ?? []);
                                if ($item->foto_url) {
                                    $photos = $photos->prepend($item->foto_url);
                                }
                                $photos = $photos->filter()->unique()->values();
                            @endphp
                            <div class="travs-bagasi-item">
                                <div>
                                    <strong>{{ $item->deskripsi_item }}</strong>
                                    <p>{{ $placementLabel }}</p>
                                    <div class="travs-bagasi-photo-row">
                                        @forelse ($photos as $photo)
                                            <img
                                                class="travs-bagasi-thumb travs-photo-preview-trigger"
                                                src="{{ $storageUrl($photo) }}"
                                                alt="Foto barang bawaan {{ $loop->iteration }}"
                                                data-preview-src="{{ $storageUrl($photo) }}"
                                            >
                                        @empty
                                            <span class="travs-empty-inline">Foto barang belum tersedia.</span>
                                        @endforelse
                                    </div>
                                </div>
                                <span>Dicatat</span>
                            </div>
                        @empty
                            <p class="travs-empty-inline">Belum ada barang bawaan yang dicatat pada pemesanan ini.</p>
                        @endforelse
                    </div>
                </div>
            </article>
        </section>
    </div>
@endsection
