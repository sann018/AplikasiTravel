@extends('admin.layouts.app')

@section('title', 'Detail Pemesanan')
@section('page-title', 'Detail Pemesanan')
@section('page-description', 'Lihat rincian pemesanan, pembayaran, dan penumpang.')

@section('content')
    @php
        $pembayaran = $pemesanan->pembayaran;
        $metodeBayar = $pembayaran?->metode_bayar;
        $metodeManualTransfer = ['transfer_manual', 'manual_transfer', 'bank_manual'];
        $isCashPayment = $metodeBayar === 'cash';
        $isGatewayPayment = $pembayaran && ! in_array($metodeBayar, array_merge(['cash'], $metodeManualTransfer), true);
        $statusLockedByGateway = $isGatewayPayment;
        $cashVerificationReady = $isCashPayment
            && $pembayaran?->status_bayar === 'menunggu'
            && $pemesanan->status_pemesanan === 'menunggu_pembayaran';
        $paymentDetailLabel = match (true) {
            $isCashPayment && $pembayaran?->status_bayar === 'menunggu' => 'Lihat Detail Tunai',
            $isCashPayment => 'Lihat Detail Tunai',
            in_array($metodeBayar, $metodeManualTransfer, true) => 'Lihat Bukti Pembayaran',
            $isGatewayPayment => 'Lihat Detail Pembayaran',
            default => 'Lihat Detail Pembayaran',
        };
        $paymentDetailUrl = $pembayaran
            ? route('admin.pembayaran.show', $pembayaran)
            : route('admin.pembayaran.index', ['search' => $pemesanan->kode_booking]);
        $formatRupiah = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
        $storageUrl = fn (?string $path) => $path
            ? (str_starts_with($path, 'http') ? $path : asset('storage/'.ltrim($path, '/')))
            : null;
        $normalizeWhatsapp = function (?string $phone): ?string {
            $digits = preg_replace('/\D+/', '', (string) $phone);

            if ($digits === '') {
                return null;
            }

            if (str_starts_with($digits, '0')) {
                $digits = '62'.substr($digits, 1);
            } elseif (str_starts_with($digits, '8')) {
                $digits = '62'.$digits;
            }

            return strlen($digits) >= 10 ? $digits : null;
        };
        $bookingRoute = $pemesanan->jadwal->rute->kota_asal.' - '.$pemesanan->jadwal->rute->kota_tujuan;
        $totalManifest = $pemesanan->detailPemesanan->count();
        $totalHadir = $pemesanan->detailPemesanan
            ->where('status_kehadiran', 'hadir')
            ->count();
        $totalTurun = $pemesanan->detailPemesanan
            ->filter(fn ($detail) => $detail->diturunkan_pada !== null)
            ->count();
        $totalBagasi = $pemesanan->itemBagasi->count();
        $bagasiAman = $totalBagasi === 0 || $totalTurun >= $totalManifest;
        $operasionalClear = $totalManifest > 0
            && $totalHadir >= $totalManifest
            && $totalTurun >= $totalManifest;
    @endphp

    <section class="travs-detail-grid">
        <article class="travs-section-card">
            <div class="travs-panel-title">
                <h3>Ringkasan Pemesanan</h3>
                <p>Kode pemesanan {{ $pemesanan->kode_booking }} untuk rute {{ $bookingRoute }}.</p>
            </div>

            <dl class="travs-description-list">
                <div>
                    <dt>Penumpang Pemesan</dt>
                    <dd>
                        <div class="travs-person-cell">
                            <div class="travs-avatar">
                                @if ($pemesanan->pengguna->foto_profil)
                                    <img class="travs-avatar-image travs-photo-preview-trigger" src="{{ $storageUrl($pemesanan->pengguna->foto_profil) }}" alt="Foto {{ $pemesanan->pengguna->nama }}">
                                @else
                                    <span>{{ strtoupper(mb_substr($pemesanan->pengguna->nama, 0, 1)) }}</span>
                                @endif
                            </div>
                            <div>
                                <strong>{{ $pemesanan->pengguna->nama }}</strong>
                                <div class="travs-table-subcopy">{{ $pemesanan->pengguna->email }} &middot; {{ $pemesanan->pengguna->no_hp }}</div>
                            </div>
                        </div>
                    </dd>
                </div>
                <div>
                    <dt>Status Pemesanan</dt>
                    <dd>{{ ucwords(str_replace('_', ' ', $pemesanan->status_pemesanan)) }}</dd>
                </div>
                <div>
                    <dt>Waktu Berangkat</dt>
                    <dd>{{ $pemesanan->jadwal->waktu_berangkat->translatedFormat('d F Y H:i') }}</dd>
                </div>
                <div>
                    <dt>Pengemudi</dt>
                    <dd>{{ $pemesanan->jadwal->pengemudi->pengguna->nama }}</dd>
                </div>
                <div>
                    <dt>Kendaraan</dt>
                    <dd>{{ $pemesanan->jadwal->kendaraan->merk_kendaraan }} &middot; {{ $pemesanan->jadwal->kendaraan->nomor_plat }}</dd>
                </div>
                <div>
                    <dt>Alamat Jemput</dt>
                    <dd>{{ $pemesanan->alamat_jemput ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Patokan Jemput</dt>
                    <dd>{{ $pemesanan->catatan_jemput ?: 'Patokan belum diisi' }}</dd>
                </div>
                <div>
                    <dt>Alamat Turun</dt>
                    <dd>{{ $pemesanan->alamat_turun ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Patokan Turun</dt>
                    <dd>{{ $pemesanan->catatan_turun ?: 'Patokan belum diisi' }}</dd>
                </div>
            </dl>
        </article>

        <article class="travs-section-card">
            <div class="travs-panel-title">
                <h3>Status dan Pembayaran</h3>
                <p>Status pemesanan dapat diperbarui sesuai proses perjalanan. Pembayaran digital diperbarui otomatis setelah konfirmasi layanan pembayaran.</p>
            </div>

            <form method="POST" action="{{ route('admin.pemesanan.update', $pemesanan) }}" class="travs-form-grid">
                @csrf
                @method('PUT')

                <div class="travs-field">
                    <label for="status_pemesanan">Status Pemesanan</label>
                    <select id="status_pemesanan" name="status_pemesanan" class="travs-select @error('status_pemesanan') is-invalid @enderror" @disabled($statusLockedByGateway)>
                        @foreach (['menunggu_pembayaran' => 'Menunggu Pembayaran', 'terbayar' => 'Terbayar', 'dibatalkan' => 'Dibatalkan', 'selesai' => 'Selesai'] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected($pemesanan->status_pemesanan === $value)
                                @disabled(($cashVerificationReady || ($isGatewayPayment && $pembayaran?->status_bayar !== 'berhasil')) && $value === 'terbayar')
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @if ($statusLockedByGateway)
                        <small style="color:#64748b;">Status pembayaran digital diperbarui otomatis dari layanan pembayaran.</small>
                    @endif
                    @if ($isGatewayPayment && $pembayaran?->status_bayar !== 'berhasil')
                        <small style="color:#64748b;">Untuk pembayaran digital, gunakan pengecekan status pembayaran yang tersedia.</small>
                    @endif
                    @include('admin.partials.field-error', ['name' => 'status_pemesanan'])
                </div>

                <div class="travs-detail-money">
                    <div><span>Subtotal Tiket</span><strong>{{ $formatRupiah($pemesanan->subtotal_tiket) }}</strong></div>
                    <div><span>Biaya Bagasi</span><strong>{{ $formatRupiah($pemesanan->biaya_bagasi) }}</strong></div>
                    <div class="is-total"><span>Total</span><strong>{{ $formatRupiah($pemesanan->total_harga) }}</strong></div>
                </div>

                <div class="travs-operational-summary">
                    <div>
                        <small>Kehadiran</small>
                        <strong>{{ $totalHadir }}/{{ $totalManifest }}</strong>
                        <span>{{ $totalManifest > 0 && $totalHadir >= $totalManifest ? 'Semua hadir' : 'Belum lengkap' }}</span>
                    </div>
                    <div>
                        <small>Status Turun</small>
                        <strong>{{ $totalTurun }}/{{ $totalManifest }}</strong>
                        <span>{{ $operasionalClear ? 'Clear' : 'Belum clear' }}</span>
                    </div>
                    <div>
                        <small>Barang Bawaan</small>
                        <strong>{{ $totalBagasi }} item</strong>
                        <span>{{ $bagasiAman ? 'Aman' : 'Perlu cek saat turun' }}</span>
                    </div>
                </div>

                <div class="travs-form-actions travs-inline">
                    <a href="{{ route('admin.pemesanan.index') }}" class="travs-button-secondary">Kembali ke Daftar</a>
                    <a href="{{ $paymentDetailUrl }}" class="travs-button-secondary">{{ $paymentDetailLabel }}</a>
                    @unless ($statusLockedByGateway)
                        <button type="submit" class="travs-button">Simpan Status</button>
                    @endunless
                </div>
            </form>

            @if ($cashVerificationReady)
                <div class="travs-subsection">
                    <strong>Konfirmasi Pembayaran Tunai</strong>
                    <p class="travs-empty-inline">Pemesanan ini menunggu pembayaran tunai dari penumpang.</p>
                    <form method="POST" action="{{ route('admin.pembayaran.confirm-cash', $pemesanan) }}">
                        @csrf
                        <button type="submit" class="travs-button">Konfirmasi Pembayaran Tunai</button>
                    </form>
                </div>
            @endif
        </article>
    </section>

    <section class="travs-detail-stack">
        <article class="travs-section-card collapsible-section" data-collapsible-section data-collapse-id="pemesanan-penumpang-kursi">
            <div class="collapsible-section__header" data-collapse-header>
                <div class="travs-panel-title">
                    <h3>Penumpang per Kursi</h3>
                    <p>Daftar penumpang, lokasi, status kehadiran, dan tiket pada pemesanan ini.</p>
                </div>
                @include('admin.partials.collapsible-toggle', ['target' => 'pemesanan-penumpang-kursi-body'])
            </div>

            <div id="pemesanan-penumpang-kursi-body" class="collapsible-section__body" data-collapse-body>
                <div class="travs-table-wrap">
                    <table class="travs-table">
                        <thead>
                            <tr>
                                <th>Kursi</th>
                                <th>Penumpang</th>
                                <th>No. HP</th>
                                <th>Status</th>
                                <th>Lokasi</th>
                                <th>Tiket</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pemesanan->detailPemesanan as $detail)
                                @php
                                    $whatsappNumber = $normalizeWhatsapp($detail->no_hp_penumpang);
                                @endphp
                                <tr>
                                    <td>{{ $detail->nomor_kursi }}</td>
                                    <td>{{ $detail->nama_penumpang }}</td>
                                    <td>
                                        @if ($whatsappNumber)
                                            <a class="travs-contact-link" href="https://wa.me/{{ $whatsappNumber }}" target="_blank" rel="noopener">{{ $detail->no_hp_penumpang }}</a>
                                        @else
                                            {{ $detail->no_hp_penumpang ?: '-' }}
                                        @endif
                                    </td>
                                    <td>
                                        <span class="travs-status-pill travs-status-{{ $detail->status_kehadiran }}">
                                            {{ ucfirst(str_replace('_', ' ', $detail->status_kehadiran)) }}
                                        </span>
                                        <span class="travs-status-pill travs-status-{{ $detail->diturunkan_pada ? 'selesai' : 'menunggu' }}">
                                            {{ $detail->diturunkan_pada ? 'Sudah turun' : 'Belum turun' }}
                                        </span>
                                        <div class="travs-table-subcopy">
                                            {{ $detail->diturunkan_pada ? $detail->diturunkan_pada->format('d M Y H:i') : 'Barang bawaan perlu dicek saat turun' }}
                                        </div>
                                    </td>
                                    <td>
                                        <strong>Jemput:</strong> {{ $pemesanan->alamat_jemput ?: '-' }}
                                        <div class="travs-location-note">Patokan jemput: {{ $pemesanan->catatan_jemput ?: 'Patokan belum diisi' }}</div>
                                        <div style="margin-top:8px;"><strong>Turun:</strong> {{ $pemesanan->alamat_turun ?: '-' }}</div>
                                        <div class="travs-location-note">Patokan turun: {{ $pemesanan->catatan_turun ?: 'Patokan belum diisi' }}</div>
                                    </td>
                                    <td>{{ $detail->tiketElektronik?->kode_qr ?: 'Belum terbit' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </article>

        <article class="travs-section-card collapsible-section" data-collapsible-section data-collapse-id="pemesanan-pembayaran-bagasi">
            <div class="collapsible-section__header" data-collapse-header>
                <div class="travs-panel-title">
                    <h3>Pembayaran dan Bagasi</h3>
                    <p>Rincian pembayaran, total tagihan, dan barang bawaan.</p>
                </div>
                @include('admin.partials.collapsible-toggle', ['target' => 'pemesanan-pembayaran-bagasi-body'])
            </div>

            <div id="pemesanan-pembayaran-bagasi-body" class="collapsible-section__body" data-collapse-body>
                <dl class="travs-description-list">
                    <div>
                        <dt>Metode Bayar</dt>
                        <dd>{{ strtoupper($metodeBayar ?? '-') }}</dd>
                    </div>
                    <div>
                        <dt>Status Bayar</dt>
                        <dd>{{ ucwords(str_replace('_', ' ', $pembayaran?->status_bayar ?? 'draft')) }}</dd>
                    </div>
                    <div>
                        <dt>Dibayar Pada</dt>
                        <dd>{{ $pembayaran?->dibayar_pada?->translatedFormat('d F Y H:i') ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt>Batas Pembayaran</dt>
                        <dd>{{ $pemesanan->batas_pembayaran?->translatedFormat('d F Y H:i') ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt>Subtotal Tiket</dt>
                        <dd>{{ $formatRupiah($pemesanan->subtotal_tiket) }}</dd>
                    </div>
                    <div>
                        <dt>Biaya Bagasi</dt>
                        <dd>{{ $formatRupiah($pemesanan->biaya_bagasi) }}</dd>
                    </div>
                    <div>
                        <dt>Total Pembayaran</dt>
                        <dd>{{ $formatRupiah($pemesanan->total_harga) }}</dd>
                    </div>
                </dl>

                <div class="travs-subsection">
                    <strong>Item Bagasi</strong>
                    <div class="travs-bagasi-list">
                        @forelse ($pemesanan->itemBagasi as $item)
                            @php
                                // Data lama kadang belum menyimpan biaya per item.
                                // Kalau hanya ada satu item, gunakan total biaya bagasi pemesanan sebagai fallback tampilan.
                                $itemCost = (float) ($item->biaya_item ?? 0);
                                if ($itemCost <= 0 && $pemesanan->itemBagasi->count() === 1 && (float) $pemesanan->biaya_bagasi > 0) {
                                    $itemCost = (float) $pemesanan->biaya_bagasi;
                                }
                                $placementLabel = ($item->posisi_barang ?? 'masuk_bagasi') === 'dipegang_sendiri' ? 'Dipegang sendiri' : 'Masuk bagasi';
                                $categoryLabel = ($item->kategori_ukuran ?? 'besar') === 'kecil' ? 'Barang kecil' : 'Barang besar';
                                $largeTypeLabel = match ($item->jenis_barang_besar) {
                                    'sepeda' => 'Sepeda',
                                    'sepeda_motor' => 'Sepeda motor',
                                    'beko' => 'Beko / barang berat',
                                    'lainnya' => 'Barang besar lainnya',
                                    default => null,
                                };
                                $photos = collect($item->foto_urls ?? []);
                                if ($item->foto_url) {
                                    $photos = $photos->prepend($item->foto_url);
                                }
                                $photos = $photos->filter()->unique()->values();
                            @endphp
                            <div class="travs-bagasi-item">
                                <div>
                                    <strong>{{ $item->deskripsi_item }}</strong>
                                    <p>{{ $item->berat_kg ? $item->berat_kg.' kg' : '0 kg' }} &middot; {{ $item->ukuran_cm ?: 0 }} cm &middot; {{ $item->jumlah_item }} item</p>
                                    <p>{{ $placementLabel }} &middot; {{ $categoryLabel }}@if($largeTypeLabel) &middot; {{ $largeTypeLabel }}@endif</p>
                                    <div class="travs-bagasi-photo-row">
                                        @forelse ($photos as $photo)
                                            <img
                                                class="travs-bagasi-thumb travs-photo-preview-trigger"
                                                src="{{ $storageUrl($photo) }}"
                                                alt="Foto barang bawaan {{ $loop->iteration }}"
                                                data-preview-src="{{ $storageUrl($photo) }}"
                                            >
                                        @empty
                                            <span class="travs-empty-inline">Foto barang belum tersedia.</span>
                                        @endforelse
                                    </div>
                                </div>
                                <span>{{ $itemCost > 0 ? $formatRupiah($itemCost) : 'Gratis' }}</span>
                            </div>
                        @empty
                            <p class="travs-empty-inline">Belum ada item bagasi pada pemesanan ini.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </article>
    </section>
@endsection
