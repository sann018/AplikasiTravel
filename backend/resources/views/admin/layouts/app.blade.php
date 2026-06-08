<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard Admin') - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/mazer/css/app.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('vendor/mazer/css/travs-bridge.css') }}">
</head>
<body class="travs-mazer-admin">
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
                    ['label' => 'Rute Perjalanan', 'route' => 'admin.rute.index', 'active' => 'admin.rute.*', 'icon' => 'map'],
                    ['label' => 'Jadwal Perjalanan', 'route' => 'admin.jadwal.index', 'active' => 'admin.jadwal.*', 'icon' => 'calendar'],
                    ['label' => 'Pemesanan', 'route' => 'admin.pemesanan.index', 'active' => 'admin.pemesanan.*', 'icon' => 'book-open'],
                ],
            ],
            [
                'label' => 'Keuangan & Laporan',
                'items' => [
                    ['label' => 'Pembayaran', 'route' => 'admin.pembayaran.index', 'active' => 'admin.pembayaran.*', 'icon' => 'dollar-sign'],
                    ['label' => 'Biaya Operasional', 'route' => 'admin.biaya.index', 'active' => 'admin.biaya.index|admin.biaya.store|admin.biaya.update|admin.biaya.destroy', 'icon' => 'credit-card'],
                    ['label' => 'Rekap Biaya', 'route' => 'admin.biaya.rekap', 'active' => 'admin.biaya.rekap', 'icon' => 'bar-chart-2'],
                    ['label' => 'Laporan Pemesanan', 'route' => 'admin.laporan.pemesanan', 'active' => 'admin.laporan.pemesanan*', 'icon' => 'clipboard'],
                    ['label' => 'Laporan Transaksi', 'route' => 'admin.laporan.transaksi', 'active' => 'admin.laporan.transaksi*', 'icon' => 'file-text'],
                ],
            ],
            [
                'label' => 'Sistem',
                'items' => [
                    ['label' => 'Pengaturan', 'route' => 'admin.pengaturan.index', 'active' => 'admin.pengaturan.*', 'icon' => 'settings'],
                    ['label' => 'Notifikasi', 'route' => 'admin.notifikasi.index', 'active' => 'admin.notifikasi.*', 'icon' => 'bell'],
                ],
            ],
        ];
        $pageTitle = trim($__env->yieldContent('page-title', 'Dashboard'));
        $pageDescription = trim($__env->yieldContent('page-description', 'Kelola operasional travel secara terpusat dan nyaman dipakai setiap hari.'));
        $unreadAdminNotifications = \App\Models\Notifikasi::query()
            ->where('pengguna_id', auth()->id())
            ->where('sudah_dibaca', false)
            ->count();
    @endphp

    <div id="app">
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header position-relative">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div class="travs-brand-copy">
                            <div class="travs-brand-mark">
                                <span></span>
                            </div>
                            <div>
                                <h5 class="mb-0">{{ config('app.name') }}</h5>
                                <small>RuslyTravel Admin</small>
                            </div>
                        </div>

                        <div class="sidebar-toggler x">
                            <a href="#" class="sidebar-hide d-xl-none d-block" aria-label="Tutup menu">
                                <i data-feather="x"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="sidebar-menu">
                    <ul class="menu">
                        @foreach ($navGroups as $group)
                            <li class="sidebar-title">{{ $group['label'] }}</li>

                            @foreach ($group['items'] as $item)
                                @php
                                    $activePatterns = explode('|', $item['active']);
                                    $isActive = collect($activePatterns)->contains(fn (string $pattern) => request()->routeIs($pattern));
                                @endphp

                                <li class="sidebar-item {{ $isActive ? 'active' : '' }}">
                                    <a href="{{ route($item['route']) }}" class="sidebar-link">
                                        <i data-feather="{{ $item['icon'] }}"></i>
                                        <span>{{ $item['label'] }}</span>
                                        @if ($item['route'] === 'admin.notifikasi.index' && $unreadAdminNotifications > 0)
                                            <span class="travs-sidebar-badge">{{ number_format($unreadAdminNotifications) }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div id="main" class="layout-navbar">
            <header class="travs-sticky-header">
                <nav class="travs-topbar">
                    <div class="travs-topbar-left">
                        <a href="#" class="travs-icon-button d-xl-none" aria-label="Buka menu">
                            <i data-feather="menu"></i>
                        </a>
                        <div class="travs-topbar-title">
                            <h3>{{ $pageTitle }}</h3>
                            <p>{{ $pageDescription }}</p>
                        </div>
                    </div>

                    <div class="travs-topbar-right">
                        <div class="travs-topbar-chip">
                            <i data-feather="calendar"></i>
                            <span>{{ now()->translatedFormat('d F Y') }}</span>
                        </div>

                        <div class="dropdown">
                            <a
                                href="#"
                                class="travs-user-pill"
                                id="topbarUserDropdown"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >
                                <div class="travs-user-avatar">
                                    @if (auth()->user()->foto_profil)
                                        <img src="/storage/{{ ltrim(auth()->user()->foto_profil, '/') }}" alt="Foto {{ auth()->user()->nama }}">
                                    @else
                                        {{ strtoupper(substr(auth()->user()->nama, 0, 1)) }}
                                    @endif
                                </div>
                                <div class="travs-user-copy">
                                    <strong>{{ auth()->user()->nama }}</strong>
                                    <span>{{ ucfirst(auth()->user()->peran) }}</span>
                                </div>
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="topbarUserDropdown">
                                <li>
                                    <div class="dropdown-header">
                                        <h6 class="mb-0">{{ auth()->user()->nama }}</h6>
                                        <small class="text-muted">{{ ucfirst(auth()->user()->peran) }}</small>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('admin.logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i data-feather="log-out"></i>
                                            <span>Keluar</span>
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            </header>

            <div id="main-content">
                @if (session('status'))
                    <div class="travs-alert travs-alert-success mb-4">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="travs-alert travs-alert-danger mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="travs-alert travs-alert-danger mb-4">
                        {{ $errors->first() }}
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/mazer/js/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('vendor/mazer/js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
