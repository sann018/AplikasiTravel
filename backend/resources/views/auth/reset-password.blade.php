<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur Ulang Kata Sandi - Rusly Travel</title>
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
        .password-field input { padding-right: 112px; }
        .password-toggle {
            position: absolute;
            top: 1px;
            right: 1px;
            bottom: 1px;
            width: auto;
            padding: 0 12px;
            border: 0;
            border-left: 1px solid #cbd5e1;
            background: #fff;
            color: #102a4c;
            font-weight: 700;
            cursor: pointer;
        }
        .password-toggle:hover,
        .password-toggle:focus-visible { background: #edf2f7; outline: none; }
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
                <input id="password" type="password" name="password" required autocomplete="new-password">
                <button type="button" class="password-toggle" data-password-toggle data-password-target="password" aria-label="Tampilkan kata sandi" aria-pressed="false">Lihat</button>
            </div>
            @error('password') <div class="error">{{ $message }}</div> @enderror

            <label for="password_confirmation">Konfirmasi Kata Sandi</label>
            <div class="password-field">
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                <button type="button" class="password-toggle" data-password-toggle data-password-target="password_confirmation" aria-label="Tampilkan kata sandi" aria-pressed="false">Lihat</button>
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
            button.textContent = shouldShow ? 'Sembunyikan' : 'Lihat';
            button.setAttribute('aria-label', shouldShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            button.setAttribute('aria-pressed', String(shouldShow));
        });
    });
</script>
</body>
</html>
