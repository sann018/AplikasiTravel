<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\TiketElektronik;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_driver_can_load_owned_trip_manifest_from_paid_bookings(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()
            ->whereHas('pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->whereHas('pemesanan', fn ($query) => $query->where('status_pemesanan', 'terbayar'))
            ->firstOrFail();

        Sanctum::actingAs($driver);

        $response = $this->getJson("/api/pengemudi/trips/{$jadwal->id}/manifest");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $jadwal->id)
            ->assertJsonCount(2, 'data.manifest')
            ->assertJsonStructure([
                'data' => [
                    'manifest' => [
                        [
                            'booking_id',
                            'detail_id',
                            'nama_penumpang',
                            'nomor_kursi',
                            'pickup',
                            'ticket',
                        ],
                    ],
                ],
            ]);
    }

    public function test_driver_can_scan_owned_trip_ticket_once(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $ticket = TiketElektronik::query()
            ->whereHas('detailPemesanan.pemesanan.jadwal.pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->where('sudah_dipindai', false)
            ->firstOrFail();

        Sanctum::actingAs($driver);

        $response = $this->postJson('/api/pengemudi/tickets/scan', [
            'token' => $ticket->token_unik_qr,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.passenger.nama_penumpang', $ticket->detailPemesanan->nama_penumpang);

        $ticket->refresh();
        $this->assertTrue($ticket->sudah_dipindai);
        $this->assertNotNull($ticket->dipindai_pada);
        $this->assertSame('hadir', $ticket->detailPemesanan()->firstOrFail()->status_kehadiran);
    }

    public function test_driver_cannot_scan_same_ticket_twice(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $ticket = TiketElektronik::query()
            ->whereHas('detailPemesanan.pemesanan.jadwal.pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->where('sudah_dipindai', false)
            ->firstOrFail();

        Sanctum::actingAs($driver);

        $this->postJson('/api/pengemudi/tickets/scan', [
            'token' => $ticket->token_unik_qr,
        ])->assertOk();

        $this->postJson('/api/pengemudi/tickets/scan', [
            'token' => $ticket->token_unik_qr,
        ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_driver_cannot_scan_ticket_from_other_trip(): void
    {
        $otherDriver = User::query()->where('email', 'pengemudi2@travs.com')->firstOrFail();
        $ticket = TiketElektronik::query()
            ->whereHas('detailPemesanan.pemesanan.jadwal.pengemudi.pengguna', fn ($query) => $query->where('email', 'pengemudi@travs.com'))
            ->where('sudah_dipindai', false)
            ->firstOrFail();

        Sanctum::actingAs($otherDriver);

        $this->postJson('/api/pengemudi/tickets/scan', [
            'token' => $ticket->token_unik_qr,
        ])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
