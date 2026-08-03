<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Pemesanan;
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
        $schedule = Jadwal::query()
            ->whereHas('pemesanan.pembayaran', fn ($query) => $query->where('status_bayar', 'berhasil'))
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.jadwal.show', $schedule))
            ->assertOk()
            ->assertSee('Detail Jadwal Perjalanan')
            ->assertSee('travs-admin-detail-simple', false)
            ->assertSee('Ringkasan Jadwal')
            ->assertSee('Status dan Keuangan')
            ->assertSee('Pemesanan dan Penumpang')
            ->assertSee('Biaya Operasional')
            ->assertSee('Pendapatan Kotor RuslyTravel');
    }

    public function test_schedule_detail_uses_historical_booking_fare_after_route_price_changes(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $booking = Pemesanan::query()
            ->whereIn('status_pemesanan', ['terbayar', 'selesai'])
            ->whereHas('pembayaran', fn ($query) => $query->where('status_bayar', 'berhasil'))
            ->firstOrFail();
        $schedule = $booking->jadwal;
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

    public function test_schedule_detail_only_displays_successful_bookings(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $successfulBooking = Pemesanan::query()
            ->whereIn('status_pemesanan', ['terbayar', 'selesai'])
            ->whereHas('pembayaran', fn ($query) => $query->where('status_bayar', 'berhasil'))
            ->firstOrFail();
        $cancelledBooking = $successfulBooking->replicate();
        $cancelledBooking->kode_booking = 'TRAVS-CANCELLED-HIDDEN';
        $cancelledBooking->status_pemesanan = 'dibatalkan';
        $cancelledBooking->save();

        $this->actingAs($admin)
            ->get(route('admin.jadwal.show', $successfulBooking->jadwal))
            ->assertOk()
            ->assertSee($successfulBooking->kode_booking)
            ->assertDontSee('TRAVS-CANCELLED-HIDDEN')
            ->assertDontSee('menunggu pembayaran')
            ->assertDontSee('dibatalkan');
    }
}
