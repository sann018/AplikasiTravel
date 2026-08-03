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
                            <th>Bukti</th>
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
                                <td>
                                    @if ($cost->bukti_url)
                                        <a
                                            href="{{ asset('storage/'.ltrim($cost->bukti_url, '/')) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="travs-button-link btn btn-link"
                                        >Lihat Bukti</a>
                                    @else
                                        <span class="text-muted">Data lama tanpa bukti</span>
                                    @endif
                                </td>
                                <td class="text-end"><a href="{{ route('admin.biaya.edit', $cost) }}" class="travs-button-link btn btn-link">Buka Data</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="travs-table-empty">Belum ada biaya operasional pada jadwal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">Total Biaya Operasional</th>
                            <th colspan="3" class="text-end">{{ $formatRupiah($summary['operational_cost']) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </article>
