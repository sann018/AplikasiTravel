@extends('admin.layouts.app')

@section('title', 'Pemesanan')
@section('page-title', 'Monitoring Pemesanan')
@section('page-description', 'Pantau pemesanan yang masuk, status pembayaran, dan arahkan admin ke detail pemesanan untuk tindak lanjut operasional.')

@section('content')
    <article class="travs-section-card card">
        <div class="travs-panel-title">
            <h3>Filter Pemesanan</h3>
            <p>Cari pemesanan berdasarkan kode, penumpang, rute, rentang tanggal, atau status pembayaran.</p>
        </div>

        <form method="GET" class="travs-filter-grid travs-filter-grid-wide d-grid">
            <div class="travs-field">
                <label for="search" class="form-label">Cari</label>
                <input id="search" name="search" class="travs-input form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Kode pemesanan, nama penumpang, atau rute">
            </div>
            <div class="travs-field">
                <label for="status" class="form-label">Status Pemesanan</label>
                <select id="status" name="status" class="travs-select @error('status') is-invalid @enderror form-select">
                    <option value="">Semua status</option>
                    @foreach (['menunggu_pembayaran' => 'Menunggu Pembayaran', 'terbayar' => 'Terbayar', 'dibatalkan' => 'Dibatalkan', 'selesai' => 'Selesai'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @include('admin.partials.field-error', ['name' => 'status'])
            </div>
            <div class="travs-field">
                <label for="payment_status" class="form-label">Status Pembayaran</label>
                <select id="payment_status" name="payment_status" class="travs-select @error('payment_status') is-invalid @enderror form-select">
                    <option value="">Semua status</option>
                    @foreach (['menunggu' => 'Menunggu', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'kedaluwarsa' => 'Kedaluwarsa'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @include('admin.partials.field-error', ['name' => 'payment_status'])
            </div>
            <div class="travs-field">
                <label for="source" class="form-label">Sumber Pemesanan</label>
                <select id="source" name="source" class="travs-select @error('source') is-invalid @enderror form-select">
                    <option value="">Semua sumber</option>
                    <option value="aplikasi_penumpang" @selected(($filters['source'] ?? '') === 'aplikasi_penumpang')>Aplikasi Penumpang</option>
                    <option value="loket" @selected(($filters['source'] ?? '') === 'loket')>Loket</option>
                </select>
                @include('admin.partials.field-error', ['name' => 'source'])
            </div>
            <div class="travs-field">
                <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                <input id="tanggal_mulai" name="tanggal_mulai" type="date" class="travs-input @error('tanggal_mulai') is-invalid @enderror form-control" value="{{ $filters['tanggal_mulai'] ?? '' }}">
                @include('admin.partials.field-error', ['name' => 'tanggal_mulai'])
            </div>
            <div class="travs-field">
                <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                <input id="tanggal_selesai" name="tanggal_selesai" type="date" class="travs-input @error('tanggal_selesai') is-invalid @enderror form-control" value="{{ $filters['tanggal_selesai'] ?? '' }}">
                @include('admin.partials.field-error', ['name' => 'tanggal_selesai'])
            </div>
            <div class="travs-filter-actions d-flex align-items-end flex-wrap">
                <button type="submit" class="travs-button btn btn-primary">Terapkan Filter</button>
                <a href="{{ route('admin.pemesanan.index') }}" class="travs-button-secondary btn btn-outline-secondary" data-clear-form-draft>Reset</a>
            </div>
        </form>

        <div class="travs-table-wrap table-responsive">
            <table class="travs-table table mb-0">
                <thead>
                    <tr>
                        <th>Kode Pemesanan</th>
                        <th>Penumpang</th>
                        <th>Rute</th>
                        <th class="text-center">Status Pemesanan</th>
                        <th class="text-center">Status Pembayaran</th>
                        <th class="text-center">Kursi</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pemesanan as $booking)
                        @php
                            $contactName = $booking->namaKontakPemesan();
                            $contactPhone = $booking->nomorKontakPemesan();
                            $profilePhoto = $booking->pengguna?->foto_profil;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $booking->kode_booking }}</strong>
                                <div class="travs-table-subcopy">{{ $booking->created_at->translatedFormat('d M Y H:i') }}</div>
                                <span class="travs-status-pill badge rounded-pill mt-1">
                                    {{ $booking->isPemesananLoket() ? 'Loket' : 'Aplikasi Penumpang' }}
                                </span>
                                @if ($booking->isPemesananLoket() && $booking->dibuatOleh)
                                    <div class="travs-staf-pelayanan-source-note mt-2">
                                        Dicatat oleh Staf Pelayanan: <strong>{{ $booking->dibuatOleh->nama }}</strong>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="travs-person-cell d-flex align-items-center">
                                    <div class="travs-avatar">
                                        @if ($profilePhoto)
                                            <img class="travs-avatar-image travs-photo-preview-trigger" src="/storage/{{ ltrim($profilePhoto, '/') }}" alt="Foto {{ $contactName }}">
                                        @else
                                            <span>{{ strtoupper(mb_substr($contactName, 0, 1)) }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <strong>{{ $contactName }}</strong>
                                        <div class="travs-table-subcopy">{{ $contactPhone ?: '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $booking->jadwal->rute->kota_asal }} - {{ $booking->jadwal->rute->kota_tujuan }}</td>
                            <td class="text-center">
                                <span class="travs-status-pill travs-status-{{ $booking->status_pemesanan }} badge rounded-pill">
                                    {{ ucwords(str_replace('_', ' ', $booking->status_pemesanan)) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="travs-status-pill travs-status-{{ $booking->pembayaran?->status_bayar ?? 'draft' }} badge rounded-pill">
                                    {{ $booking->pembayaran
                                        ? ucwords(str_replace('_', ' ', $booking->pembayaran->status_bayar))
                                        : 'Belum Tersedia' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <strong>{{ $booking->detail_pemesanan_count }}</strong>
                                <div class="travs-table-subcopy">kursi</div>
                            </td>
                            <td class="text-end text-nowrap">Rp{{ number_format($booking->total_harga, 0, ',', '.') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.pemesanan.show', $booking) }}" class="travs-button-link btn btn-link">Lihat Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="travs-table-empty">Tidak ada pemesanan yang sesuai dengan filter yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $pemesanan->links() }}

        <section class="border-top pt-4 mt-4">
            <div class="mb-3">
                <h3 class="h6 mb-1">Hapus Riwayat Pemesanan Lama</h3>
                <p class="text-muted small mb-0">Pilih rentang tanggal pemesanan yang akan dihapus.</p>
            </div>

            <form
                method="POST"
                action="{{ route('admin.pemesanan.old.destroy') }}"
                class="row g-3 align-items-end"
                data-confirm-title="Hapus riwayat pemesanan?"
                data-confirm-message="Data pemesanan pada rentang tanggal yang dipilih akan dihapus permanen."
                data-confirm-action="Ya, hapus riwayat"
                data-confirm-date-start="hapus_tanggal_mulai"
                data-confirm-date-end="hapus_tanggal_selesai"
            >
                @csrf
                @method('DELETE')

                <div class="col-12 col-md-5">
                    <label for="hapus_tanggal_mulai" class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                    <input
                        id="hapus_tanggal_mulai"
                        name="hapus_tanggal_mulai"
                        type="date"
                        max="{{ now()->subDay()->format('Y-m-d') }}"
                        value="{{ old('hapus_tanggal_mulai') }}"
                        class="travs-input @error('hapus_tanggal_mulai') is-invalid @enderror form-control"
                        required
                    >
                    @include('admin.partials.field-error', ['name' => 'hapus_tanggal_mulai'])
                </div>

                <div class="col-12 col-md-5">
                    <label for="hapus_tanggal_selesai" class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                    <input
                        id="hapus_tanggal_selesai"
                        name="hapus_tanggal_selesai"
                        type="date"
                        max="{{ now()->subDay()->format('Y-m-d') }}"
                        value="{{ old('hapus_tanggal_selesai') }}"
                        class="travs-input @error('hapus_tanggal_selesai') is-invalid @enderror form-control"
                        required
                    >
                    @include('admin.partials.field-error', ['name' => 'hapus_tanggal_selesai'])
                </div>

                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-danger w-100">Hapus</button>
                </div>
            </form>

            <p class="form-text mt-3 mb-0">
                Hanya pemesanan selesai atau dibatalkan yang dihapus. Lakukan pencadangan data terlebih dahulu.
            </p>
        </section>
    </article>
@endsection
