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
