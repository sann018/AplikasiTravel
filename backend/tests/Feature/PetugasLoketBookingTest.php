<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\KursiJadwal;
use App\Models\Pemesanan;
use App\Models\TiketElektronik;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PetugasLoketBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_counter_staff_can_create_counter_booking_without_passenger_account(): void
    {
        $petugas = User::factory()->create(['peran' => 'petugas_loket']);
        $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seatNumber = $this->availableSeat($schedule);
        Sanctum::actingAs($petugas, ['mobile-counter']);

        $this->postJson('/api/petugas-loket/pemesanan/lock-form', [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => [$seatNumber],
        ])->assertOk();

        $response = $this->postJson('/api/petugas-loket/pemesanan', $this->payload(
            $schedule,
            $seatNumber,
        ));

        $response
            ->assertCreated()
            ->assertJsonPath('data.sumber_pemesanan', 'loket')
            ->assertJsonPath('data.pemesan.nama', 'Calon Penumpang');

        $this->assertDatabaseHas('pemesanan', [
            'id' => $response->json('data.id'),
            'pengguna_id' => null,
            'dibuat_oleh' => $petugas->id,
            'sumber_pemesanan' => 'loket',
            'nama_pemesan' => 'Calon Penumpang',
            'no_hp_pemesan' => '081234567890',
        ]);
    }

    public function test_counter_staff_can_link_counter_booking_to_existing_passenger(): void
    {
        $petugas = User::factory()->create(['peran' => 'petugas_loket']);
        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();
        $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seatNumber = $this->availableSeat($schedule);
        Sanctum::actingAs($petugas, ['mobile-counter']);

        $this->postJson('/api/petugas-loket/pemesanan/lock-form', [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => [$seatNumber],
        ])->assertOk();

        $payload = $this->payload($schedule, $seatNumber);
        $payload['passenger_account_id'] = $passenger->id;
        $response = $this->postJson('/api/petugas-loket/pemesanan', $payload)->assertCreated();

        $this->assertDatabaseHas('pemesanan', [
            'id' => $response->json('data.id'),
            'pengguna_id' => $passenger->id,
            'dibuat_oleh' => $petugas->id,
            'sumber_pemesanan' => 'loket',
        ]);
    }

    public function test_counter_staff_cannot_open_booking_created_by_another_counter_staff(): void
    {
        $creator = User::factory()->create(['peran' => 'petugas_loket']);
        $otherPetugas = User::factory()->create(['peran' => 'petugas_loket']);
        $schedule = Jadwal::query()->firstOrFail();
        $booking = Pemesanan::query()->create([
            'pengguna_id' => null,
            'dibuat_oleh' => $creator->id,
            'sumber_pemesanan' => 'loket',
            'nama_pemesan' => 'Pemesan Loket',
            'no_hp_pemesan' => '081234567890',
            'jadwal_id' => $schedule->id,
            'kode_booking' => 'TRAVS-LOKET-TEST',
            'jumlah_kursi' => 1,
            'subtotal_tiket' => 250000,
            'total_harga' => 250000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);
        Sanctum::actingAs($otherPetugas, ['mobile-counter']);

        $this->getJson("/api/petugas-loket/pemesanan/{$booking->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_counter_staff_token_cannot_use_passenger_booking_route(): void
    {
        $petugas = User::factory()->create(['peran' => 'petugas_loket']);
        Sanctum::actingAs($petugas, ['mobile-counter']);

        $this->getJson('/api/penumpang/bookings')->assertForbidden();
    }

    public function test_counter_staff_passenger_search_requires_an_exact_email_or_phone(): void
    {
        $petugas = User::factory()->create(['peran' => 'petugas_loket']);
        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();
        Sanctum::actingAs($petugas, ['mobile-counter']);

        $this->getJson('/api/petugas-loket/penumpang/search?query='.urlencode($passenger->email))
            ->assertOk()
            ->assertJsonPath('data.id', $passenger->id)
            ->assertJsonMissingPath('data.password');

        $this->getJson('/api/petugas-loket/penumpang/search?query='.urlencode(substr($passenger->email, 0, 4)))
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_counter_cash_confirmation_is_idempotent(): void
    {
        $petugas = User::factory()->create(['peran' => 'petugas_loket']);
        $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seatNumber = $this->availableSeat($schedule);
        Sanctum::actingAs($petugas, ['mobile-counter']);

        $this->postJson('/api/petugas-loket/pemesanan/lock-form', [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => [$seatNumber],
        ])->assertOk();

        $bookingId = $this->postJson(
            '/api/petugas-loket/pemesanan',
            $this->payload($schedule, $seatNumber),
        )->assertCreated()->json('data.id');

        $this->putJson("/api/petugas-loket/pemesanan/{$bookingId}/payment", [
            'payment_method' => 'cash',
        ])->assertOk()->assertJsonPath('data.payment.status', 'menunggu');

        $this->postJson("/api/petugas-loket/pemesanan/{$bookingId}/confirm-cash")
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'berhasil');
        $ticketCount = TiketElektronik::query()
            ->whereHas('detailPemesanan', fn ($query) => $query->where('pemesanan_id', $bookingId))
            ->count();

        $this->postJson("/api/petugas-loket/pemesanan/{$bookingId}/confirm-cash")
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'berhasil');
        $this->assertSame($ticketCount, TiketElektronik::query()
            ->whereHas('detailPemesanan', fn ($query) => $query->where('pemesanan_id', $bookingId))
            ->count());
    }

    private function availableSeat(Jadwal $schedule): int
    {
        return (int) KursiJadwal::query()
            ->where('jadwal_id', $schedule->id)
            ->where('status_kursi', 'tersedia')
            ->orderBy('nomor_kursi')
            ->value('nomor_kursi');
    }

    /** @return array<string, mixed> */
    private function payload(Jadwal $schedule, int $seatNumber): array
    {
        return [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => [$seatNumber],
            'customer' => [
                'name' => 'Calon Penumpang',
                'phone' => '081234567890',
                'email' => 'calon@example.com',
            ],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Kantor Rusly Travel Pontianak',
                'note' => 'Tunggu di ruang loket',
            ],
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
                'note' => 'Turun di pintu utama',
            ],
            'passengers' => [[
                'seat_number' => $seatNumber,
                'nama_penumpang' => 'Calon Penumpang',
                'no_hp_penumpang' => '081234567890',
            ]],
            'baggage_items' => [],
        ];
    }
}
