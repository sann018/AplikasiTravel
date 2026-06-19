<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\DetailPemesanan;
use App\Models\KursiJadwal;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
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

    public function test_driver_manifest_includes_cash_booking_waiting_for_payment(): void
    {
        [$driver, $jadwal, $booking, $detail] = $this->createCashPendingBookingForDriver();

        Sanctum::actingAs($driver);

        $response = $this->getJson("/api/pengemudi/trips/{$jadwal->id}/manifest");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'booking_id' => $booking->id,
                'detail_id' => $detail->id,
                'status_pemesanan' => 'menunggu_pembayaran',
                'can_confirm_cash' => true,
            ]);
    }

    public function test_driver_can_confirm_cash_payment_and_issue_ticket(): void
    {
        [$driver, $jadwal, $booking, $detail] = $this->createCashPendingBookingForDriver();

        Sanctum::actingAs($driver);

        $response = $this->postJson("/api/pengemudi/trips/{$jadwal->id}/passengers/{$detail->id}/confirm-cash");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.pembayaran.status', 'berhasil')
            ->assertJsonPath('data.pembayaran.can_confirm_cash', false)
            ->assertJsonPath('data.ticket.sudah_dipindai', false);

        $booking->refresh();
        $this->assertSame('terbayar', $booking->status_pemesanan);
        $this->assertSame('berhasil', $booking->pembayaran()->firstOrFail()->status_bayar);
        $this->assertNotNull($detail->tiketElektronik()->first());
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

    public function test_driver_can_mark_passenger_pickup(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $detail = DetailPemesanan::query()
            ->whereHas('pemesanan.jadwal.pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->whereHas('pemesanan', fn ($query) => $query->where('status_pemesanan', 'terbayar'))
            ->where('status_kehadiran', 'menunggu')
            ->firstOrFail();

        $jadwal = $detail->pemesanan->jadwal;

        Sanctum::actingAs($driver);

        $response = $this->postJson("/api/pengemudi/trips/{$jadwal->id}/passengers/{$detail->id}/pickup");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.detail_id', $detail->id)
            ->assertJsonPath('data.pickup.is_picked_up', true);

        $detail->refresh();
        $this->assertNotNull($detail->dijemput_pada);
        $this->assertSame('menunggu', $detail->status_kehadiran);
    }

    public function test_driver_can_mark_passenger_dropoff_after_ticket_scan(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $ticket = TiketElektronik::query()
            ->whereHas('detailPemesanan.pemesanan.jadwal.pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->where('sudah_dipindai', false)
            ->firstOrFail();

        $detail = $ticket->detailPemesanan()->with('pemesanan.jadwal')->firstOrFail();
        $jadwal = $detail->pemesanan->jadwal;

        Sanctum::actingAs($driver);

        $this->postJson('/api/pengemudi/tickets/scan', [
            'token' => $ticket->token_unik_qr,
        ])->assertOk();

        $response = $this->postJson("/api/pengemudi/trips/{$jadwal->id}/passengers/{$detail->id}/dropoff");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.detail_id', $detail->id)
            ->assertJsonPath('data.dropoff.is_dropped_off', true);

        $detail->refresh();
        $this->assertSame('hadir', $detail->status_kehadiran);
        $this->assertNotNull($detail->diturunkan_pada);
    }

    public function test_driver_cannot_mark_dropoff_before_passenger_is_present(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $detail = DetailPemesanan::query()
            ->whereHas('pemesanan.jadwal.pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->whereHas('pemesanan', fn ($query) => $query->where('status_pemesanan', 'terbayar'))
            ->where('status_kehadiran', 'menunggu')
            ->firstOrFail();

        $jadwal = $detail->pemesanan->jadwal;

        Sanctum::actingAs($driver);

        $this->postJson("/api/pengemudi/trips/{$jadwal->id}/passengers/{$detail->id}/dropoff")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNull($detail->fresh()->diturunkan_pada);
    }

    public function test_driver_cannot_pickup_passenger_from_other_trip(): void
    {
        $otherDriver = User::query()->where('email', 'pengemudi2@travs.com')->firstOrFail();
        $detail = DetailPemesanan::query()
            ->whereHas('pemesanan.jadwal.pengemudi.pengguna', fn ($query) => $query->where('email', 'pengemudi@travs.com'))
            ->whereHas('pemesanan', fn ($query) => $query->where('status_pemesanan', 'terbayar'))
            ->firstOrFail();

        $jadwal = $detail->pemesanan->jadwal;

        Sanctum::actingAs($otherDriver);

        $this->postJson("/api/pengemudi/trips/{$jadwal->id}/passengers/{$detail->id}/pickup")
            ->assertForbidden()
            ->assertJsonPath('success', false);
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

    /**
     * @return array{0: User, 1: Jadwal, 2: Pemesanan, 3: DetailPemesanan}
     */
    private function createCashPendingBookingForDriver(): array
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()
            ->whereHas('pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->firstOrFail();
        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->whereDoesntHave('detailPemesanan')
            ->firstOrFail();

        $passenger = User::factory()->create([
            'nama' => 'Penumpang Cash',
            'email' => 'cash-driver-test@example.com',
            'no_hp' => '089900000001',
            'peran' => 'penumpang',
        ]);

        $booking = Pemesanan::query()->create([
            'pengguna_id' => $passenger->id,
            'jadwal_id' => $jadwal->id,
            'kode_booking' => 'TRAVS-CASH-'.now()->format('His').'-'.$seat->id,
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => null,
            'batas_pembayaran' => null,
            'lintang_jemput' => -0.077963,
            'bujur_jemput' => 109.400901,
            'alamat_jemput' => 'Jalan Ampera Raya, Pontianak',
            'subtotal_tiket' => 150000,
            'biaya_bagasi' => 0,
            'diskon' => 0,
            'total_harga' => 150000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);

        $detail = DetailPemesanan::query()->create([
            'pemesanan_id' => $booking->id,
            'kursi_jadwal_id' => $seat->id,
            'nomor_kursi' => $seat->nomor_kursi,
            'nama_penumpang' => 'Penumpang Cash',
            'no_hp_penumpang' => '089900000001',
            'status_kehadiran' => 'menunggu',
        ]);

        Pembayaran::query()->create([
            'pemesanan_id' => $booking->id,
            'order_id_gateway' => 'CASH-'.$booking->kode_booking,
            'metode_bayar' => 'cash',
            'jumlah_bayar' => 0,
            'status_bayar' => 'menunggu',
        ]);

        $seat->update(['status_kursi' => 'terkunci', 'dikunci_oleh' => $passenger->id]);

        return [$driver, $jadwal, $booking, $detail];
    }
}
