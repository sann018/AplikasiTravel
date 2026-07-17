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

    @foreach (['tanggal_mulai', 'tanggal_selesai'] as $dateErrorField)
        @if ($errors->has($dateErrorField))
            <div class="travs-alert travs-alert-danger" role="alert">{{ $errors->first($dateErrorField) }}</div>
        @endif
    @endforeach

    <div class="travs-admin-simple-page">
        <section id="notification-filter-body" class="travs-admin-simple-block">
            <div class="travs-panel-title">
                <h3>Filter Notifikasi</h3>
                <p>Cari notifikasi berdasarkan judul, pesan, jenis, status baca, atau tanggal.</p>
            </div>

            <form method="GET" class="travs-filter-grid">
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
                <div class="travs-field">
                    <label for="tanggal_mulai">Tanggal Mulai</label>
                    <input id="tanggal_mulai" name="tanggal_mulai" type="date" class="travs-input" value="{{ $filters['tanggal_mulai'] ?? '' }}">
                </div>
                <div class="travs-field">
                    <label for="tanggal_selesai">Tanggal Selesai</label>
                    <input id="tanggal_selesai" name="tanggal_selesai" type="date" class="travs-input" value="{{ $filters['tanggal_selesai'] ?? '' }}">
                </div>
                <div class="travs-filter-actions">
                    <button type="submit" class="travs-button-secondary">Terapkan Filter</button>
                    <a href="{{ route($resetRoute ?? 'admin.notifikasi.index') }}" class="travs-button-secondary">Reset</a>
                </div>
            </form>
        </section>

        <section id="notification-cleanup-body" class="travs-admin-simple-block">
            <div class="travs-panel-title">
                <h3>Bersihkan Notifikasi Lama</h3>
                <p>Hanya notifikasi yang sudah dibaca yang akan dihapus. Notifikasi belum dibaca tetap disimpan.</p>
            </div>

            <form
                method="POST"
                action="{{ route($clearRoute ?? 'admin.notifikasi.history.clear') }}"
                class="travs-notification-cleanup-form"
                onsubmit="return confirm('Hapus riwayat notifikasi yang sudah dibaca sesuai pilihan ini?');"
            >
                @csrf
                @method('DELETE')
                <div class="travs-field">
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
        </section>

        <section id="notification-list-body" class="travs-admin-simple-block">
            <div class="travs-panel-title">
                <h3>Daftar Notifikasi</h3>
                <p>{{ $listDescription ?? 'Notifikasi terbaru dari seluruh aktivitas sistem.' }}</p>
            </div>

            @php
                $newIds = array_map('intval', $newNotificationIds ?? []);
                $pageNotifications = $notifikasi->getCollection();
                $newNotifications = $pageNotifications->filter(
                    fn ($item) => ! $item->sudah_dibaca || in_array((int) $item->id, $newIds, true)
                );
                $oldNotifications = $pageNotifications->reject(
                    fn ($item) => ! $item->sudah_dibaca || in_array((int) $item->id, $newIds, true)
                );
                $notificationGroups = [
                    ['title' => 'Notifikasi Baru', 'description' => 'Pemberitahuan yang belum pernah dibuka sebelumnya.', 'items' => $newNotifications, 'new' => true],
                    ['title' => 'Notifikasi Lama', 'description' => 'Pemberitahuan yang sudah pernah dibuka.', 'items' => $oldNotifications, 'new' => false],
                ];
                // Data utama ditebalkan agar admin dapat memindai isi notifikasi tanpa mengubah pesannya.
                $highlightNotificationText = static function (?string $text, array $terms = []): string {
                    $text = (string) $text;
                    preg_match_all('/(?:CASH-)?TRAVS-[A-Z0-9-]+/i', $text, $codeMatches);

                    $terms = array_values(array_unique(array_filter(
                        array_merge($terms, $codeMatches[0] ?? []),
                        fn ($term) => filled($term)
                    )));
                    usort($terms, fn ($left, $right) => mb_strlen((string) $right) <=> mb_strlen((string) $left));

                    if ($terms === []) {
                        return e($text);
                    }

                    $pattern = '/('.implode('|', array_map(
                        fn ($term) => preg_quote((string) $term, '/'),
                        $terms
                    )).')/iu';
                    $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
                    $normalizedTerms = array_map(fn ($term) => mb_strtolower((string) $term), $terms);

                    return implode('', array_map(function ($part) use ($normalizedTerms) {
                        if (in_array(mb_strtolower((string) $part), $normalizedTerms, true)) {
                            return '<strong>'.e((string) $part).'</strong>';
                        }

                        return e((string) $part);
                    }, $parts));
                };
            @endphp

            @if ($pageNotifications->isEmpty())
                <div class="travs-table-empty">Belum ada log notifikasi.</div>
            @else
                <div class="travs-notification-list">
                    @foreach ($notificationGroups as $group)
                        @if ($group['items']->isNotEmpty())
                            <div class="travs-notification-simple-heading {{ $group['new'] ? 'is-new' : 'is-old' }}">
                                <div>
                                    <strong>{{ $group['title'] }}</strong>
                                    <span>{{ $group['description'] }}</span>
                                </div>
                                <b>{{ number_format($group['items']->count()) }}</b>
                            </div>

                            @foreach ($group['items'] as $item)
                                @php
                                    $isNewNotification = (bool) $group['new'];
                                    $hasBookingReference = in_array($item->jenis, ['pembayaran', 'pemesanan'], true)
                                        || $item->judul === 'Pemesanan baru masuk';
                                    $booking = $hasBookingReference ? $item->referensiBooking : null;
                                    $routeLabel = $booking?->jadwal?->rute
                                        ? $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan
                                        : null;
                                    $vehicleLabel = $booking?->jadwal?->kendaraan
                                        ? $booking->jadwal->kendaraan->merk_kendaraan.' ('.$booking->jadwal->kendaraan->nomor_plat.')'
                                        : null;
                                    $scheduleLabel = $booking?->jadwal?->waktu_berangkat?->translatedFormat('d M Y H:i');
                                    $bookingStatusLabel = $booking
                                        ? ucwords(str_replace('_', ' ', $booking->status_pemesanan))
                                        : null;
                                @endphp

                                <article class="travs-notification-simple-row {{ $isNewNotification ? 'is-new' : 'is-old' }}">
                                    <div class="travs-notification-simple-row__header">
                                        <div>
                                            <h4>{{ $item->judul }}</h4>
                                            <p>{{ $item->created_at->translatedFormat('d M Y H:i') }} - <strong>{{ $item->pengguna->nama }}</strong></p>
                                        </div>
                                        <div class="travs-status-stack">
                                            <span class="travs-status-pill travs-status-{{ $item->jenis }}">
                                                {{ ucfirst($item->jenis) }}
                                            </span>
                                            <span class="travs-status-pill travs-status-{{ $isNewNotification ? 'menunggu' : 'selesai' }}">
                                                {{ $isNewNotification ? 'Baru' : 'Lama' }}
                                            </span>
                                        </div>
                                    </div>

                                    <p class="travs-notification-simple-message">{!! $highlightNotificationText($item->pesan, [
                                        $booking?->kode_booking,
                                        $booking?->pengguna?->nama,
                                        $routeLabel,
                                        $bookingStatusLabel,
                                    ]) !!}</p>

                                    @if ($booking)
                                        <dl class="travs-notification-simple-details">
                                            <div>
                                                <dt>Kode Pemesanan</dt>
                                                <dd><strong>{{ $booking->kode_booking }}</strong></dd>
                                            </div>
                                            <div>
                                                <dt>Status Pemesanan</dt>
                                                <dd><strong>{{ $bookingStatusLabel }}</strong></dd>
                                            </div>
                                            <div>
                                                <dt>Rute</dt>
                                                <dd><strong>{{ $routeLabel ?? 'Tidak tersedia' }}</strong></dd>
                                            </div>
                                            <div>
                                                <dt>Armada</dt>
                                                <dd><strong>{{ $vehicleLabel ?? 'Tidak tersedia' }}</strong></dd>
                                            </div>
                                            <div>
                                                <dt>Jadwal Keberangkatan</dt>
                                                <dd><strong>{{ $scheduleLabel ?? 'Tidak tersedia' }}</strong></dd>
                                            </div>
                                        </dl>

                                        <div class="travs-form-actions travs-notification-simple-actions">
                                            <a href="{{ route('admin.pemesanan.show', $booking) }}" class="travs-button">Buka Detail Pemesanan</a>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        @endif
                    @endforeach
                </div>
            @endif

            {{ $notifikasi->links() }}
        </section>
    </div>
@endsection
