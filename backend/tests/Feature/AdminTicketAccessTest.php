<?php

namespace Tests\Feature;

use App\Models\Pemesanan;
use App\Models\TiketElektronik;
use App\Models\User;
use Database\Seeders\TravSDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_download_passenger_ticket_from_booking(): void
    {
        $this->seed(TravSDemoSeeder::class);

        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $booking = Pemesanan::query()->where('kode_booking', 'TRAVS-DEMO-001')->firstOrFail();
        $ticket = TiketElektronik::query()
            ->whereHas('detailPemesanan', fn ($query) => $query->where('pemesanan_id', $booking->id))
            ->firstOrFail();

        $detailResponse = $this->actingAs($admin)
            ->get(route('admin.pemesanan.show', $booking));

        $detailResponse->assertOk();
        $detailResponse->assertSee('Aksi Tiket');
        $detailResponse->assertSee(route('admin.pemesanan.tiket.show', [$booking, $ticket]), false);
        $detailResponse->assertSee(route('admin.pemesanan.tiket.download', [$booking, $ticket]), false);
        $detailResponse->assertDontSee($ticket->token_unik_qr);

        $viewResponse = $this->actingAs($admin)
            ->get(route('admin.pemesanan.tiket.show', [$booking, $ticket]));

        $viewResponse->assertOk();
        $viewResponse->assertSee('Tiket Elektronik Penumpang');
        $viewResponse->assertSee($ticket->detailPemesanan->nama_penumpang);
        $viewResponse->assertSee('data-ticket-download', false);
        $viewResponse->assertSee('data-auto-download="false"', false);
        $viewResponse->assertHeader('cache-control', 'max-age=0, no-store, private');

        $downloadResponse = $this->actingAs($admin)
            ->get(route('admin.pemesanan.tiket.download', [$booking, $ticket]));

        $downloadResponse->assertOk();
        $downloadResponse->assertSee('data-auto-download="true"', false);
        $this->assertFalse((bool) $ticket->fresh()->sudah_dipindai);
    }

    public function test_admin_cannot_open_ticket_from_another_booking(): void
    {
        $this->seed(TravSDemoSeeder::class);

        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $booking = Pemesanan::query()->where('kode_booking', 'TRAVS-DEMO-001')->firstOrFail();
        $otherTicket = TiketElektronik::query()
            ->whereHas('detailPemesanan', fn ($query) => $query->where('pemesanan_id', '!=', $booking->id))
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.pemesanan.tiket.show', [$booking, $otherTicket]))
            ->assertNotFound();
    }

    public function test_non_admin_cannot_open_admin_passenger_ticket(): void
    {
        $this->seed(TravSDemoSeeder::class);

        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();
        $ticket = TiketElektronik::query()->with('detailPemesanan')->firstOrFail();
        $booking = $ticket->detailPemesanan->pemesanan;

        $this->actingAs($passenger)
            ->get(route('admin.pemesanan.tiket.show', [$booking, $ticket]))
            ->assertForbidden();
    }
}
