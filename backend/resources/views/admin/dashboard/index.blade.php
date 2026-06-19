@extends('admin.layouts.app')

@section('title', 'Dasbor')
@section('page-title', 'Dasbor')
@section('page-description', 'Ringkasan operasional harian.')

@section('content')
    @php
        $completionRate = $stats['jumlah_pengemudi'] > 0
            ? round(($stats['pengemudi_terverifikasi'] / max($stats['jumlah_pengemudi'], 1)) * 100)
            : 0;
        $maxPendapatan = max($pendapatanHarian->max('total'), 1);
    @endphp

    <section class="travs-summary-grid travs-summary-grid-dashboard">
        <article class="travs-summary-card">
            <small>Total Armada</small>
            <strong>{{ number_format($stats['total_armada']) }}</strong>
            <span>Unit terdaftar</span>
        </article>
        <article class="travs-summary-card">
            <small>Rute Aktif</small>
            <strong>{{ number_format($stats['rute_aktif']) }}</strong>
            <span>Lintasan tersedia</span>
        </article>
        <article class="travs-summary-card">
            <small>Jadwal Hari Ini</small>
            <strong>{{ number_format($stats['jadwal_hari_ini']) }}</strong>
            <span>Keberangkatan aktif</span>
        </article>
        <article class="travs-summary-card">
            <small>Pemesanan Hari Ini</small>
            <strong>{{ number_format($stats['pemesanan_hari_ini']) }}</strong>
            <span>Booking baru masuk</span>
        </article>
        <article class="travs-summary-card">
            <small>Booking Menunggu</small>
            <strong>{{ number_format($stats['booking_menunggu']) }}</strong>
            <span>Perlu follow up pembayaran</span>
        </article>
        <article class="travs-summary-card">
            <small>Driver Terverifikasi</small>
            <strong>{{ number_format($stats['pengemudi_terverifikasi']) }}</strong>
            <span>{{ $completionRate }}% dari total driver</span>
        </article>
        <article class="travs-summary-card">
            <small>Biaya Hari Ini</small>
            <strong>Rp{{ number_format($stats['biaya_harian'], 0, ',', '.') }}</strong>
            <span>Pengeluaran operasional</span>
        </article>
    </section>

    <section class="travs-dashboard-main-grid">
        <article class="travs-dashboard-panel travs-dashboard-panel-wide">
            <div class="travs-panel-header">
                <div class="travs-panel-title">
                    <h3>Ringkasan Pendapatan 7 Hari</h3>
                    <p>Grafik pendapatan harian.</p>
                </div>
                <div class="travs-dashboard-panel-chip">
                    <strong>Rp{{ number_format($pendapatanHarian->sum('total'), 0, ',', '.') }}</strong>
                    <span>Total 7 hari</span>
                </div>
            </div>

            <div class="travs-dashboard-chart-shell">
                @if ($pendapatanHarian->sum('total') > 0)
                    <div class="travs-dashboard-chart-bars">
                        @foreach ($pendapatanHarian as $item)
                            @php
                                $height = max(18, (int) round(($item['total'] / $maxPendapatan) * 176));
                            @endphp
                            <div class="travs-dashboard-chart-item">
                                <small>Rp{{ number_format($item['total'], 0, ',', '.') }}</small>
                                <div class="travs-dashboard-chart-track">
                                    <span class="travs-dashboard-chart-bar" style="height: {{ $height }}px;"></span>
                                </div>
                                <strong>{{ $item['label'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="travs-empty-state">
                        <div class="travs-empty-illustration"></div>
                        <strong>Belum ada pendapatan tercatat</strong>
                        <p>Grafik akan muncul setelah ada pembayaran berhasil.</p>
                    </div>
                @endif
            </div>
        </article>

        <article class="travs-dashboard-panel">
            <div class="travs-panel-header">
                <div class="travs-panel-title">
                    <h3>Fokus Hari Ini</h3>
                    <p>Hal yang perlu dicek hari ini.</p>
                </div>
            </div>

            <div class="travs-dashboard-focus-list">
                <div class="travs-dashboard-focus-item">
                    <span class="travs-status-pill travs-status-menunggu">Prioritas</span>
                    <strong>{{ number_format($stats['booking_menunggu']) }} booking menunggu pembayaran</strong>
                    <p>Cek pembayaran yang masih menunggu.</p>
                </div>
                <div class="travs-dashboard-focus-item">
                    <span class="travs-status-pill travs-status-terverifikasi">Driver</span>
                    <strong>{{ number_format($stats['pengemudi_terverifikasi']) }} driver sudah diverifikasi</strong>
                    <p>Gunakan pengemudi aktif untuk jadwal baru.</p>
                </div>
                <div class="travs-dashboard-focus-item">
                    <span class="travs-status-pill travs-status-qris">Operasional</span>
                    <strong>Biaya hari ini Rp{{ number_format($stats['biaya_harian'], 0, ',', '.') }}</strong>
                    <p>Catat biaya perjalanan terbaru.</p>
                </div>
            </div>
        </article>

        <article class="travs-dashboard-panel">
            <div class="travs-panel-header">
                <div class="travs-panel-title">
                    <h3>Pemesanan Terbaru</h3>
                    <p>Pemesanan terbaru.</p>
                </div>
                <a href="{{ route('admin.pemesanan.index') }}" class="travs-button-link">Lihat semua</a>
            </div>

            <div class="travs-dashboard-list">
                @forelse ($pemesananTerbaru as $booking)
                    <article class="travs-dashboard-list-item">
                        <div>
                            <strong>{{ $booking->kode_booking }}</strong>
                            <p>{{ $booking->jadwal->rute->kota_asal }} - {{ $booking->jadwal->rute->kota_tujuan }}</p>
                        </div>
                        <div class="travs-dashboard-list-meta">
                            <span class="travs-status-pill travs-status-{{ $booking->status_pemesanan }}">
                                {{ ucwords(str_replace('_', ' ', $booking->status_pemesanan)) }}
                            </span>
                            <small>{{ $booking->pengguna->nama }}</small>
                        </div>
                    </article>
                @empty
                    <p class="travs-empty-inline">Belum ada booking terbaru.</p>
                @endforelse
            </div>
        </article>

        <article class="travs-dashboard-panel">
            <div class="travs-panel-header">
                <div class="travs-panel-title">
                    <h3>Jadwal Keberangkatan Hari Ini</h3>
                    <p>Keberangkatan aktif hari ini.</p>
                </div>
                <a href="{{ route('admin.jadwal.index') }}" class="travs-button-link">Kelola jadwal</a>
            </div>

            <div class="travs-dashboard-table-wrap">
                <table class="travs-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Rute</th>
                            <th>Armada</th>
                            <th>Kursi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jadwalHariIni as $jadwal)
                            <tr>
                                <td>{{ $jadwal->waktu_berangkat->format('H:i') }}</td>
                                <td>{{ $jadwal->rute->kota_asal }} - {{ $jadwal->rute->kota_tujuan }}</td>
                                <td>{{ $jadwal->kendaraan->nomor_plat }}</td>
                                <td>{{ $jadwal->kursi->where('status_kursi', 'tersedia')->count() }} tersedia</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="travs-table-empty">Belum ada jadwal keberangkatan hari ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="travs-dashboard-panel">
            <div class="travs-panel-header">
                <div class="travs-panel-title">
                    <h3>Biaya Operasional Terbaru</h3>
                    <p>Biaya operasional terbaru.</p>
                </div>
                <a href="{{ route('admin.biaya.index') }}" class="travs-button-link">Input biaya</a>
            </div>

            <div class="travs-dashboard-list">
                @forelse ($biayaOperasionalTerbaru as $biaya)
                    <article class="travs-dashboard-list-item">
                        <div>
                            <strong>{{ $biaya->jenis_biaya }}</strong>
                            <p>{{ $biaya->jadwal->rute->kota_asal }} - {{ $biaya->jadwal->rute->kota_tujuan }}</p>
                        </div>
                        <div class="travs-dashboard-list-meta">
                            <strong>Rp{{ number_format($biaya->nominal, 0, ',', '.') }}</strong>
                            <small>{{ $biaya->tanggal_biaya->translatedFormat('d M Y H:i') }}</small>
                        </div>
                    </article>
                @empty
                    <p class="travs-empty-inline">Belum ada biaya operasional yang dicatat.</p>
                @endforelse
            </div>
        </article>
    </section>
@endsection
