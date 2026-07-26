@extends('admin.layouts.app')

@section('title', 'Dasbor')
@section('page-title', 'Dasbor')
@section('page-description', 'Ringkasan operasional harian.')

@section('content')
    @php
        $today = now()->toDateString();
    @endphp

    <div class="travs-dashboard-simple">
        <section aria-labelledby="dashboard-critical-title">
            <div class="travs-panel-title mb-3">
                <h3 id="dashboard-critical-title">Ringkasan Utama</h3>
                <p>Informasi krusial untuk memantau transaksi dan keberangkatan hari ini.</p>
            </div>

            <div class="travs-summary-grid">
                <a href="{{ route('admin.laporan.transaksi') }}" class="travs-summary-card travs-summary-link card">
                    <small>Pendapatan Minggu Ini</small>
                    <strong>Rp{{ number_format($stats['pendapatan_minggu_ini'], 0, ',', '.') }}</strong>
                    <span>Nominal transaksi berhasil, tanpa biaya admin provider.</span>
                </a>
                <a href="{{ route('admin.laporan.pemesanan', ['status' => 'terbayar']) }}" class="travs-summary-card travs-summary-link card">
                    <small>Pemesanan Berhasil Bulan Ini</small>
                    <strong>{{ number_format($stats['pemesanan_berhasil_bulan_ini'], 0, ',', '.') }}</strong>
                    <span>Pembayaran sah pada {{ now()->translatedFormat('F Y') }}.</span>
                </a>
                <a href="{{ route('admin.pemesanan.index', ['status' => 'menunggu_pembayaran']) }}" class="travs-summary-card travs-summary-link card">
                    <small>Menunggu Pembayaran</small>
                    <strong>{{ number_format($stats['booking_menunggu'], 0, ',', '.') }}</strong>
                    <span>Pemesanan yang masih membutuhkan penyelesaian pembayaran.</span>
                </a>
                <a href="{{ route('admin.jadwal.index', ['tanggal' => $today]) }}" class="travs-summary-card travs-summary-link card">
                    <small>Keberangkatan Hari Ini</small>
                    <strong>{{ number_format($stats['jadwal_hari_ini'], 0, ',', '.') }}</strong>
                    <span>Jadwal pada {{ now()->translatedFormat('d F Y') }}.</span>
                </a>
            </div>
        </section>

        <section aria-labelledby="dashboard-operational-title">
            <div class="travs-panel-title mb-3">
                <h3 id="dashboard-operational-title">Kesiapan Operasional</h3>
                <p>Ringkasan sumber daya aktif dan biaya operasional hari ini.</p>
            </div>

            <div class="travs-summary-grid">
                <a href="{{ route('admin.kendaraan.index') }}" class="travs-summary-card travs-summary-link card">
                    <small>Armada Aktif</small>
                    <strong>{{ number_format($stats['armada_aktif'], 0, ',', '.') }}</strong>
                    <span>Dari {{ number_format($stats['total_armada'], 0, ',', '.') }} armada terdaftar.</span>
                </a>
                <a href="{{ route('admin.pengemudi.index') }}" class="travs-summary-card travs-summary-link card">
                    <small>Pengemudi Aktif</small>
                    <strong>{{ number_format($stats['pengemudi_aktif'], 0, ',', '.') }}</strong>
                    <span>Dari {{ number_format($stats['jumlah_pengemudi'], 0, ',', '.') }} pengemudi terdaftar.</span>
                </a>
                <a href="{{ route('admin.rute.index') }}" class="travs-summary-card travs-summary-link card">
                    <small>Rute Aktif</small>
                    <strong>{{ number_format($stats['rute_aktif'], 0, ',', '.') }}</strong>
                    <span>Rute yang tersedia untuk penyusunan jadwal.</span>
                </a>
                <a href="{{ route('admin.biaya.index') }}" class="travs-summary-card travs-summary-link card">
                    <small>Biaya Operasional Hari Ini</small>
                    <strong>Rp{{ number_format($stats['biaya_harian'], 0, ',', '.') }}</strong>
                    <span>Biaya terverifikasi pada {{ now()->translatedFormat('d F Y') }}.</span>
                </a>
            </div>
        </section>

        <article class="travs-dashboard-panel travs-dashboard-panel-full collapsible-section" data-collapsible-section>
            <div class="travs-panel-header collapsible-section__header d-flex align-items-start justify-content-between" data-collapse-header>
                <div class="travs-panel-title">
                    <h3>Ringkasan Pendapatan Minggu Ini</h3>
                    <p>
                        Periode {{ $pendapatanHarian->first()['date']->translatedFormat('d F Y') }} sampai
                        {{ $pendapatanHarian->last()['date']->translatedFormat('d F Y') }}. Total dihitung sampai hari ini.
                    </p>
                </div>
                <div class="collapsible-section__header-actions">
                    <div class="travs-dashboard-panel-chip travs-dashboard-panel-chip--square">
                        <strong>Rp{{ number_format($pendapatanHarian->sum('total'), 0, ',', '.') }}</strong>
                        <span>Total minggu ini</span>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-revenue-body'])
                </div>
            </div>

            <div id="dashboard-revenue-body" class="travs-dashboard-table-wrap collapsible-section__body table-responsive" data-collapse-body>
                <table class="travs-table table mb-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Hari</th>
                            <th>Tanggal</th>
                            <th>Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendapatanHarian as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item['date']->translatedFormat('l') }}</td>
                                <td>{{ $item['date']->translatedFormat('d F Y') }}</td>
                                <td>
                                    {{ $item['is_future'] ? '-' : 'Rp'.number_format($item['total'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Total Pendapatan Minggu Ini</th>
                            <th>Rp{{ number_format($pendapatanHarian->sum('total'), 0, ',', '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </article>

        <article class="travs-dashboard-panel travs-dashboard-panel-full collapsible-section" data-collapsible-section>
            <div class="travs-panel-header collapsible-section__header d-flex align-items-start justify-content-between" data-collapse-header>
                <div class="travs-panel-title">
                    <h3>Jadwal Keberangkatan Hari Ini</h3>
                    <p>Daftar keberangkatan pada tanggal {{ now()->translatedFormat('d F Y') }}.</p>
                </div>
                <div class="collapsible-section__header-actions">
                    <a href="{{ route('admin.jadwal.index', ['tanggal' => $today]) }}" class="travs-button-link btn btn-link">Kelola jadwal</a>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-schedule-body'])
                </div>
            </div>

            <div id="dashboard-schedule-body" class="travs-dashboard-table-wrap collapsible-section__body table-responsive" data-collapse-body>
                <table class="travs-table table mb-0">
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
