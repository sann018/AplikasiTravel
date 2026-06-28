<?php

namespace Tests\Feature;

use App\Models\Pemesanan;
use App\Models\Jadwal;
use App\Models\Rute;
use App\Models\User;
use Database\Seeders\TravSDemoSeeder;
use Database\Seeders\UpcomingRouteBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingRouteBookingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_replaces_generated_schedules_using_existing_drivers(): void
    {
        $this->seed(TravSDemoSeeder::class);
        Jadwal::query()
            ->whereBetween('waktu_berangkat', [
                now()->addDay()->startOfDay(),
                now()->addDays(2)->endOfDay(),
            ])
            ->update(['status' => 'selesai']);
        $this->seed(UpcomingRouteBookingSeeder::class);

        $activeRoutes = Rute::query()->where('status_aktif', true)->get();

        foreach ($activeRoutes as $route) {
            $booking = Pemesanan::query()
                ->whereHas('jadwal', function ($query) use ($route) {
                    $query
                        ->where('rute_id', $route->id)
                        ->whereBetween('waktu_berangkat', [
                            now()->addDay()->startOfDay(),
                            now()->addDays(2)->endOfDay(),
                        ]);
                })
                ->where('kode_booking', 'like', 'TRAVS-UJI-%')
                ->where('status_pemesanan', 'terbayar')
                ->first();

            $this->assertNotNull($booking, "Pemesanan uji untuk rute {$route->id} belum dibuat.");
            $this->assertNotNull($booking->pembayaran);
            $this->assertCount(1, $booking->detailPemesanan);
            $this->assertNotNull($booking->detailPemesanan->first()?->tiketElektronik);
            $this->assertStringNotContainsString('@ruslytravel.test', $booking->pengguna->email);
            $this->assertStringNotContainsString(
                '@ruslytravel.test',
                $booking->jadwal->pengemudi->pengguna->email,
            );
        }

        $this->assertSame($activeRoutes->count(), Pemesanan::query()
            ->where('kode_booking', 'like', 'TRAVS-UJI-%')
            ->count());
        $this->assertSame(0, User::query()
            ->where('email', 'like', 'pengemudi.rute.%@ruslytravel.test')
            ->count());
    }
}
