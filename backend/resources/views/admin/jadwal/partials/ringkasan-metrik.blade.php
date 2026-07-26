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
