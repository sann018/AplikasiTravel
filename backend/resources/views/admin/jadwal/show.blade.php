@extends('admin.layouts.app')

@section('title', 'Detail Jadwal')
@section('page-title', 'Detail Jadwal Perjalanan')
@section('page-description', 'Ringkasan operasional, penumpang, pendapatan, dan biaya pada satu jadwal perjalanan.')

@section('content')
    @php
        $formatRupiah = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
        $routeLabel = $jadwal->rute->kota_asal.' - '.$jadwal->rute->kota_tujuan;
        $departureTimezone = $jadwal->waktu_berangkat?->timezoneName ?: config('app.timezone');
        $departureTimezoneLabel = match ($departureTimezone) {
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => 'WIB',
        };
        $scheduleStatusLabel = ucwords(str_replace('_', ' ', $jadwal->status));
        $scheduleStatusTone = match ($jadwal->status) {
            'ditutup' => 'menunggu',
            default => $jadwal->status,
        };
        // Nilai tetap berasal dari controller; teks ini hanya memberi konteks surplus atau defisit.
        $balanceContext = match (true) {
            $summary['recorded_balance'] < 0 => 'Defisit',
            $summary['recorded_balance'] > 0 => 'Surplus',
            default => 'Seimbang',
        };
    @endphp

    @include('admin.partials.page-back', [
        'url' => route('admin.jadwal.index'),
        'label' => 'Kembali ke Daftar Jadwal',
    ])

    <div class="travs-admin-detail-simple travs-detail-page">
    <header class="travs-detail-page__header">
        <div>
            <span class="travs-detail-page__eyebrow">Jadwal #{{ $jadwal->id }}</span>
            <h2>{{ $routeLabel }}</h2>
            <p>{{ $jadwal->waktu_berangkat?->translatedFormat('d F Y, H:i') }} {{ $departureTimezoneLabel }}</p>
            <div class="travs-detail-statuses" aria-label="Status jadwal">
                <span class="travs-status-pill travs-status-{{ $scheduleStatusTone }} badge rounded-pill">{{ $scheduleStatusLabel }}</span>
            </div>
        </div>
        <div class="travs-detail-page__actions">
            <a href="{{ route('admin.jadwal.edit', $jadwal) }}" class="travs-button btn btn-primary">Edit Jadwal</a>
        </div>
    </header>

    <section class="travs-detail-card-grid">
        <article class="travs-section-card card">
            <div class="travs-panel-title">
                <h3>Ringkasan Jadwal</h3>
                <p>Informasi utama perjalanan untuk rute {{ $routeLabel }}.</p>
            </div>

            <dl class="travs-detail-data-list">
                <div>
                    <dt>Rute</dt>
                    <dd>{{ $routeLabel }}</dd>
                </div>
                <div>
                    <dt>Waktu Berangkat</dt>
                    <dd>{{ $jadwal->waktu_berangkat?->translatedFormat('d M Y H:i') }} {{ $departureTimezoneLabel }}</dd>
                </div>
                <div>
                    <dt>Estimasi Tiba</dt>
                    <dd>{{ $jadwal->estimasi_tiba?->translatedFormat('d M Y H:i') ?? '-' }} {{ $jadwal->estimasi_tiba ? $departureTimezoneLabel : '' }}</dd>
                </div>
                <div>
                    <dt>Armada</dt>
                    <dd>
                        <strong>{{ $jadwal->kendaraan->label_armada }}</strong>
                        <div class="travs-table-subcopy">{{ $jadwal->kendaraan->tipe_kendaraan ?: 'Tipe belum diisi' }}</div>
                    </dd>
                </div>
                <div>
                    <dt>Pengemudi</dt>
                    <dd>
                        <strong>{{ $jadwal->pengemudi->pengguna->nama }}</strong>
                        <div class="travs-table-subcopy">{{ $jadwal->pengemudi->pengguna->no_hp }} &middot; SIM {{ $jadwal->pengemudi->nomor_sim }}</div>
                    </dd>
                </div>
            </dl>
        </article>

        <article class="travs-section-card card">
            <div class="travs-panel-title">
                <h3>Status dan Keuangan</h3>
                <p>Ringkasan pemesanan, pendapatan, biaya, dan proses perjalanan pada jadwal ini.</p>
            </div>
            <dl class="travs-detail-data-list travs-detail-data-list-money">
                <div>
                    <dt>Pemesanan Berhasil</dt>
                    <dd>
                        <strong>{{ number_format($summary['bookings']['total']) }}</strong>
                        <span>{{ $summary['bookings']['terbayar'] }} terbayar &middot; {{ $summary['bookings']['selesai'] }} selesai</span>
                    </dd>
                </div>
                <div>
                    <dt>Pendapatan Diterima</dt>
                    <dd><strong>{{ $formatRupiah($summary['confirmed_revenue']) }}</strong></dd>
                </div>
                <div>
                    <dt>Biaya Operasional</dt>
                    <dd><strong>{{ $formatRupiah($summary['operational_cost']) }}</strong></dd>
                </div>
                <div>
                    <dt>Surplus/Defisit</dt>
                    <dd>
                        <strong>{{ $balanceContext }} &middot; {{ $formatRupiah($summary['recorded_balance']) }}</strong>
                        <span>Pendapatan dikurangi biaya operasional yang tercatat.</span>
                    </dd>
                </div>
            </dl>
        </article>
    </section>

    <section class="travs-section-card card">
        <div class="travs-panel-title">
            <h3>Ringkasan Operasional</h3>
            <p>Kondisi kursi dan penumpang pada jadwal ini.</p>
        </div>

        <div class="travs-detail-metric-grid">
            <div>
                <small>Kapasitas</small>
                <strong>{{ $summary['seats']['total'] }}</strong>
                <span>Total kursi jadwal</span>
            </div>
            <div>
                <small>Dipesan</small>
                <strong>{{ $summary['seats']['dipesan'] }}</strong>
                <span>Kursi terisi</span>
            </div>
            <div>
                <small>Tersedia</small>
                <strong>{{ $summary['seats']['tersedia'] }}</strong>
                <span>Kursi dapat dipesan</span>
            </div>
            <div>
                <small>Nonaktif</small>
                <strong>{{ $summary['seats']['nonaktif'] }}</strong>
                <span>Kursi tidak digunakan</span>
            </div>
            <div>
                <small>Hadir</small>
                <strong>{{ $summary['passengers']['hadir'] }}/{{ $summary['passengers']['total'] }}</strong>
                <span>{{ $summary['passengers']['menunggu'] }} belum hadir</span>
            </div>
            <div>
                <small>Tidak Hadir</small>
                <strong>{{ $summary['passengers']['tidak_hadir'] }}</strong>
                <span>Penumpang tercatat</span>
            </div>
            <div>
                <small>Sudah Turun</small>
                <strong>{{ $summary['passengers']['turun'] }}/{{ $summary['passengers']['total'] }}</strong>
                <span>Status perjalanan penumpang</span>
            </div>
        </div>
    </section>

    <section class="travs-detail-stack">
        <article class="travs-section-card card">
            <div class="travs-panel-header d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="travs-panel-title">
                    <h3>Pemesanan dan Penumpang</h3>
                    <p>Hanya pemesanan dengan pembayaran berhasil. Tarif memakai subtotal historis pemesanan.</p>
                </div>
                <a href="{{ route('admin.pemesanan.index') }}" class="travs-button-secondary btn btn-outline-secondary">Lihat Semua Pemesanan &rarr;</a>
            </div>

            <div class="travs-table-wrap table-responsive">
                <table class="travs-table table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Pemesanan</th>
                            <th>Penumpang &amp; Kursi</th>
                            <th class="text-end">Tarif</th>
                            <th class="text-center">Status Pemesanan</th>
                            <th class="text-center">Pembayaran</th>
                            <th>Kehadiran</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($successfulBookings as $booking)
                            @php
                                $passengerCount = max(1, (int) $booking->jumlah_kursi);
                                $historicalFare = (float) $booking->subtotal_tiket / $passengerCount;
                                $presentCount = $booking->detailPemesanan->where('status_kehadiran', 'hadir')->count();
                                $absentCount = $booking->detailPemesanan->where('status_kehadiran', 'tidak_hadir')->count();
                                $droppedCount = $booking->detailPemesanan->whereNotNull('diturunkan_pada')->count();
                                $paymentStatus = $booking->pembayaran?->status_bayar ?? 'belum_tersedia';
                                $paymentStatusTone = $booking->pembayaran ? $paymentStatus : 'draft';
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $booking->kode_booking }}</strong>
                                    <div class="travs-table-subcopy">{{ $booking->pengguna->nama }}</div>
                                </td>
                                <td>
                                    @forelse ($booking->detailPemesanan->sortBy('nomor_kursi') as $passenger)
                                        @php
                                            $passengerStatus = $passenger->diturunkan_pada
                                                ? 'selesai'
                                                : $passenger->status_kehadiran;
                                            $passengerStatusLabel = $passenger->diturunkan_pada
                                                ? 'Sudah turun'
                                                : match ($passenger->status_kehadiran) {
                                                    'hadir' => 'Hadir',
                                                    'tidak_hadir' => 'Tidak hadir',
                                                    default => 'Belum hadir',
                                                };
                                        @endphp
                                        <div class="mb-2">
                                            <strong>Kursi {{ $passenger->nomor_kursi }} &middot; {{ $passenger->nama_penumpang }}</strong>
                                            <div class="travs-table-subcopy">{{ $passenger->no_hp_penumpang }}</div>
                                            <span class="travs-status-pill travs-status-{{ $passengerStatus }} badge rounded-pill mt-1">{{ $passengerStatusLabel }}</span>
                                        </div>
                                    @empty
                                        <span class="travs-table-subcopy">Belum ada detail penumpang.</span>
                                    @endforelse
                                </td>
                                <td class="text-end">
                                    <strong>{{ $formatRupiah($historicalFare) }}/orang</strong>
                                    <div class="travs-table-subcopy">Total {{ $formatRupiah($booking->total_harga) }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="travs-status-pill travs-status-{{ $booking->status_pemesanan }} badge rounded-pill">
                                        {{ ucwords(str_replace('_', ' ', $booking->status_pemesanan)) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="travs-status-pill travs-status-{{ $paymentStatusTone }} badge rounded-pill">
                                        {{ ucwords(str_replace('_', ' ', $paymentStatus)) }}
                                    </span>
                                    @if ($booking->pembayaran)
                                        <div class="travs-table-subcopy mt-1">{{ \App\Support\PaymentMethodCatalog::label($booking->pembayaran->metode_bayar) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $presentCount }} hadir</strong>
                                    <div class="travs-table-subcopy">{{ $absentCount }} tidak hadir &middot; {{ $droppedCount }} turun</div>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.pemesanan.show', ['pemesanan' => $booking, 'jadwal_id' => $jadwal->id]) }}" class="travs-button-link btn btn-link">Detail Pemesanan</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="travs-table-empty">Belum ada pemesanan berhasil pada jadwal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="travs-section-card card">
            <div class="travs-panel-header d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="travs-panel-title">
                    <h3>Biaya Operasional</h3>
                    <p>Biaya final yang tercatat khusus untuk jadwal ini.</p>
                </div>
                <a href="{{ route('admin.biaya.index', ['rute_id' => $jadwal->rute_id]) }}" class="travs-button-secondary btn btn-outline-secondary">Lihat Semua Biaya &rarr;</a>
            </div>

            <div class="travs-table-wrap table-responsive">
                <table class="travs-table table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Biaya</th>
                            <th>Pencatat</th>
                            <th>Keterangan</th>
                            <th class="text-end">Nominal</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jadwal->biayaOperasional as $cost)
                            <tr>
                                <td>{{ $cost->tanggal_biaya?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td><strong>{{ $cost->jenis_biaya }}</strong></td>
                                <td>{{ $cost->pencatat?->nama ?? '-' }}</td>
                                <td>{{ $cost->keterangan ?: '-' }}</td>
                                <td class="text-end"><strong>{{ $formatRupiah($cost->nominal) }}</strong></td>
                                <td class="text-end"><a href="{{ route('admin.biaya.edit', $cost) }}" class="travs-button-link btn btn-link">Buka Data</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="travs-table-empty">Belum ada biaya operasional pada jadwal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">Total Biaya Operasional</th>
                            <th colspan="2" class="text-end">{{ $formatRupiah($summary['operational_cost']) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </article>
    </section>
    </div>
@endsection
