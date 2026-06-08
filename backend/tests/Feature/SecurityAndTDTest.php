<?php

namespace Tests\Feature;

use App\Models\DetailPemesanan;
use App\Models\Jadwal;
use App\Models\KursiJadwal;
use App\Models\Notifikasi;
use App\Models\Pemesanan;
use App\Models\TiketElektronik;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Test TDD untuk keamanan, akses role, double submit, token palsu,
 * dan notifikasi event jadwal berubah.
 */
class SecurityAndTDTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    // =========================================================================
    // Test: Forbidden access per role
    // =========================================================================

    public function test_penumpang_cannot_access_driver_endpoints(): void
    {
        $penumpang = User::query()->where('peran', 'penumpang')->firstOrFail();

        Sanctum::actingAs($penumpang);

        $this->getJson('/api/pengemudi/dashboard')->assertStatus(403);
        $this->getJson('/api/pengemudi/trips')->assertStatus(403);
        $this->postJson('/api/pengemudi/tickets/scan', [
            'token_qr' => 'fake-token',
            'jadwal_id' => 1,
        ])->assertStatus(403);
    }

    public function test_pengemudi_cannot_access_penumpang_booking_endpoints(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();

        Sanctum::actingAs($driver);

        $this->getJson('/api/penumpang/bookings')->assertStatus(403);
        $this->postJson('/api/penumpang/bookings/estimate', [
            'jadwal_id' => 1,
            'seat_count' => 1,
        ])->assertStatus(403);
    }

    public function test_guest_cannot_access_protected_endpoints(): void
    {
        $endpoints = [
            'GET' => [
                '/api/penumpang/dashboard',
                '/api/penumpang/bookings',
                '/api/pengemudi/dashboard',
                '/api/pengemudi/trips',
            ],
            'POST' => [
                '/api/penumpang/bookings',
                '/api/penumpang/bookings/lock-form',
                '/api/pengemudi/tickets/scan',
            ],
        ];

        foreach ($endpoints['GET'] as $endpoint) {
            $this->getJson($endpoint)->assertStatus(401);
        }

        foreach ($endpoints['POST'] as $endpoint) {
            $this->postJson($endpoint, [])->assertStatus(401);
        }
    }

    // =========================================================================
    // Test: Double submit prevention
    // =========================================================================

    public function test_double_submit_booking_does_not_create_duplicate(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatNumber] = $this->availableSeatNumbers($jadwal, 1);

        Sanctum::actingAs($user);

        $payload = [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seatNumber],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Jalan Ahmad Yani, Pontianak',
            ],
            'payment_method' => 'qris',
            'passengers' => [
                [
                    'seat_number' => $seatNumber,
                    'nama_penumpang' => 'Double Submit Test',
                    'no_hp_penumpang' => '081200009999',
                ],
            ],
        ];

        $firstResponse = $this->postJson('/api/penumpang/bookings', $payload);
        $firstResponse->assertCreated();

        $secondResponse = $this->postJson('/api/penumpang/bookings', $payload);
        $secondResponse->assertUnprocessable();

        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('nomor_kursi', $seatNumber)
            ->firstOrFail();

        $detailCount = DetailPemesanan::query()
            ->where('kursi_jadwal_id', $seat->id)
            ->count();

        $this->assertSame(1, $detailCount, 'Double submit should not create duplicate detail_pemesanan for same seat.');
    }

    // =========================================================================
    // Test: Fake / unknown QR token rejection
    // =========================================================================

    public function test_driver_cannot_scan_fake_qr_token(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()
            ->whereHas('pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->firstOrFail();

        Sanctum::actingAs($driver);

        $response = $this->postJson('/api/pengemudi/tickets/scan', [
            'token_qr' => 'FAKE-TOKEN-DOES-NOT-EXIST-12345',
            'jadwal_id' => $jadwal->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_driver_cannot_scan_empty_token(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()
            ->whereHas('pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->firstOrFail();

        Sanctum::actingAs($driver);

        $response = $this->postJson('/api/pengemudi/tickets/scan', [
            'token_qr' => '',
            'jadwal_id' => $jadwal->id,
        ]);

        $response->assertUnprocessable();
    }

    // =========================================================================
    // Test: Admin schedule change triggers notification to passengers
    // =========================================================================

    public function test_admin_schedule_time_change_sends_notification_to_passengers(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();

        $booking = Pemesanan::query()->create([
            'pengguna_id' => User::query()->where('peran', 'penumpang')->firstOrFail()->id,
            'jadwal_id' => $jadwal->id,
            'kode_booking' => 'TRAVS-NOTIF-TEST',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now()->addMinutes(30),
            'batas_pembayaran' => now()->addMinutes(5),
            'alamat_jemput' => 'Test notifikasi',
            'subtotal_tiket' => 225000,
            'biaya_bagasi' => 0,
            'diskon' => 0,
            'total_harga' => 230000,
            'status_pemesanan' => 'terbayar',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.jadwal.update', $jadwal), [
                'kendaraan_id' => $jadwal->kendaraan_id,
                'rute_id' => $jadwal->rute_id,
                'pengemudi_id' => $jadwal->pengemudi_id,
                'waktu_berangkat' => now()->addDays(10)->format('Y-m-d H:i'),
                'estimasi_tiba' => now()->addDays(10)->addHours(7)->format('Y-m-d H:i'),
                'status' => $jadwal->status,
            ]);

        $this->assertDatabaseHas('notifikasi', [
            'pengguna_id' => $booking->pengguna_id,
            'jenis' => 'perjalanan',
            'judul' => 'Jadwal perjalanan diperbarui',
        ]);
    }

    // =========================================================================
    // Test: Booking ownership verification
    // =========================================================================

    public function test_penumpang_cannot_view_other_users_booking(): void
    {
        $userA = User::query()->where('email', 'penumpang@travs.com')->firstOrFail();
        $userB = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();

        $booking = Pemesanan::query()->create([
            'pengguna_id' => $userA->id,
            'jadwal_id' => $jadwal->id,
            'kode_booking' => 'TRAVS-OWNERSHIP-TEST',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now()->addMinutes(30),
            'batas_pembayaran' => now()->addMinutes(5),
            'alamat_jemput' => 'Ownership test',
            'subtotal_tiket' => 225000,
            'biaya_bagasi' => 0,
            'diskon' => 0,
            'total_harga' => 230000,
            'status_pemesanan' => 'terbayar',
        ]);

        Sanctum::actingAs($userB);

        $this->getJson("/api/penumpang/bookings/{$booking->id}")
            ->assertStatus(404);
    }

    // =========================================================================
    // Test: Validation error completeness for booking payload
    // =========================================================================

    public function test_booking_with_empty_payload_returns_validation_errors(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/penumpang/bookings', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'jadwal_id',
                'seat_numbers',
                'pickup',
                'payment_method',
                'passengers',
            ]);
    }

    public function test_booking_with_invalid_payment_method_rejected(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatNumber] = $this->availableSeatNumbers($jadwal, 1);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/penumpang/bookings', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seatNumber],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Test',
            ],
            'payment_method' => 'bitcoin',
            'passengers' => [
                [
                    'seat_number' => $seatNumber,
                    'nama_penumpang' => 'Test',
                    'no_hp_penumpang' => '081200000001',
                ],
            ],
        ]);

        $response->assertUnprocessable();
    }

    // =========================================================================
    // Test: Seat cannot be booked twice (race condition guard)
    // =========================================================================

    public function test_same_seat_cannot_be_locked_by_two_users(): void
    {
        $userA = User::query()->where('email', 'penumpang@travs.com')->firstOrFail();
        $userB = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatNumber] = $this->availableSeatNumbers($jadwal, 1);

        Sanctum::actingAs($userA);
        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seatNumber],
        ])->assertOk();

        Sanctum::actingAs($userB);
        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seatNumber],
        ])->assertUnprocessable();
    }

    // =========================================================================
    // Helper
    // =========================================================================

    /**
     * @return array<int, int>
     */
    private function availableSeatNumbers(Jadwal $jadwal, int $count): array
    {
        return KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('status_kursi', 'tersedia')
            ->orderBy('nomor_kursi')
            ->limit($count)
            ->pluck('nomor_kursi')
            ->map(fn ($seatNumber) => (int) $seatNumber)
            ->values()
            ->all();
    }
}
