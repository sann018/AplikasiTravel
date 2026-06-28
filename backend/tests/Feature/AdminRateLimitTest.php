<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_write_routes_use_action_rate_limit(): void
    {
        $routeNames = [
            'admin.banner.store',
            'admin.banner.destroy',
            'admin.kendaraan.store',
            'admin.kendaraan.update',
            'admin.kendaraan.destroy',
            'admin.pengemudi.store',
            'admin.pengemudi.update',
            'admin.pengemudi.destroy',
            'admin.rute.store',
            'admin.rute.update',
            'admin.rute.destroy',
            'admin.jadwal.store',
            'admin.jadwal.update',
            'admin.jadwal.destroy',
            'admin.pemesanan.update',
            'admin.pembayaran.confirm-cash',
            'admin.biaya.store',
            'admin.biaya.update',
            'admin.biaya.destroy',
            'admin.notifikasi.admin.history.clear',
            'admin.notifikasi.history.clear',
            'admin.settings.general.update',
            'admin.settings.booking.update',
            'admin.settings.payment.update',
            'admin.settings.baggage.update',
            'admin.settings.notification.update',
            'admin.pengaturan.update',
            'admin.pengaturan.account.update',
            'admin.logout',
        ];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route {$routeName} tidak ditemukan.");
            $this->assertContains(
                'throttle:travs-admin-action',
                $route->gatherMiddleware(),
                "Route {$routeName} belum memakai rate limit aksi admin.",
            );
        }
    }

    public function test_admin_login_and_pakasir_webhook_are_rate_limited(): void
    {
        $loginRoute = Route::getRoutes()->getByName('admin.login.store');
        $webhookRoute = collect(Route::getRoutes())->first(
            fn ($route) => $route->uri() === 'api/payments/pakasir/webhook',
        );

        $this->assertContains('throttle:travs-auth', $loginRoute?->gatherMiddleware() ?? []);
        $this->assertContains('throttle:travs-webhook', $webhookRoute?->gatherMiddleware() ?? []);
    }

    public function test_admin_action_rate_limit_stops_repeated_submit(): void
    {
        $admin = User::factory()->create([
            'nama' => 'Admin Rate Limit',
            'email' => 'admin-rate-limit@example.com',
            'peran' => 'admin',
            'password' => bcrypt('password'),
        ]);

        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($admin)
                ->delete(route('admin.notifikasi.admin.history.clear'), ['period' => 'all_read'])
                ->assertRedirect(route('admin.notifikasi.admin'));
        }

        $this->actingAs($admin)
            ->delete(route('admin.notifikasi.admin.history.clear'), ['period' => 'all_read'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Terlalu banyak aksi dalam waktu singkat. Tunggu sebentar, lalu coba lagi.');
    }
}
