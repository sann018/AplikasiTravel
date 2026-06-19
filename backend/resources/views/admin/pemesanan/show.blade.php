@extends('admin.layouts.app')

@section('title', 'Detail Pemesanan')
@section('page-title', 'Detail Pemesanan')
@section('page-description', 'Lihat rincian pemesanan, pembayaran, dan penumpang.')

@section('content')
    @php
        $cashVerificationReady = $pemesanan->pembayaran?->metode_bayar === 'cash'
            && $pemesanan->pembayaran?->status_bayar === 'menunggu'
            && $pemesanan->status_pemesanan === 'menunggu_pembayaran';
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
                <h3>Ringkasan Booking</h3>
                <p>Kode booking {{ $pemesanan->kode_booking }} untuk rute {{ $pemesanan->jadwal->rute->kota_asal }} - {{ $pemesanan->jadwal->rute->kota_tujuan }}.</p>
            </div>

            <dl class="travs-description-list">
                <div>
                    <dt>Penumpang Pemesan</dt>
                    <dd>
                        <div class="travs-person-cell">
                            <div class="travs-avatar">
                                @if ($pemesanan->pengguna->foto_profil)
                                    <img class="travs-avatar-image travs-photo-preview-trigger" src="/storage/{{ ltrim($pemesanan->pengguna->foto_profil, '/') }}" alt="Foto {{ $pemesanan->pengguna->nama }}">
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
                    <dt>Status Booking</dt>
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
            </dl>
        </article>

        <article class="travs-section-card">
            <div class="travs-panel-title">
                <h3>Status dan Pembayaran</h3>
                <p>Ubah status jika diperlukan.</p>
            </div>

            <form method="POST" action="{{ route('admin.pemesanan.update', $pemesanan) }}" class="travs-form-grid">
                @csrf
                @method('PUT')

                <div class="travs-field">
                    <label for="status_pemesanan">Status Booking</label>
                    <select id="status_pemesanan" name="status_pemesanan" class="travs-select @error('status_pemesanan') is-invalid @enderror">
                        @foreach (['menunggu_pembayaran' => 'Menunggu Pembayaran', 'terbayar' => 'Terbayar', 'dibatalkan' => 'Dibatalkan', 'selesai' => 'Selesai'] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected($pemesanan->status_pemesanan === $value)
                                @disabled($cashVerificationReady && $value === 'terbayar')
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'status_pemesanan'])
                </div>

                <div class="travs-detail-money">
                    <div><span>Subtotal Tiket</span><strong>Rp{{ number_format($pemesanan->subtotal_tiket, 0, ',', '.') }}</strong></div>
                    <div><span>Biaya Bagasi</span><strong>Rp{{ number_format($pemesanan->biaya_bagasi, 0, ',', '.') }}</strong></div>
                    <div><span>Biaya Admin</span><strong>Rp{{ number_format($pemesanan->biaya_admin, 0, ',', '.') }}</strong></div>
                    <div class="is-total"><span>Total</span><strong>Rp{{ number_format($pemesanan->total_harga, 0, ',', '.') }}</strong></div>
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
                    <a href="{{ route('admin.pembayaran.index', ['search' => $pemesanan->kode_booking]) }}" class="travs-button-secondary">Buka Pembayaran</a>
                    <button type="submit" class="travs-button">Simpan Status</button>
                </div>
            </form>

            @if ($cashVerificationReady)
                <div class="travs-subsection">
                    <strong>Verifikasi Cash</strong>
                    <p class="travs-empty-inline">Pemesanan ini menunggu pembayaran tunai.</p>
                    <form method="POST" action="{{ route('admin.pembayaran.confirm-cash', $pemesanan) }}">
                        @csrf
                        <button type="submit" class="travs-button">Verifikasi Cash</button>
                    </form>
                </div>
            @endif
        </article>
    </section>

    <section class="travs-panel-grid">
        <article class="travs-section-card">
            <div class="travs-panel-title">
                <h3>Penumpang per Kursi</h3>
                <p>Daftar penumpang pada pemesanan ini.</p>
            </div>

            <div class="travs-table-wrap">
                <table class="travs-table">
                    <thead>
                        <tr>
                            <th>Kursi</th>
                            <th>Nama Penumpang</th>
                            <th>No. HP</th>
                            <th>Status Hadir</th>
                            <th>Status Turun</th>
                            <th>Tiket</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pemesanan->detailPemesanan as $detail)
                            <tr>
                                <td>{{ $detail->nomor_kursi }}</td>
                                <td>{{ $detail->nama_penumpang }}</td>
                                <td>{{ $detail->no_hp_penumpang }}</td>
                                <td>
                                    <span class="travs-status-pill travs-status-{{ $detail->status_kehadiran }}">
                                        {{ ucfirst(str_replace('_', ' ', $detail->status_kehadiran)) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="travs-status-pill travs-status-{{ $detail->diturunkan_pada ? 'selesai' : 'menunggu' }}">
                                        {{ $detail->diturunkan_pada ? 'Sudah turun' : 'Belum turun' }}
                                    </span>
                                    <div class="travs-table-subcopy">
                                        {{ $detail->diturunkan_pada ? $detail->diturunkan_pada->format('d M Y H:i') : 'Barang bawaan perlu dicek saat turun' }}
                                    </div>
                                </td>
                                <td>{{ $detail->tiketElektronik?->kode_qr ?: 'Belum terbit' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </article>

        <article class="travs-section-card">
            <div class="travs-panel-title">
                <h3>Pembayaran dan Bagasi</h3>
                <p>Rincian pembayaran dan bagasi.</p>
            </div>

            <dl class="travs-description-list">
                <div>
                    <dt>Metode Bayar</dt>
                    <dd>{{ strtoupper($pemesanan->pembayaran?->metode_bayar ?? '-') }}</dd>
                </div>
                <div>
                    <dt>Status Bayar</dt>
                    <dd>{{ ucwords(str_replace('_', ' ', $pemesanan->pembayaran?->status_bayar ?? 'draft')) }}</dd>
                </div>
                <div>
                    <dt>Dibayar Pada</dt>
                    <dd>{{ $pemesanan->pembayaran?->dibayar_pada?->translatedFormat('d F Y H:i') ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Batas Pembayaran</dt>
                    <dd>{{ $pemesanan->batas_pembayaran?->translatedFormat('d F Y H:i') ?: '-' }}</dd>
                </div>
            </dl>

            <div class="travs-subsection">
                <strong>Item Bagasi</strong>
                <div class="travs-bagasi-list">
                    @forelse ($pemesanan->itemBagasi as $item)
                        <div class="travs-bagasi-item">
                            <div>
                                <strong>{{ $item->deskripsi_item }}</strong>
                                <p>{{ $item->berat_kg ? $item->berat_kg.' kg' : '0 kg' }} &middot; {{ $item->ukuran_cm ?: 0 }} cm &middot; {{ $item->jumlah_item }} item</p>
                            </div>
                            <span>Rp{{ number_format($item->biaya_item, 0, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="travs-empty-inline">Belum ada item bagasi pada booking ini.</p>
                    @endforelse
                </div>
            </div>
        </article>
    </section>
@endsection
