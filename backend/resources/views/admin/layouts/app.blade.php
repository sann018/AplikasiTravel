<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10233f">
    <title>@yield('title', 'Admin') - {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('rusly-travel-icon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="travs-admin-body">
    @php
        $navGroups = [
            [
                'label' => 'Utama',
                'items' => [
                    ['label' => 'Dasbor', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'grid'],
                ],
            ],
            [
                'label' => 'Kelola Operasional',
                'items' => [
                    ['label' => 'Armada Kendaraan', 'route' => 'admin.kendaraan.index', 'active' => 'admin.kendaraan.*', 'icon' => 'truck'],
                    ['label' => 'Pengemudi', 'route' => 'admin.pengemudi.index', 'active' => 'admin.pengemudi.*', 'icon' => 'users'],
                    ['label' => 'Petugas Loket', 'route' => 'admin.petugas-loket.index', 'active' => 'admin.petugas-loket.*', 'icon' => 'user-check'],
                    ['label' => 'Rute Perjalanan', 'route' => 'admin.rute.index', 'active' => 'admin.rute.*', 'icon' => 'map'],
                    ['label' => 'Manajemen Jadwal', 'route' => 'admin.jadwal.index', 'active' => 'admin.jadwal.*', 'icon' => 'calendar'],
                    ['label' => 'Pemesanan', 'route' => 'admin.pemesanan.index', 'active' => 'admin.pemesanan.*', 'icon' => 'book-open'],
                ],
            ],
            [
                'label' => 'Keuangan & Laporan',
                'items' => [
                    ['label' => 'Biaya Operasional', 'route' => 'admin.biaya.index', 'active' => 'admin.biaya.index|admin.biaya.store|admin.biaya.update|admin.biaya.destroy', 'icon' => 'credit-card'],
                    ['label' => 'Laporan Pemesanan', 'route' => 'admin.laporan.pemesanan', 'active' => 'admin.laporan.pemesanan*', 'icon' => 'clipboard'],
                    ['label' => 'Laporan Transaksi', 'route' => 'admin.laporan.transaksi', 'active' => 'admin.laporan.transaksi*', 'icon' => 'file-text'],
                ],
            ],
            [
                'label' => 'Sistem',
                'items' => [
                    ['label' => 'Pengaturan', 'route' => 'admin.settings.index', 'active' => 'admin.settings.*|admin.pengaturan.*|admin.banner.*', 'icon' => 'settings'],
                    ['label' => 'Notifikasi Admin', 'route' => 'admin.notifikasi.admin', 'active' => 'admin.notifikasi.admin*', 'icon' => 'bell'],
                ],
            ],
        ];
        $pageTitle = trim($__env->yieldContent('page-title', 'Dasbor'));
        $pageDescription = trim($__env->yieldContent('page-description', 'Kelola operasional travel secara terpusat dan nyaman dipakai setiap hari.'));
        $settingService = app(\App\Services\SettingService::class);
        $adminAppName = (string) $settingService->get('app_name', config('app.name'));
        $adminLogoUrl = $settingService->publicAssetPath('app_logo', 'travs-logo.svg');
        $unreadAdminNotifications = \App\Models\Notifikasi::query()
            ->where('pengguna_id', auth()->id())
            ->where('sudah_dibaca', false)
            ->count();
    @endphp

    <div id="app" class="travs-admin-shell">
        <aside id="sidebar" class="travs-sidebar" data-admin-sidebar>
                <div class="travs-sidebar-head">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div class="travs-brand-copy">
                            <div class="travs-brand-mark travs-brand-logo">
                                <img src="{{ $adminLogoUrl }}" alt="Logo {{ $adminAppName }}" width="42" height="42" style="width:100%;height:100%;max-width:100%;max-height:100%;object-fit:contain;position:static;">
                            </div>
                            <div>
                                <h5 class="mb-0">{{ $adminAppName }}</h5>
                                <small>{{ $adminAppName }} Admin</small>
                            </div>
                        </div>

                        <div>
                            <button type="button" class="travs-icon-button d-xl-none" data-sidebar-close aria-label="Tutup menu">
                                <i data-feather="x"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <nav class="travs-sidebar-menu" aria-label="Navigasi admin">
                    <ul class="travs-menu">
                        @foreach ($navGroups as $group)
                            <li class="travs-nav-group-title">{{ $group['label'] }}</li>

                            @foreach ($group['items'] as $item)
                                @php
                                    $activePatterns = explode('|', $item['active']);
                                    $isActive = collect($activePatterns)->contains(fn (string $pattern) => request()->routeIs($pattern));
                                @endphp

                                <li class="travs-sidebar-item {{ $isActive ? 'active' : '' }}">
                                    <a href="{{ route($item['route']) }}" class="travs-nav-link {{ $isActive ? 'is-active' : '' }}">
                                        <i data-feather="{{ $item['icon'] }}"></i>
                                        <span>{{ $item['label'] }}</span>
                                        @if ($item['route'] === 'admin.notifikasi.admin' && $unreadAdminNotifications > 0)
                                            <span class="travs-sidebar-badge">{{ number_format($unreadAdminNotifications) }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        @endforeach

                        {{-- Tombol keluar dipindahkan ke sidebar agar aksi logout mudah ditemukan tanpa membuka dropdown profil. --}}
                        <li class="travs-sidebar-item travs-sidebar-logout-item">
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="travs-nav-link travs-sidebar-logout-link">
                                    <i data-feather="log-out"></i>
                                    <span>Keluar</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </nav>
        </aside>
        <button type="button" class="travs-sidebar-backdrop" data-sidebar-backdrop hidden aria-label="Tutup menu"></button>

        <main id="main" class="travs-admin-main">
            <header class="travs-sticky-header">
                <nav class="travs-topbar">
                    <div class="travs-topbar-left">
                        <button type="button" class="travs-icon-button d-xl-none" data-sidebar-open aria-label="Buka menu">
                            <i data-feather="menu"></i>
                        </button>
                        <div class="travs-topbar-title">
                            <h3>{{ $pageTitle }}</h3>
                            <p>{{ $pageDescription }}</p>
                        </div>
                    </div>

                    <div class="travs-topbar-right">
                        <a
                            href="{{ route('admin.notifikasi.admin') }}"
                            class="travs-topbar-notification"
                            data-admin-notification-link
                            aria-label="Buka notifikasi, {{ number_format($unreadAdminNotifications) }} belum dibaca"
                            title="Notifikasi admin"
                        >
                            <i data-feather="bell"></i>
                            @if ($unreadAdminNotifications > 0)
                                <span class="travs-topbar-notification-badge">{{ $unreadAdminNotifications > 99 ? '99+' : number_format($unreadAdminNotifications) }}</span>
                            @endif
                        </a>

                        <div class="travs-topbar-chip">
                            <i data-feather="calendar"></i>
                            <span>{{ now()->translatedFormat('d F Y') }}</span>
                        </div>

                        <div class="travs-user-pill" aria-label="Akun admin aktif">
                                <div class="travs-user-avatar">
                                    @if (auth()->user()->foto_profil)
                                        <img src="/storage/{{ ltrim(auth()->user()->foto_profil, '/') }}" alt="Foto {{ auth()->user()->nama }}">
                                    @else
                                        {{ strtoupper(substr(auth()->user()->nama, 0, 1)) }}
                                    @endif
                                </div>
                                <div class="travs-user-copy">
                                    <strong></strong>
                                </div>
                        </div>
                    </div>
                </nav>
            </header>

            <div id="main-content">
                @include('admin.partials.flash-message')

                @yield('content')
            </div>
        </main>
    </div>

    <div class="travs-photo-preview-modal" data-photo-preview-modal hidden>
        <button type="button" class="travs-photo-preview-close" data-photo-preview-close aria-label="Tutup preview foto">
            &times;
        </button>
        <img src="" alt="Preview foto" data-photo-preview-image>
    </div>

    <script>
        document.addEventListener('click', function (event) {
            const previewTarget = event.target.closest('.travs-photo-preview-trigger');
            const modal = document.querySelector('[data-photo-preview-modal]');

            if (previewTarget && modal) {
                const previewImage = modal.querySelector('[data-photo-preview-image]');
                previewImage.src = previewTarget.dataset.previewSrc || previewTarget.currentSrc || previewTarget.src;
                previewImage.alt = previewTarget.alt || 'Preview foto';
                modal.hidden = false;
                document.body.classList.add('travs-photo-preview-open');
                return;
            }

            if (
                event.target.matches('[data-photo-preview-close]') ||
                event.target.matches('[data-photo-preview-modal]')
            ) {
                const image = modal?.querySelector('[data-photo-preview-image]');

                if (image) {
                    image.src = '';
                }

                modal.hidden = true;
                document.body.classList.remove('travs-photo-preview-open');
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            const modal = document.querySelector('[data-photo-preview-modal]');
            const image = modal?.querySelector('[data-photo-preview-image]');

            if (image) {
                image.src = '';
            }

            if (modal) {
                modal.hidden = true;
            }

            document.body.classList.remove('travs-photo-preview-open');
        });
    </script>
    @stack('scripts')
</body>
</html>
