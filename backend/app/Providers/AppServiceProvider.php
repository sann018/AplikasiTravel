<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.travs');
        Paginator::defaultSimpleView('vendor.pagination.travs');

        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        // Rate limit ini menjadi pagar backend agar klik berulang tidak terus menjalankan query.
        RateLimiter::for('travs-read', function (Request $request) {
            return Limit::perMinute(90)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('travs-auth', function (Request $request) {
            return Limit::perMinute(8)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('travs-action', function (Request $request) {
            return Limit::perMinute(20)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('travs-upload', function (Request $request) {
            return Limit::perMinute(6)->by($this->rateLimitKey($request));
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
}
