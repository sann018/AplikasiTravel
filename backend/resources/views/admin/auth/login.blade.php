<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/mazer/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/mazer/css/travs-bridge.css') }}">
</head>
<body class="travs-mazer-auth">
    <div class="travs-auth-wrapper">
        <div class="row g-0 min-vh-100">
            <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center travs-auth-aside">
                <div class="travs-auth-aside-copy">
                    <div class="travs-brand-copy text-white mb-4">
                        <div class="travs-brand-mark">
                            <span></span>
                        </div>
                        <div>
                            <h3 class="mb-0 text-white">{{ config('app.name') }}</h3>
                            <small class="text-white-50">Mazer Admin Experience</small>
                        </div>
                    </div>
                    <span class="badge bg-light-primary mb-3">Admin Access</span>
                    <h1 class="text-white mb-3">Masuk ke dashboard admin TravS.</h1>
                    <p class="text-white-50 mb-0">
                        Kelola jadwal, pemesanan, biaya operasional, dan laporan dari panel admin
                        yang sudah disejajarkan ke tampilan Mazer berbasis Bootstrap 5.
                    </p>
                </div>
            </div>

            <div class="col-lg-6 d-flex align-items-center justify-content-center p-4">
                <div class="card shadow-lg border-0 travs-auth-card">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex align-items-center gap-3 mb-4 d-lg-none">
                            <div class="travs-brand-mark">
                                <span></span>
                            </div>
                            <div>
                                <h4 class="mb-0">{{ config('app.name') }}</h4>
                                <small class="text-muted">Admin Dashboard</small>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h2 class="mb-2">Login Admin</h2>
                            <p class="text-muted mb-0">
                                Gunakan email atau nomor HP admin untuk masuk ke panel pengelolaan travel.
                            </p>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.login.store') }}">
                            @csrf

                            <div class="form-group position-relative has-icon-left mb-4">
                                <input
                                    id="login"
                                    name="login"
                                    type="text"
                                    class="form-control form-control-xl"
                                    value="{{ old('login') }}"
                                    placeholder="admin@travs.com atau 0812..."
                                    required
                                >
                                <div class="form-control-icon">
                                    <i data-feather="user"></i>
                                </div>
                            </div>

                            <div class="form-group position-relative has-icon-left mb-4">
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    class="form-control form-control-xl"
                                    placeholder="Masukkan kata sandi"
                                    required
                                >
                                <div class="form-control-icon">
                                    <i data-feather="lock"></i>
                                </div>
                            </div>

                            <div class="form-check form-check-lg d-flex align-items-center mb-4">
                                <input class="form-check-input me-2" type="checkbox" name="remember" value="1" id="remember">
                                <label class="form-check-label text-gray-600" for="remember">
                                    Ingat sesi login pada perangkat ini
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block btn-lg shadow-lg mt-2">
                                Masuk ke Dashboard
                            </button>
                        </form>

                        <div class="mt-4">
                            <div class="alert alert-light-primary mb-0">
                                Demo admin: <strong>admin@travs.com</strong> dengan kata sandi <strong>admin12345</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/mazer/js/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('vendor/mazer/js/app.js') }}"></script>
</body>
</html>
