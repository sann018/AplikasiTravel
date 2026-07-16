@extends('admin.layouts.app')

@section('title', 'Dasbor')
@section('page-title', 'Dasbor')
@section('page-description', 'Ringkasan operasional harian.')

@section('content')
    @php
        $today = now()->toDateString();
        $completionRate = $stats['jumlah_pengemudi'] > 0
            ? round(($stats['pengemudi_aktif'] / max($stats['jumlah_pengemudi'], 1)) * 100)
            : 0;
        $maxPendapatan = max($pendapatanHarian->max('total'), 1);
    @endphp

    <div class="travs-dashboard-simple">
        <section class="collapsible-section" data-collapsible-section>
            <div class="collapsible-section__header" data-collapse-header>
                <div class="travs-panel-title">
                    <h3>Ringkasan Operasional</h3>
                    <p>Angka utama yang paling sering dipantau admin.</p>
                </div>
                @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-summary-body'])
            </div>

            <div id="dashboard-summary-body" class="collapsible-section__body" data-collapse-body>
                <div class="travs-summary-grid travs-summary-grid-dashboard">
                    <a href="{{ route('admin.kendaraan.index') }}" class="travs-summary-card travs-summary-link" aria-label="Buka daftar seluruh armada">
                        <small>Total Armada</small>
                        <strong>{{ number_format($stats['total_armada']) }}</strong>
                        <span>Unit terdaftar</span>
                    </a>
                    <a href="{{ route('admin.rute.index', ['status' => 'aktif']) }}" class="travs-summary-card travs-summary-link" aria-label="Buka daftar rute aktif">
                        <small>Rute Aktif</small>
                        <strong>{{ number_format($stats['rute_aktif']) }}</strong>
                        <span>Lintasan tersedia</span>
                    </a>
                    <a href="{{ route('admin.pengemudi.index', ['status' => 'aktif']) }}" class="travs-summary-card travs-summary-link" aria-label="Buka pengemudi aktif">
                        <small>Driver Aktif</small>
                        <strong>{{ number_format($stats['pengemudi_aktif']) }}</strong>
                        <span>{{ $completionRate }}% dari total driver</span>
                    </a>
                    <a href="{{ route('admin.biaya.index', ['tanggal_mulai' => $today, 'tanggal_selesai' => $today]) }}" class="travs-summary-card travs-summary-link travs-summary-card-multi travs-summary-card-finance" aria-label="Buka ringkasan keuangan operasional hari ini">
                        <small>Keuangan Hari Ini</small>
                        <span class="travs-summary-card-row travs-summary-income">
                            <em>Pendapatan</em>
                            <strong>Rp{{ number_format($stats['pendapatan_harian'], 0, ',', '.') }}</strong>
                        </span>
                        <span class="travs-summary-card-row travs-summary-cost">
                            <em>Biaya</em>
                            <strong>Rp{{ number_format($stats['biaya_harian'], 0, ',', '.') }}</strong>
                        </span>
                        <span>Pemasukan dan pengeluaran operasional</span>
                    </a>
                    <a href="{{ route('admin.pemesanan.index', ['tanggal_mulai' => $today, 'tanggal_selesai' => $today]) }}" class="travs-summary-card travs-summary-link" aria-label="Buka pemesanan hari ini">
                        <small>Pemesanan Hari Ini</small>
                        <strong>{{ number_format($stats['pemesanan_hari_ini']) }}</strong>
                        <span>Pemesanan baru masuk</span>
                    </a>
                    <a href="{{ route('admin.pemesanan.index', ['status' => 'menunggu_pembayaran']) }}" class="travs-summary-card travs-summary-link" aria-label="Buka pemesanan yang menunggu pembayaran">
                        <small>Pemesanan Menunggu</small>
                        <strong>{{ number_format($stats['booking_menunggu']) }}</strong>
                        <span>Perlu tindak lanjut</span>
                    </a>
                    <a href="{{ route('admin.jadwal.index', ['tanggal' => $today]) }}" class="travs-summary-card travs-summary-link" aria-label="Buka jadwal hari ini">
                        <small>Jadwal Hari Ini</small>
                        <strong>{{ number_format($stats['jadwal_hari_ini']) }}</strong>
                        <span>Keberangkatan aktif</span>
                    </a>
                </div>
            </div>
        </section>

        <article class="travs-dashboard-panel travs-dashboard-panel-full collapsible-section" data-collapsible-section>
            <div class="travs-panel-header collapsible-section__header" data-collapse-header>
                <div class="travs-panel-title">
                    <span class="travs-eyebrow">Keuangan</span>
                    <h3>Ringkasan Pendapatan 7 Hari</h3>
                    <p>Nominal pembayaran berhasil dalam tujuh hari terakhir.</p>
                </div>
                <div class="collapsible-section__header-actions">
                    <div class="travs-dashboard-panel-chip">
                        <strong>Rp{{ number_format($pendapatanHarian->sum('total'), 0, ',', '.') }}</strong>
                        <span>Total 7 hari</span>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-revenue-body'])
                </div>
            </div>

            <div id="dashboard-revenue-body" class="travs-dashboard-chart-shell collapsible-section__body" data-collapse-body>
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
                        <strong>Belum ada pendapatan tercatat</strong>
                        <p>Grafik muncul setelah pembayaran berhasil.</p>
                    </div>
                @endif
            </div>
        </article>

        <article class="travs-dashboard-panel travs-dashboard-panel-full collapsible-section" data-collapsible-section>
            <div class="travs-panel-header collapsible-section__header" data-collapse-header>
                <div class="travs-panel-title">
                    <h3>Jadwal Keberangkatan Hari Ini</h3>
                    <p>Daftar keberangkatan pada tanggal {{ now()->translatedFormat('d F Y') }}.</p>
                </div>
                <div class="collapsible-section__header-actions">
                    <a href="{{ route('admin.jadwal.index', ['tanggal' => $today]) }}" class="travs-button-link">Kelola jadwal</a>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-schedule-body'])
                </div>
            </div>

            <div id="dashboard-schedule-body" class="travs-dashboard-table-wrap collapsible-section__body" data-collapse-body>
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
                                <td>{{ $jadwal->kendaraan->label_armada }}</td>
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
    </div>
@endsection

@push('scripts')
    @include('admin.partials.collapsible-section-script')
@endpush
