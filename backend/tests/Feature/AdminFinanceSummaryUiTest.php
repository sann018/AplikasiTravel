<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinanceSummaryUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_operational_cost_page_shows_filtered_summary_cards(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.biaya.index'));

        $response->assertOk()
            ->assertSee('Total Biaya Periode')
            ->assertSee('Jumlah Transaksi')
            ->assertSee('Rute dengan Biaya Tertinggi')
            ->assertViewHas('periodSummary');
    }

    public function test_booking_report_shows_status_and_value_summary_cards(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.laporan.pemesanan'));

        $response->assertOk()
            ->assertSee('Total Pemesanan')
            ->assertSee('Terbayar')
            ->assertSee('Dibatalkan')
            ->assertSee('Selesai')
            ->assertSee('Total Nilai Pemesanan')
            ->assertSee('Periode Aktif')
            ->assertViewHas('activePeriodLabel', 'Semua periode');
    }
}
