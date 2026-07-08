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
        $dashboardTabs = [
            'ringkasan' => 'Ringkasan',
            'keuangan' => 'Keuangan',
            'aktivitas' => 'Aktivitas',
        ];
    @endphp

    <div class="travs-dashboard-simple">
        <section class="travs-settings-tabs travs-dashboard-tabs collapsible-section mb-3" data-collapsible-section>
            <div class="collapsible-section__header" data-collapse-header>
                <div class="travs-settings-tabs-copy">
                    <span class="travs-eyebrow">Tampilan Dasbor</span>
                </div>
                @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-tabs-body'])
            </div>

            <div id="dashboard-tabs-body" class="travs-settings-tab-list collapsible-section__body" data-collapse-body role="tablist" aria-label="Bagian dasbor">
                @foreach ($dashboardTabs as $tabKey => $tabLabel)
                    <button type="button" class="travs-settings-tab {{ $loop->first ? 'is-active' : '' }}" data-dashboard-tab="{{ $tabKey }}" role="tab" aria-controls="dashboard-{{ $tabKey }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                        {{ $tabLabel }}
                    </button>
                @endforeach
            </div>
        </section>

        <div class="travs-dashboard-panes">
            <section id="dashboard-ringkasan" class="travs-dashboard-pane" data-dashboard-pane="ringkasan">
                <section class="collapsible-section" data-collapsible-section>
                    <div class="collapsible-section__header" data-collapse-header>
                        <div class="travs-panel-title">
                            <h3>Ringkasan Operasional</h3>
                            <p>Angka utama operasional hari ini.</p>
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
                    <a href="{{ route('admin.jadwal.index', ['tanggal' => $today]) }}" class="travs-summary-card travs-summary-link" aria-label="Buka jadwal hari ini">
                        <small>Jadwal Hari Ini</small>
                        <strong>{{ number_format($stats['jadwal_hari_ini']) }}</strong>
                        <span>Keberangkatan aktif</span>
                    </a>
                    <a href="{{ route('admin.pemesanan.index', ['tanggal_mulai' => $today, 'tanggal_selesai' => $today]) }}" class="travs-summary-card travs-summary-link" aria-label="Buka pemesanan hari ini">
                        <small>Pemesanan Hari Ini</small>
                        <strong>{{ number_format($stats['pemesanan_hari_ini']) }}</strong>
                        <span>Booking baru masuk</span>
                    </a>
                    <a href="{{ route('admin.pemesanan.index', ['status' => 'menunggu_pembayaran']) }}" class="travs-summary-card travs-summary-link" aria-label="Buka pemesanan yang menunggu pembayaran">
                        <small>Booking Menunggu</small>
                        <strong>{{ number_format($stats['booking_menunggu']) }}</strong>
                        <span>Perlu tindak lanjut</span>
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
                        </div>
                    </div>
                </section>

                <article class="travs-dashboard-panel travs-dashboard-panel-full collapsible-section" data-collapsible-section>
                    <div class="travs-panel-header collapsible-section__header" data-collapse-header>
                        <div class="travs-panel-title">
                            <span class="travs-eyebrow">Hari Ini</span>
                            <h3>Fokus Operasional</h3>
                            <p>Informasi yang paling perlu diperhatikan admin.</p>
                        </div>
                        @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-focus-body'])
                    </div>

                    <div id="dashboard-focus-body" class="travs-dashboard-focus-list travs-dashboard-focus-compact collapsible-section__body" data-collapse-body>
                        <a href="{{ route('admin.pemesanan.index', ['status' => 'menunggu_pembayaran']) }}" class="travs-dashboard-focus-item">
                            <span class="travs-status-pill travs-status-menunggu">Prioritas</span>
                            <strong>{{ number_format($stats['booking_menunggu']) }} booking menunggu pembayaran</strong>
                            <p>Buka daftar untuk memeriksa status pembayaran.</p>
                        </a>
                        <a href="{{ route('admin.pengemudi.index', ['status' => 'aktif']) }}" class="travs-dashboard-focus-item">
                            <span class="travs-status-pill travs-status-aktif">Driver</span>
                            <strong>{{ number_format($stats['pengemudi_aktif']) }} driver aktif</strong>
                            <p>Siap dipilih saat admin membuat jadwal.</p>
                        </a>
                        <a href="{{ route('admin.biaya.index', ['tanggal_mulai' => $today, 'tanggal_selesai' => $today]) }}" class="travs-dashboard-focus-item">
                            <span class="travs-status-pill travs-status-qris">Operasional</span>
                            <strong>Pendapatan Rp{{ number_format($stats['pendapatan_harian'], 0, ',', '.') }} · Biaya Rp{{ number_format($stats['biaya_harian'], 0, ',', '.') }}</strong>
                            <p>Buka ringkasan keuangan operasional hari ini.</p>
                        </a>
                    </div>
                </article>
            </section>

            <section id="dashboard-keuangan" class="travs-dashboard-pane" data-dashboard-pane="keuangan" hidden>
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
                            <h3>Biaya Operasional Terbaru</h3>
                            <p>Catatan pengeluaran yang terakhir dimasukkan.</p>
                        </div>
                        <div class="collapsible-section__header-actions">
                            <a href="{{ route('admin.biaya.index') }}" class="travs-button-link">Kelola biaya</a>
                            @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-latest-cost-body'])
                        </div>
                    </div>

                    <div id="dashboard-latest-cost-body" class="travs-dashboard-list collapsible-section__body" data-collapse-body>
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
                            <p class="travs-empty-inline">Belum ada biaya operasional.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <section id="dashboard-aktivitas" class="travs-dashboard-pane" data-dashboard-pane="aktivitas" hidden>
                <article class="travs-dashboard-panel travs-dashboard-panel-full collapsible-section" data-collapsible-section>
                    <div class="travs-panel-header collapsible-section__header" data-collapse-header>
                        <div class="travs-panel-title">
                            <span class="travs-eyebrow">Aktivitas</span>
                            <h3>Pemesanan Terbaru</h3>
                            <p>Lima pemesanan yang terakhir masuk.</p>
                        </div>
                        <div class="collapsible-section__header-actions">
                            <a href="{{ route('admin.pemesanan.index') }}" class="travs-button-link">Lihat semua</a>
                            @include('admin.partials.collapsible-toggle', ['targetId' => 'dashboard-latest-booking-body'])
                        </div>
                    </div>

                    <div id="dashboard-latest-booking-body" class="travs-dashboard-list collapsible-section__body" data-collapse-body>
                        @forelse ($pemesananTerbaru as $booking)
                            <a href="{{ route('admin.pemesanan.show', $booking) }}" class="travs-dashboard-list-item">
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
                            </a>
                        @empty
                            <p class="travs-empty-inline">Belum ada pemesanan terbaru.</p>
                        @endforelse
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
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.partials.collapsible-section-script')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const buttons = [...document.querySelectorAll('[data-dashboard-tab]')];
            const panes = [...document.querySelectorAll('[data-dashboard-pane]')];

            const openTab = (tabName) => {
                buttons.forEach((button) => {
                    const active = button.dataset.dashboardTab === tabName;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panes.forEach((pane) => pane.hidden = pane.dataset.dashboardPane !== tabName);
            };

            buttons.forEach((button) => button.addEventListener('click', () => {
                openTab(button.dataset.dashboardTab);
                window.history.replaceState(null, '', `#${button.dataset.dashboardTab}`);
            }));

            const initialTab = window.location.hash.replace('#', '');
            openTab(buttons.some((button) => button.dataset.dashboardTab === initialTab) ? initialTab : 'ringkasan');
        });
    </script>
@endpush
