<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10233f">
    <title>Atur Ulang Kata Sandi - Rusly Travel</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('rusly-travel-icon.svg') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f7fb; color: #102a4c; }
        main { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .panel { width: 100%; max-width: 460px; background: #fff; border: 1px solid #d8e0eb; padding: 28px; }
        h1 { margin: 0 0 8px; font-size: 24px; }
        p { margin: 0 0 22px; color: #64748b; line-height: 1.5; }
        label { display: block; margin: 14px 0 6px; font-weight: 700; }
        input { width: 100%; min-height: 44px; border: 1px solid #cbd5e1; padding: 10px 12px; font-size: 16px; }
        .password-field { position: relative; }
        .password-field input { padding-right: 52px; }
        .password-field input[type="password"]::-ms-reveal,
        .password-field input[type="password"]::-ms-clear { display: none; }
        .password-toggle {
            position: absolute;
            top: 1px;
            right: 1px;
            bottom: 1px;
            width: 44px;
            padding: 0;
            border: 0;
            border-left: 1px solid #cbd5e1;
            background: #fff;
            color: #000;
            cursor: pointer;
        }
        .password-toggle:hover,
        .password-toggle:focus-visible { background: #edf2f7; outline: none; }
        .password-toggle svg { width: 22px; height: 22px; stroke: currentColor; fill: none; stroke-width: 2; }
        .submit-button { width: 100%; margin-top: 22px; min-height: 46px; border: 0; background: #102a4c; color: #fff; font-weight: 700; cursor: pointer; }
        .error { margin-top: 6px; color: #b91c1c; font-size: 14px; }
    </style>
</head>
<body>
<main>
    <section class="panel">
        <h1>Atur Ulang Kata Sandi</h1>
        <p>Masukkan kata sandi baru untuk akun Rusly Travel Anda.</p>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
            @error('email') <div class="error">{{ $message }}</div> @enderror

            <label for="password">Kata Sandi Baru</label>
            <div class="password-field">
                <input id="password" type="password" name="password" required minlength="6" maxlength="20" autocomplete="new-password" placeholder="6–20 karakter, huruf + angka/simbol">
                <button type="button" class="password-toggle" data-password-toggle data-password-target="password" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi" aria-pressed="false">
                    <svg data-password-icon aria-hidden="true" viewBox="0 0 24 24">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
            @error('password') <div class="error">{{ $message }}</div> @enderror

            <label for="password_confirmation">Konfirmasi Kata Sandi</label>
            <div class="password-field">
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="6" maxlength="20" autocomplete="new-password" placeholder="Ulangi kata sandi baru">
                <button type="button" class="password-toggle" data-password-toggle data-password-target="password_confirmation" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi" aria-pressed="false">
                    <svg data-password-icon aria-hidden="true" viewBox="0 0 24 24">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>

            <button type="submit" class="submit-button">Simpan Kata Sandi Baru</button>
        </form>
    </section>
</main>
<script>
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordTarget);

        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            const label = shouldShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', String(shouldShow));
        });
    });
</script>
</body>
</html>
