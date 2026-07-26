<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFilterDraftResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_filter_reset_actions_clear_saved_browser_draft(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $routeNames = [
            'admin.notifikasi.index',
            'admin.laporan.transaksi',
            'admin.rute.index',
            'admin.pembayaran.index',
            'admin.kendaraan.index',
            'admin.biaya.index',
            'admin.jadwal.index',
            'admin.pemesanan.index',
            'admin.laporan.pemesanan',
            'admin.pengemudi.index',
        ];

        foreach ($routeNames as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('data-clear-form-draft', false);
        }

        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('[data-clear-form-draft]', $script);
        $this->assertStringContainsString('localStorage.removeItem(draftKey)', $script);
    }
}
