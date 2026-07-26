@extends('admin.layouts.app')

@section('title', $pageTitle ?? 'Log Notifikasi')
@section('page-title', $pageTitle ?? 'Log Notifikasi')
@section('page-description', $pageDescription ?? 'Pantau notifikasi untuk penumpang, pengemudi, dan admin.')

@section('content')
    @if (session('success'))
        <div class="travs-alert travs-alert-success alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->has('period'))
        <div class="travs-alert travs-alert-danger alert alert-danger" role="alert">{{ $errors->first('period') }}</div>
    @endif

    @foreach (['tanggal_mulai', 'tanggal_selesai'] as $dateErrorField)
        @if ($errors->has($dateErrorField))
            <div class="travs-alert travs-alert-danger alert alert-danger" role="alert">{{ $errors->first($dateErrorField) }}</div>
        @endif
    @endforeach

    <div class="travs-admin-simple-page">
        <section id="notification-filter-body" class="travs-admin-simple-block">
            <div class="travs-panel-title">
                <h3>Filter Notifikasi</h3>
                <p>Cari notifikasi berdasarkan judul, pesan, jenis, status baca, atau tanggal.</p>
            </div>

            <form method="GET" class="travs-filter-grid d-grid">
                <div class="travs-field">
                    <label for="search" class="form-label">Cari</label>
                    <input id="search" name="search" class="travs-input form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Judul, pesan, atau nama pengguna">
                </div>
                <div class="travs-field">
                    <label for="jenis" class="form-label">Jenis</label>
                    <select id="jenis" name="jenis" class="travs-select form-select">
                        <option value="">Semua jenis</option>
                        @foreach (['pembayaran' => 'Pembayaran', 'perjalanan' => 'Perjalanan', 'pemesanan' => 'Pemesanan', 'sistem' => 'Sistem'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['jenis'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="travs-field">
                    <label for="dibaca" class="form-label">Status Baca</label>
                    <select id="dibaca" name="dibaca" class="travs-select form-select">
                        <option value="">Semua status</option>
                        <option value="1" @selected(($filters['dibaca'] ?? '') === '1')>Sudah dibaca</option>
                        <option value="0" @selected(($filters['dibaca'] ?? '') === '0')>Belum dibaca</option>
                    </select>
                </div>
                <div class="travs-field">
                    <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                    <input id="tanggal_mulai" name="tanggal_mulai" type="date" class="travs-input form-control" value="{{ $filters['tanggal_mulai'] ?? '' }}">
                </div>
                <div class="travs-field">
                    <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                    <input id="tanggal_selesai" name="tanggal_selesai" type="date" class="travs-input form-control" value="{{ $filters['tanggal_selesai'] ?? '' }}">
                </div>
                <div class="travs-filter-actions d-flex align-items-end flex-wrap">
                    <button type="submit" class="travs-button btn btn-primary">Terapkan Filter</button>
                    <a href="{{ route($resetRoute ?? 'admin.notifikasi.index') }}" class="travs-button-secondary btn btn-outline-secondary">Reset</a>
                    <a href="{{ route($resetRoute ?? 'admin.notifikasi.index', ['dibaca' => '0']) }}" class="travs-button-secondary btn btn-outline-secondary">Tampilkan hanya belum dibaca</a>
                </div>
            </form>
        </section>

        <details id="notification-cleanup-body" class="travs-admin-simple-block travs-notification-more-actions">
            <summary class="travs-button-secondary btn btn-outline-secondary">Aksi Lainnya</summary>
            <div class="travs-notification-more-actions__body">
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
                        <label for="cleanup-period" class="form-label">Periode</label>
                        <select id="cleanup-period" name="period" class="travs-select form-select" required>
                            <option value="7_days">Lebih lama dari 7 hari</option>
                            <option value="30_days" selected>Lebih lama dari 30 hari</option>
                            <option value="90_days">Lebih lama dari 90 hari</option>
                            <option value="all_read">Semua yang sudah dibaca</option>
                        </select>
                    </div>
                    <button type="submit" class="travs-button-link travs-button-link-danger btn btn-link">Hapus Riwayat</button>
                </form>
            </div>
        </details>

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
                            <section class="travs-notification-group {{ $group['new'] ? 'is-new' : 'is-old' }}">
                                <div class="travs-notification-simple-heading {{ $group['new'] ? 'is-new' : 'is-old' }}">
                                    <div>
                                        <strong>{{ $group['title'] }}</strong>
                                        <span>{{ $group['description'] }}</span>
                                    </div>
                                    <b>{{ number_format($group['items']->count()) }}</b>
                                </div>

                                @php
                                    $datedNotifications = $group['items']->groupBy(
                                        fn ($item) => $item->created_at->toDateString()
                                    );
                                @endphp

                                @foreach ($datedNotifications as $dateNotifications)
                                @php
                                    $notificationDate = $dateNotifications->first()->created_at;
                                    $notificationDateLabel = $notificationDate->isToday()
                                        ? 'Hari ini'
                                        : ($notificationDate->isYesterday()
                                            ? 'Kemarin'
                                            : $notificationDate->translatedFormat('d M Y'));
                                @endphp

                                    <div class="travs-notification-date-group">
                                        <div class="travs-notification-date-heading">
                                            <strong>{{ $notificationDateLabel }}</strong>
                                        </div>

                                        @foreach ($dateNotifications as $item)
                                        @php
                                            $isNewNotification = (bool) $group['new'];
                                            $referenceText = trim($item->judul.' '.$item->pesan);
                                            preg_match('/(?:CASH-)?TRAVS-[A-Z0-9-]+/i', $referenceText, $bookingCodeMatch);
                                            $detectedBookingCode = $bookingCodeMatch[0] ?? null;
                                            $hasScheduleReference = $item->hasScheduleReference();
                                            $schedule = $hasScheduleReference ? $item->referensiJadwal : null;
                                            $hasBookingReference = ! $hasScheduleReference && (
                                                in_array($item->jenis, ['pembayaran', 'pemesanan'], true)
                                                || str_contains(mb_strtolower($referenceText), 'pemesanan')
                                                || filled($detectedBookingCode)
                                            );
                                            $booking = $hasBookingReference ? $item->referensiBooking : null;
                                            $bookingSearchCode = $booking?->kode_booking ?? $detectedBookingCode;
                                            $bookingActionUrl = $booking
                                                ? route('admin.pemesanan.show', $booking)
                                                : ($hasBookingReference && filled($bookingSearchCode)
                                                    ? route('admin.pemesanan.index', ['search' => $bookingSearchCode])
                                                    : null);
                                            $bookingActionLabel = $booking ? 'Buka Detail Pemesanan' : 'Cari Pemesanan';
                                            $notificationActionUrl = $schedule
                                                ? route('admin.jadwal.show', $schedule)
                                                : $bookingActionUrl;
                                            $notificationActionLabel = $schedule
                                                ? 'Buka Detail Jadwal'
                                                : $bookingActionLabel;
                                            $routeLabel = $booking?->jadwal?->rute
                                                ? $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan
                                                : null;
                                            $bookingStatusLabel = $booking
                                                ? ucwords(str_replace('_', ' ', $booking->status_pemesanan))
                                                : null;
                                        @endphp

                                    <article class="travs-notification-simple-row {{ $isNewNotification ? 'is-new' : 'is-old' }}">
                                    <div class="travs-notification-simple-row__header">
                                        <div>
                                            <h4>{{ $item->judul }}</h4>
                                            <p>{{ $item->created_at->format('H:i') }} - <strong>{{ $item->pengguna->nama }}</strong></p>
                                        </div>
                                        <div class="travs-status-stack">
                                            <span class="travs-status-pill travs-status-{{ $item->jenis }} badge rounded-pill">
                                                {{ ucfirst($item->jenis) }}
                                            </span>
                                            @if ($isNewNotification)
                                                <span class="travs-status-pill travs-status-menunggu badge rounded-pill">Baru</span>
                                            @endif
                                        </div>
                                    </div>

                                    <p class="travs-notification-simple-message">{!! $highlightNotificationText($item->pesan, [
                                        $bookingSearchCode,
                                        $booking?->pengguna?->nama,
                                        $routeLabel,
                                        $bookingStatusLabel,
                                    ]) !!}</p>

                                    @if ($notificationActionUrl)
                                        <div class="travs-form-actions travs-notification-simple-actions d-flex justify-content-end">
                                            <a href="{{ $notificationActionUrl }}" class="travs-button btn btn-primary">{{ $notificationActionLabel }}</a>
                                        </div>
                                    @endif
                                    </article>
                                        @endforeach
                                    </div>
                                @endforeach
                            </section>
                        @endif
                    @endforeach
                </div>
            @endif

            {{ $notifikasi->links() }}
        </section>
    </div>
@endsection
