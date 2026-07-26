    <section class="travs-detail-stack">
        <article class="travs-section-card card">
            <div class="travs-panel-header d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="travs-panel-title">
                    <h3>Pemesanan dan Penumpang</h3>
                    <p>Hanya pemesanan dengan pembayaran berhasil. Tarif memakai subtotal historis pemesanan.</p>
                </div>
                <a href="{{ route('admin.pemesanan.index') }}" class="travs-button-secondary btn btn-outline-secondary">Lihat Semua Pemesanan &rarr;</a>
            </div>

            <div class="travs-table-wrap table-responsive">
                <table class="travs-table table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Pemesanan</th>
                            <th>Penumpang &amp; Kursi</th>
                            <th class="text-end">Tarif</th>
                            <th class="text-center">Status Pemesanan</th>
                            <th class="text-center">Pembayaran</th>
                            <th>Kehadiran</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($successfulBookings as $booking)
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
                                <td class="text-end">
                                    <strong>{{ $formatRupiah($historicalFare) }}/orang</strong>
                                    <div class="travs-table-subcopy">Total {{ $formatRupiah($booking->total_harga) }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="travs-status-pill travs-status-{{ $booking->status_pemesanan }} badge rounded-pill">
                                        {{ ucwords(str_replace('_', ' ', $booking->status_pemesanan)) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="travs-status-pill travs-status-{{ $paymentStatusTone }} badge rounded-pill">
                                        {{ ucwords(str_replace('_', ' ', $paymentStatus)) }}
                                    </span>
                                    @if ($booking->pembayaran)
                                        <div class="travs-table-subcopy mt-1">{{ \App\Support\PaymentMethodCatalog::label($booking->pembayaran->metode_bayar) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $presentCount }} hadir</strong>
                                    <div class="travs-table-subcopy">{{ $absentCount }} tidak hadir &middot; {{ $droppedCount }} turun</div>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.pemesanan.show', ['pemesanan' => $booking, 'jadwal_id' => $jadwal->id]) }}" class="travs-button-link btn btn-link">Detail Pemesanan</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="travs-table-empty">Belum ada pemesanan berhasil pada jadwal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
