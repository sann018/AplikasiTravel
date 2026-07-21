<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScheduleDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_open_read_only_schedule_detail(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $schedule = Jadwal::query()->whereHas('pemesanan')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.jadwal.show', $schedule))
            ->assertOk()
            ->assertSee('Detail Jadwal Perjalanan')
            ->assertSee('travs-admin-detail-simple', false)
            ->assertSee('Ringkasan Jadwal')
            ->assertSee('Status dan Keuangan')
            ->assertSee('Pemesanan dan Penumpang')
            ->assertSee('Biaya Operasional')
            ->assertSee('Pendapatan Diterima');
    }

    public function test_schedule_detail_uses_historical_booking_fare_after_route_price_changes(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $schedule = Jadwal::query()->whereHas('pemesanan')->firstOrFail();
        $booking = $schedule->pemesanan()->firstOrFail();
        $booking->update([
            'jumlah_kursi' => 2,
            'subtotal_tiket' => 240000,
            'total_harga' => 240000,
        ]);
        $schedule->rute()->update(['harga_tiket' => 999999]);

        $this->actingAs($admin)
            ->get(route('admin.jadwal.show', $schedule))
            ->assertOk()
            ->assertSee('Rp120.000/orang')
            ->assertSee('Total Rp240.000')
            ->assertDontSee('Rp999.999/orang');
    }
}
