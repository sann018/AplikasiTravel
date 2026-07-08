@extends('admin.layouts.app')

@section('title', $pageTitle ?? 'Log Notifikasi')
@section('page-title', $pageTitle ?? 'Log Notifikasi')
@section('page-description', $pageDescription ?? 'Pantau notifikasi untuk penumpang, pengemudi, dan admin.')

@section('content')
    @if (session('success'))
        <div class="travs-alert travs-alert-success" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->has('period'))
        <div class="travs-alert travs-alert-danger" role="alert">{{ $errors->first('period') }}</div>
    @endif

    <section class="collapsible-section" data-collapsible-section>
        <div class="collapsible-section__header" data-collapse-header>
            <div class="travs-panel-title">
                <h3>Statistik</h3>
            </div>
            @include('admin.partials.collapsible-toggle', ['targetId' => 'notification-summary-body'])
        </div>

        <div id="notification-summary-body" class="travs-summary-grid collapsible-section__body" data-collapse-body>
            <article class="travs-summary-card">
            <small>Total Notifikasi</small>
            <strong>{{ number_format($stats['total']) }}</strong>
            <span>{{ ($isAdminInbox ?? false) ? 'Khusus admin' : 'Semua notifikasi' }}</span>
        </article>
            <article class="travs-summary-card">
            <small>Belum Dibaca</small>
            <strong>{{ number_format($stats['belum_dibaca']) }}</strong>
            <span>Menunggu dibuka user</span>
        </article>
            <article class="travs-summary-card">
            <small>Notifikasi Pembayaran</small>
            <strong>{{ number_format($stats['pembayaran']) }}</strong>
            <span>Terkait transaksi</span>
        </article>
            <article class="travs-summary-card">
            <small>Booking Baru</small>
            <strong>{{ number_format($stats['pemesanan']) }}</strong>
            <span>Masuk ke admin</span>
            </article>
        </div>
    </section>

    <article class="travs-section-card collapsible-section" data-collapsible-section>
        <div class="collapsible-section__header" data-collapse-header>
            <div class="travs-panel-title">
                <h3>Filter Notifikasi</h3>
                <p>Cari notifikasi berdasarkan judul, pesan, jenis, atau status baca.</p>
            </div>
            @include('admin.partials.collapsible-toggle', ['targetId' => 'notification-filter-body'])
        </div>

        <form id="notification-filter-body" method="GET" class="travs-filter-grid collapsible-section__body" data-collapse-body>
            <div class="travs-field">
                <label for="search">Cari</label>
                <input id="search" name="search" class="travs-input" value="{{ $filters['search'] ?? '' }}" placeholder="Judul, pesan, atau nama pengguna">
            </div>
            <div class="travs-field">
                <label for="jenis">Jenis</label>
                <select id="jenis" name="jenis" class="travs-select">
                    <option value="">Semua jenis</option>
                    @foreach (['pembayaran' => 'Pembayaran', 'perjalanan' => 'Perjalanan', 'pemesanan' => 'Pemesanan', 'sistem' => 'Sistem'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['jenis'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="travs-field">
                <label for="dibaca">Status Baca</label>
                <select id="dibaca" name="dibaca" class="travs-select">
                    <option value="">Semua status</option>
                    <option value="1" @selected(($filters['dibaca'] ?? '') === '1')>Sudah dibaca</option>
                    <option value="0" @selected(($filters['dibaca'] ?? '') === '0')>Belum dibaca</option>
                </select>
            </div>
            <div class="travs-filter-actions">
                <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                <a href="{{ route($resetRoute ?? 'admin.notifikasi.index') }}" class="travs-button-secondary">Reset</a>
            </div>
        </form>
    </article>

    <article class="travs-section-card collapsible-section" data-collapsible-section style="margin-top: 18px;">
        <div class="travs-panel-header collapsible-section__header" data-collapse-header>
            <div class="travs-panel-title">
                <h3>Bersihkan Notifikasi Lama</h3>
                <p>Hanya notifikasi yang sudah dibaca yang akan dihapus. Notifikasi belum dibaca tetap disimpan.</p>
            </div>
            @include('admin.partials.collapsible-toggle', ['targetId' => 'notification-cleanup-body'])
        </div>

        <div id="notification-cleanup-body" class="collapsible-section__body" data-collapse-body>
            <form method="POST" action="{{ route($clearRoute ?? 'admin.notifikasi.history.clear') }}"
                onsubmit="return confirm('Hapus riwayat notifikasi yang sudah dibaca sesuai pilihan ini?');"
                style="display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
                @csrf
                @method('DELETE')
                <div class="travs-field" style="min-width: 220px;">
                    <label for="cleanup-period">Periode</label>
                    <select id="cleanup-period" name="period" class="travs-select" required>
                        <option value="7_days">Lebih lama dari 7 hari</option>
                        <option value="30_days" selected>Lebih lama dari 30 hari</option>
                        <option value="90_days">Lebih lama dari 90 hari</option>
                        <option value="all_read">Semua yang sudah dibaca</option>
                    </select>
                </div>
                <button type="submit" class="travs-button-secondary">Hapus Riwayat</button>
            </form>
        </div>
    </article>

    <article class="travs-section-card collapsible-section" data-collapsible-section style="margin-top: 18px;">
        <div class="collapsible-section__header" data-collapse-header>
            <div class="travs-panel-title">
                <h3>Daftar Notifikasi</h3>
                <p>{{ $listDescription ?? 'Notifikasi terbaru dari seluruh aktivitas sistem.' }}</p>
            </div>
            @include('admin.partials.collapsible-toggle', ['targetId' => 'notification-list-body'])
        </div>

        <div id="notification-list-body" class="collapsible-section__body" data-collapse-body>
        <section class="travs-content" style="padding: 0; gap: 18px;">
        @forelse ($notifikasi as $item)
            @php
                $isBookingNotification = $item->judul === 'Pemesanan baru masuk';
                $booking = $isBookingNotification ? $item->referensiBooking : null;
                $routeLabel = $booking?->jadwal?->rute
                    ? $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan
                    : null;
                $vehicleLabel = $booking?->jadwal?->kendaraan
                    ? $booking->jadwal->kendaraan->merk_kendaraan.' ('.$booking->jadwal->kendaraan->nomor_plat.')'
                    : null;
                $scheduleLabel = $booking?->jadwal?->waktu_berangkat?->translatedFormat('d M Y H:i');
            @endphp

            <article class="travs-section-card {{ $isBookingNotification ? 'is-booking-notification' : '' }} {{ $item->sudah_dibaca ? '' : 'is-unread-notification' }}">
                <div class="travs-panel-header">
                    <div class="travs-panel-title">
                        <h3 style="margin-bottom: 4px;">{{ $item->judul }}</h3>
                        <p>{{ $item->created_at->translatedFormat('d M Y H:i') }} · {{ $item->pengguna->nama }}</p>
                    </div>
                    <div class="travs-status-stack">
                        <span class="travs-status-pill travs-status-{{ $item->jenis }}">
                            {{ ucfirst($item->jenis) }}
                        </span>
                        <span class="travs-status-pill travs-status-{{ $item->sudah_dibaca ? 'selesai' : 'menunggu' }}">
                            {{ $item->sudah_dibaca ? 'Sudah dibaca' : 'Belum dibaca' }}
                        </span>
                    </div>
                </div>

                @if ($isBookingNotification)
                    <div class="travs-booking-notification-head">
                        <span class="travs-booking-notification-dot"></span>
                        <strong style="color: var(--travs-accent-deep);">Booking Baru Masuk</strong>
                    </div>
                @endif

                <div class="travs-admin-booking-alert">
                    <strong>Isi notifikasi</strong>
                    <small>{{ $item->pesan }}</small>
                </div>

                @if ($booking)
                    <div class="travs-form-grid-2" style="margin-top: 16px;">
                        <div class="travs-admin-booking-alert">
                            <strong>Kode Booking</strong>
                            <small>{{ $booking->kode_booking }}</small>
                        </div>
                        <div class="travs-admin-booking-alert">
                            <strong>Status Booking</strong>
                            <small>{{ ucwords(str_replace('_', ' ', $booking->status_pemesanan)) }}</small>
                        </div>
                        <div class="travs-admin-booking-alert">
                            <strong>Rute</strong>
                            <small>{{ $routeLabel ?? 'Tidak tersedia' }}</small>
                        </div>
                        <div class="travs-admin-booking-alert">
                            <strong>Armada</strong>
                            <small>{{ $vehicleLabel ?? 'Tidak tersedia' }}</small>
                        </div>
                        <div class="travs-admin-booking-alert travs-field-span-2">
                            <strong>Jadwal Keberangkatan</strong>
                            <small>{{ $scheduleLabel ?? 'Tidak tersedia' }}</small>
                        </div>
                    </div>

                    <div class="travs-form-actions" style="margin-top: 16px; justify-content: flex-start;">
                        <a href="{{ route('admin.pemesanan.show', $booking) }}" class="travs-button">Buka Detail Pemesanan</a>
                    </div>
                @endif
            </article>
        @empty
            <article class="travs-section-card">
                <div class="travs-table-empty">Belum ada log notifikasi.</div>
            </article>
        @endforelse

        {{ $notifikasi->links() }}
        </section>
        </div>
    </article>
@endsection

@push('scripts')
    @include('admin.partials.collapsible-section-script')
@endpush
