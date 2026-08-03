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
                    <dt>Pendapatan Kotor RuslyTravel</dt>
                    <dd><strong>{{ $formatRupiah($summary['confirmed_revenue']) }}</strong></dd>
                </div>
                <div>
                    <dt>Total Biaya Operasional</dt>
                    <dd><strong>{{ $formatRupiah($summary['operational_cost']) }}</strong></dd>
                </div>
                <div>
                    <dt>Pendapatan Bersih</dt>
                    <dd>
                        <strong>{{ $formatRupiah($summary['recorded_balance']) }}</strong>
                        <span>
                            {{ $formatRupiah($summary['confirmed_revenue']) }}
                            &minus; {{ $formatRupiah($summary['operational_cost']) }}
                            &middot; {{ $balanceContext }}
                        </span>
                    </dd>
                </div>
            </dl>
        </article>
    </section>
