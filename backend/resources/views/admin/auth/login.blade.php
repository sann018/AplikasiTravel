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
        <div class="min-vh-100 d-flex align-items-center justify-content-center p-4">
            <div class="card shadow-lg border-0 travs-auth-card">
                <div class="card-body p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="travs-brand-copy justify-content-center mb-3">
                            <div class="travs-brand-mark">
                                <span></span>
                            </div>
                            <div>
                                <h3 class="mb-0">{{ config('app.name') }}</h3>
                                <small class="text-muted">Admin</small>
                            </div>
                        </div>
                        <h2 class="mb-2">Masuk Admin</h2>
                        <p class="text-muted mb-0">
                            Kelola jadwal, pemesanan, dan laporan travel.
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
                                placeholder="Email atau nomor HP"
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
                            Masuk
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/mazer/js/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('vendor/mazer/js/app.js') }}"></script>
</body>
</html>
