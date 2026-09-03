            <article class="travs-section-card collapsible-section card" data-collapsible-section data-collapse-id="pemesanan-penumpang-kursi">
                <div class="collapsible-section__header" data-collapse-header>
                    <div class="travs-panel-title">
                        <h3>Penumpang per Kursi</h3>
                        <p>Daftar penumpang, status kehadiran, status turun, dan tiket.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['target' => 'pemesanan-penumpang-kursi-body'])
                </div>

                <div id="pemesanan-penumpang-kursi-body" class="collapsible-section__body" data-collapse-body>
                    <div class="travs-table-wrap table-responsive">
                        <table class="travs-table table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Kursi</th>
                                    <th>Penumpang</th>
                                    <th>No. HP</th>
                                    <th class="text-center">Kehadiran</th>
                                    <th>Status Turun</th>
                                    <th>Tiket</th>
                                    <th class="text-end">Aksi Tiket</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pemesanan->detailPemesanan as $detail)
                                    @php
                                        $whatsappNumber = $normalizeWhatsapp($detail->no_hp_penumpang);
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $detail->nomor_kursi }}</strong></td>
                                        <td>{{ $detail->nama_penumpang }}</td>
                                        <td>
                                            @if ($whatsappNumber)
                                                <a class="travs-contact-link" href="https://wa.me/{{ $whatsappNumber }}" target="_blank" rel="noopener">{{ $detail->no_hp_penumpang }}</a>
                                            @else
                                                {{ $detail->no_hp_penumpang ?: '—' }}
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="travs-status-pill travs-status-{{ $detail->status_kehadiran }} badge rounded-pill">
                                                {{ ucfirst(str_replace('_', ' ', $detail->status_kehadiran)) }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ $detail->diturunkan_pada ? 'Sudah turun' : 'Belum turun' }}</strong>
                                            <div class="travs-table-subcopy">{{ $detail->diturunkan_pada?->format('d M Y H:i') ?: '—' }}</div>
                                        </td>
                                        <td>
                                            <strong>{{ $detail->tiketElektronik?->kode_qr ?: 'Belum terbit' }}</strong>
                                            @if ($detail->tiketElektronik)
                                                <div class="travs-table-subcopy">
                                                    {{ $detail->tiketElektronik->sudah_dipindai ? 'Sudah dipindai' : 'Belum dipindai' }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($detail->tiketElektronik)
                                                <div class="travs-ticket-table-actions">
                                                    <a
                                                        class="travs-button travs-button-outline btn btn-outline-primary btn-sm"
                                                        href="{{ route('admin.pemesanan.tiket.show', [$pemesanan, $detail->tiketElektronik]) }}"
                                                    >Lihat</a>
                                                    <a
                                                        class="travs-button travs-button-primary btn btn-primary btn-sm"
                                                        href="{{ route('admin.pemesanan.tiket.download', [$pemesanan, $detail->tiketElektronik]) }}"
                                                    >Unduh</a>
                                                </div>
                                            @else
                                                <span class="travs-table-subcopy">Belum tersedia</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="travs-table-empty">Belum ada data penumpang pada pemesanan ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
