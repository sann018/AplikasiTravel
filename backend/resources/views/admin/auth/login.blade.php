<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="travs-admin-auth">
    @php
        $settingService = app(\App\Services\SettingService::class);
        $adminAppName = (string) $settingService->get('app_name', config('app.name'));
        $adminLogoUrl = $settingService->publicAssetPath('app_logo', 'travs-logo.svg');
    @endphp
    <div class="travs-auth-wrapper">
        <div class="min-vh-100 d-flex align-items-center justify-content-center p-4">
            <div class="card shadow-lg border-0 travs-auth-card">
                <div class="card-body p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="travs-brand-copy justify-content-center mb-3">
                            <div class="travs-brand-mark travs-brand-logo">
                                <img src="{{ $adminLogoUrl }}" alt="Logo {{ $adminAppName }}" width="42" height="42" style="width:100%;height:100%;max-width:100%;max-height:100%;object-fit:contain;position:static;">
                            </div>
                            <div>
                                <h3 class="mb-0">{{ $adminAppName }}</h3>
                                <small class="text-muted">Admin</small>
                            </div>
                        </div>
                        <h3 class="mb-2">Masuk Dasbor Admin</h3>
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

                        <div class="form-group position-relative has-icon-left travs-password-control mb-4">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="form-control form-control-xl"
                                placeholder="Masukkan kata sandi"
                                maxlength="72"
                                required
                            >
                            <div class="form-control-icon">
                                <i data-feather="lock"></i>
                            </div>
                            <button type="button" class="travs-password-toggle" data-password-toggle data-password-target="password" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi" aria-pressed="false">
                                <i data-feather="eye" data-password-icon aria-hidden="true"></i>
                            </button>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block btn-lg shadow-lg mt-2">
                            Masuk
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
