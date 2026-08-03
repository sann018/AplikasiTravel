<?php

namespace App\Providers;

use App\Models\KursiJadwal;
use App\Observers\KursiJadwalObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Tempat mendaftarkan service tambahan jika nanti dibutuhkan aplikasi.
     */
    public function register(): void {}

    /**
     * Menyiapkan pembagian halaman dan batas permintaan saat aplikasi mulai berjalan.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.travs');
        Paginator::defaultSimpleView('vendor.pagination.travs');
        KursiJadwal::observe(KursiJadwalObserver::class);

        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        // Batas permintaan mencegah klik berulang menjalankan query terlalu sering
        // dan membantu menjaga endpoint penting dari spam.
        RateLimiter::for('travs-read', function (Request $request) {
            return Limit::perMinute(90)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('travs-auth', function (Request $request) {
            return Limit::perMinute(8)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('travs-verification-code', function (Request $request) {
            return Limit::perMinute(6)->by($this->emailRateLimitKey($request));
        });

        RateLimiter::for('travs-verification-resend', function (Request $request) {
            return Limit::perMinute(3)->by($this->emailRateLimitKey($request));
        });

        RateLimiter::for('travs-action', function (Request $request) {
            return Limit::perMinute(20)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('travs-upload', function (Request $request) {
            return Limit::perMinute(6)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('travs-admin-action', function (Request $request) {
            return Limit::perMinute(12)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('travs-webhook', function (Request $request) {
            return Limit::perMinute(60)->by('webhook:'.$request->ip());
        });
    }

    private function rateLimitKey(Request $request): string
    {
        $user = $request->user();

        if ($user) {
            return 'user:'.$user->getAuthIdentifier();
        }

        return 'ip:'.$request->ip();
    }

    private function emailRateLimitKey(Request $request): string
    {
        $email = strtolower(trim((string) $request->input('email', '')));

        return 'email:'.$email.'|ip:'.$request->ip();
    }
}
