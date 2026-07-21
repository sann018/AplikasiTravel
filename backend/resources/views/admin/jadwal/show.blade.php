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
    @endphp

    <div class="travs-admin-detail-simple">
    <section class="travs-detail-grid">
        <article class="travs-section-card card">
            <div class="travs-panel-title">
                <h3>Ringkasan Jadwal</h3>
                <p>Informasi utama perjalanan untuk rute {{ $routeLabel }}.</p>
            </div>

            <dl class="travs-description-list travs-description-list-neutral">
                <div>
                    <dt>Rute</dt>
                    <dd>{{ $routeLabel }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd><span class="travs-status-pill travs-status-{{ $scheduleStatusTone }} badge rounded-pill">{{ $scheduleStatusLabel }}</span></dd>
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
            <div class="travs-table-wrap table-responsive">
                <table class="travs-table table mb-0">
                    <tbody>
                        <tr>
                            <th>Status Jadwal</th>
                            <td><span class="travs-status-pill travs-status-{{ $scheduleStatusTone }} badge rounded-pill">{{ $scheduleStatusLabel }}</span></td>
                        </tr>
                        <tr>
                            <th>Pemesanan</th>
                            <td>
                                <strong>{{ number_format($summary['bookings']['total']) }}</strong>
                                <div class="travs-table-subcopy">
                                    {{ $summary['bookings']['menunggu_pembayaran'] }} menunggu pembayaran /
                                    {{ $summary['bookings']['terbayar'] }} terbayar /
                                    {{ $summary['bookings']['selesai'] }} selesai /
                                    {{ $summary['bookings']['dibatalkan'] }} dibatalkan
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>Pendapatan Diterima</th>
                            <td><strong>{{ $formatRupiah($summary['confirmed_revenue']) }}</strong></td>
                        </tr>
                        <tr>
                            <th>Menunggu Pembayaran</th>
                            <td>{{ $formatRupiah($summary['pending_value']) }}</td>
                        </tr>
                        <tr>
                            <th>Biaya Operasional</th>
                            <td>{{ $formatRupiah($summary['operational_cost']) }}</td>
                        </tr>
                        <tr>
                            <th>Selisih Tercatat</th>
                            <td><strong>{{ $formatRupiah($summary['recorded_balance']) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="travs-operational-summary travs-operational-summary-neutral">
                <div>
                    <small>Kursi</small>
                    <strong>{{ $summary['seats']['tersedia'] }} tersedia</strong>
                    <span>{{ $summary['seats']['dipesan'] }} dipesan / {{ $summary['seats']['nonaktif'] }} nonaktif</span>
                </div>
                <div>
                    <small>Kehadiran</small>
                    <strong>{{ $summary['passengers']['hadir'] }}/{{ $summary['passengers']['total'] }}</strong>
                    <span>{{ $summary['passengers']['menunggu'] }} belum hadir / {{ $summary['passengers']['tidak_hadir'] }} tidak hadir</span>
                </div>
                <div @class(['is-complete' => $summary['passengers']['total'] > 0 && $summary['passengers']['turun'] >= $summary['passengers']['total']])>
                    <small>Status Turun</small>
                    <strong>{{ $summary['passengers']['turun'] }}/{{ $summary['passengers']['total'] }}</strong>
                    <span>{{ $summary['passengers']['turun'] >= $summary['passengers']['total'] && $summary['passengers']['total'] > 0 ? 'Semua turun' : 'Belum lengkap' }}</span>
                </div>
            </div>

            <div class="travs-form-actions travs-inline d-flex justify-content-end align-items-center flex-wrap">
                <a href="{{ route('admin.jadwal.index') }}" class="travs-button-secondary btn btn-secondary">Kembali ke Daftar</a>
                <a href="{{ route('admin.jadwal.edit', $jadwal) }}" class="travs-button btn btn-primary">Edit Jadwal</a>
            </div>
        </article>
    </section>

    <section class="travs-detail-stack">
        <article class="travs-section-card card">
            <div class="travs-panel-header d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="travs-panel-title">
                    <h3>Pemesanan dan Penumpang</h3>
                    <p>Tarif per penumpang berasal dari subtotal historis pemesanan, bukan harga rute saat ini.</p>
                </div>
                <a href="{{ route('admin.pemesanan.index') }}" class="travs-button-secondary btn btn-secondary">Daftar Pemesanan</a>
            </div>

            <div class="travs-table-wrap table-responsive">
                <table class="travs-table table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Pemesanan</th>
                            <th>Penumpang &amp; Kursi</th>
                            <th>Tarif</th>
                            <th>Pemesanan</th>
                            <th>Pembayaran</th>
                            <th>Kehadiran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jadwal->pemesanan as $booking)
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
                                <td>
                                    <strong>{{ $formatRupiah($historicalFare) }}/orang</strong>
                                    <div class="travs-table-subcopy">Total {{ $formatRupiah($booking->total_harga) }}</div>
                                </td>
                                <td>
                                    <span class="travs-status-pill travs-status-{{ $booking->status_pemesanan }} badge rounded-pill">
                                        {{ ucwords(str_replace('_', ' ', $booking->status_pemesanan)) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="travs-status-pill travs-status-{{ $paymentStatusTone }} badge rounded-pill">
                                        {{ ucwords(str_replace('_', ' ', $paymentStatus)) }}
                                    </span>
                                    @if ($booking->pembayaran)
                                        <div class="travs-table-subcopy mt-1">{{ strtoupper($booking->pembayaran->metode_bayar) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $presentCount }} hadir</strong>
                                    <div class="travs-table-subcopy">{{ $absentCount }} tidak hadir &middot; {{ $droppedCount }} turun</div>
                                </td>
                                <td>
                                    <a href="{{ route('admin.pemesanan.show', $booking) }}" class="travs-button-link btn btn-link">Detail Pemesanan</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="travs-table-empty">Belum ada pemesanan pada jadwal ini.</td>
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
                <a href="{{ route('admin.biaya.index', ['rute_id' => $jadwal->rute_id]) }}" class="travs-button-secondary btn btn-secondary">Daftar Biaya</a>
            </div>

            <div class="travs-table-wrap table-responsive">
                <table class="travs-table table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Biaya</th>
                            <th>Pencatat</th>
                            <th>Keterangan</th>
                            <th>Nominal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jadwal->biayaOperasional as $cost)
                            <tr>
                                <td>{{ $cost->tanggal_biaya?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td><strong>{{ $cost->jenis_biaya }}</strong></td>
                                <td>{{ $cost->pencatat?->nama ?? '-' }}</td>
                                <td>{{ $cost->keterangan ?: '-' }}</td>
                                <td><strong>{{ $formatRupiah($cost->nominal) }}</strong></td>
                                <td><a href="{{ route('admin.biaya.edit', $cost) }}" class="travs-button-link btn btn-link">Buka Data</a></td>
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
                            <th colspan="2">{{ $formatRupiah($summary['operational_cost']) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </article>
    </section>
    </div>
@endsection
