<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembayaran Diproses - RuslyTravel</title>
    <style>
        :root {
            color-scheme: light;
            --navy: #082447;
            --soft: #eef5fb;
            --line: #d6e2ef;
            --orange: #ff861f;
            --muted: #64799a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(180deg, #f7fbff 0%, #eaf2f8 100%);
            color: var(--navy);
        }

        main {
            width: min(460px, 100%);
            padding: 28px;
            border: 1px solid var(--line);
            border-radius: 28px;
            background: #ffffff;
            box-shadow: 0 18px 50px rgba(8, 36, 71, 0.12);
        }

        .mark {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            border-radius: 20px;
            background: #fff2e8;
            color: var(--orange);
            font-size: 28px;
            font-weight: 900;
        }

        h1 {
            margin: 18px 0 8px;
            font-size: 26px;
            line-height: 1.18;
        }

        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: 15px;
        }

        .hint {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 18px;
            background: var(--soft);
            color: var(--navy);
            font-weight: 700;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <main>
        <div class="mark">✓</div>
        <h1>Pembayaran sedang diproses</h1>
        <p>
            Terima kasih. Silakan kembali ke aplikasi RuslyTravel, lalu buka detail pemesanan
            untuk memperbarui status pembayaran dan melihat tiket jika pembayaran sudah berhasil.
        </p>
        <div class="hint">
            Jika status belum berubah, tunggu sebentar lalu tekan refresh status pembayaran.
        </div>
    </main>
</body>
</html>
