<?php

namespace Tests\Feature;

use App\Models\DetailPemesanan;
use App\Models\Jadwal;
use App\Models\KursiJadwal;
use App\Models\Pemesanan;
use App\Models\TiketElektronik;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_can_read_schedule_detail_with_booking_rules(): void
    {
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();

        $response = $this->getJson("/api/guest/jadwal/{$jadwal->id}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $jadwal->id)
            ->assertJsonStructure([
                'data' => [
                    'booking_rules' => [
                        'max_seat_per_booking',
                        'seat_lock_form_minutes',
                        'seat_lock_payment_minutes',
                        'admin_fee',
                    ],
                    'seat_map',
                    'payment_methods',
                    'bagasi',
                ],
            ]);
    }

    public function test_passenger_can_lock_seats_for_checkout_form(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatA, $seatB] = $this->availableSeatNumbers($jadwal, 2);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seatA, $seatB],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.jadwal_id', $jadwal->id)
            ->assertJsonPath('data.seat_numbers.0', $seatA)
            ->assertJsonPath('data.seat_numbers.1', $seatB);

        $this->assertDatabaseHas('kursi_jadwal', [
            'jadwal_id' => $jadwal->id,
            'nomor_kursi' => $seatA,
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $user->id,
        ]);
    }

    public function test_booking_action_is_rate_limited_to_reduce_spam_clicks(): void
    {
        $user = User::factory()->create([
            'email' => 'rate-limit-passenger@example.com',
            'peran' => 'penumpang',
        ]);
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $payload = [
            'jadwal_id' => $jadwal->id,
            'seat_count' => 1,
            'baggage_items' => [],
        ];

        Sanctum::actingAs($user);

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
                ->postJson('/api/penumpang/bookings/estimate', $payload)
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson('/api/penumpang/bookings/estimate', $payload)
            ->assertStatus(429);
    }

    public function test_passenger_can_create_booking_draft_and_lock_selected_seats(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatA, $seatB] = $this->availableSeatNumbers($jadwal, 2);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/penumpang/bookings', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seatA, $seatB],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Jalan Ahmad Yani, Pontianak',
            ],
            'payment_method' => 'qris',
            'passengers' => [
                [
                    'seat_number' => $seatA,
                    'nama_penumpang' => 'Penumpang Test 1',
                    'no_hp_penumpang' => '081200000001',
                ],
                [
                    'seat_number' => $seatB,
                    'nama_penumpang' => 'Penumpang Test 2',
                    'no_hp_penumpang' => '081200000002',
                ],
            ],
            'baggage_items' => [
                [
                    'deskripsi_item' => 'Tas Kabin',
                    'metode_hitung' => 'berat',
                    'berat_kg' => 8,
                    'ukuran_cm' => 80,
                    'jumlah_item' => 1,
                    'foto_url' => 'https://example.com/tas-kabin.jpg',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'menunggu_pembayaran')
            ->assertJsonPath('data.jumlah_kursi', 2);

        $booking = Pemesanan::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('detail_pemesanan', [
            'pemesanan_id' => $booking->id,
            'nomor_kursi' => $seatA,
        ]);

        $this->assertDatabaseHas('kursi_jadwal', [
            'jadwal_id' => $jadwal->id,
            'nomor_kursi' => $seatA,
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $user->id,
        ]);

        $this->assertDatabaseHas('kursi_jadwal', [
            'jadwal_id' => $jadwal->id,
            'nomor_kursi' => $seatB,
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $user->id,
        ]);
    }

    public function test_passenger_can_upload_baggage_photo(): void
    {
        Storage::fake('public');

        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/penumpang/bookings/baggage-photo', [
            'foto' => UploadedFile::fake()->image('bagasi.jpg', 800, 600),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['path', 'url', 'original_name'],
            ]);

        Storage::disk('public')->assertExists($response->json('data.path'));
    }

    public function test_booking_charges_item_fee_above_free_baggage_limit(): void
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
                'address' => 'Jalan Ahmad Yani, Pontianak',
            ],
            'payment_method' => 'qris',
            'passengers' => [
                [
                    'seat_number' => $seatNumber,
                    'nama_penumpang' => 'Penumpang Bagasi',
                    'no_hp_penumpang' => '081200000004',
                ],
            ],
            'baggage_items' => [
                [
                    'deskripsi_item' => 'Koper dan tas',
                    'metode_hitung' => 'berat',
                    'berat_kg' => 8,
                    'jumlah_item' => 3,
                    'foto_url' => '/storage/baggage/demo.jpg',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.biaya_bagasi', 3000);

        $booking = Pemesanan::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('item_bagasi', [
            'pemesanan_id' => $booking->id,
            'metode_hitung' => 'berat',
            'jumlah_item' => 3,
        ]);
    }

    public function test_luggage_capacity_counts_existing_bookings(): void
    {
        $userA = User::query()->where('email', 'penumpang@travs.com')->firstOrFail();
        $userB = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatNumber] = $this->availableSeatNumbers($jadwal, 1);

        $existingBooking = Pemesanan::query()->create([
            'pengguna_id' => $userA->id,
            'jadwal_id' => $jadwal->id,
            'kode_booking' => 'TRAVS-BAGASI-EXISTING',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now()->addMinutes(30),
            'batas_pembayaran' => now()->addMinutes(5),
            'alamat_jemput' => 'Pickup existing',
            'subtotal_tiket' => 225000,
            'biaya_bagasi' => 350000,
            'biaya_admin' => 5000,
            'diskon' => 0,
            'total_harga' => 580000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);

        $existingBooking->itemBagasi()->create([
            'deskripsi_item' => 'Koper besar existing',
            'metode_hitung' => 'berat',
            'berat_kg' => 45,
            'jumlah_item' => 1,
            'foto_url' => '/storage/baggage/existing.jpg',
            'biaya_item' => 0,
        ]);

        Sanctum::actingAs($userB);

        $response = $this->postJson('/api/penumpang/bookings', [
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
                    'nama_penumpang' => 'Penumpang Kapasitas',
                    'no_hp_penumpang' => '081200000005',
                ],
            ],
            'baggage_items' => [
                [
                    'deskripsi_item' => 'Koper tambahan',
                    'metode_hitung' => 'berat',
                    'berat_kg' => 8,
                    'jumlah_item' => 1,
                    'foto_url' => '/storage/baggage/new.jpg',
                ],
            ],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('baggage_items');
    }

    public function test_expired_pending_booking_is_released_before_new_booking_is_created(): void
    {
        $userA = User::query()->where('email', 'penumpang@travs.com')->firstOrFail();
        $userB = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatNumber] = $this->availableSeatNumbers($jadwal, 1);

        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('nomor_kursi', $seatNumber)
            ->firstOrFail();

        $expiredBooking = Pemesanan::query()->create([
            'pengguna_id' => $userA->id,
            'jadwal_id' => $jadwal->id,
            'kode_booking' => 'TRAVS-EXPIRED-001',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now()->subMinutes(20),
            'batas_pembayaran' => now()->subMinutes(10),
            'alamat_jemput' => 'Pickup lama',
            'subtotal_tiket' => 225000,
            'biaya_bagasi' => 0,
            'biaya_admin' => 5000,
            'diskon' => 0,
            'total_harga' => 230000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);

        DetailPemesanan::query()->create([
            'pemesanan_id' => $expiredBooking->id,
            'kursi_jadwal_id' => $seat->id,
            'nomor_kursi' => $seatNumber,
            'nama_penumpang' => 'Booking Kedaluwarsa',
            'no_hp_penumpang' => '081200001111',
            'status_kehadiran' => 'menunggu',
        ]);

        $expiredBooking->pembayaran()->create([
            'order_id_gateway' => 'DRAFT-EXPIRED-001',
            'metode_bayar' => 'qris',
            'jumlah_bayar' => 230000,
            'status_bayar' => 'menunggu',
            'expired_at' => now()->subMinutes(10),
        ]);

        $seat->update([
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $userA->id,
            'locked_at' => now()->subMinutes(20),
            'lock_expires_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        Sanctum::actingAs($userB);

        $response = $this->postJson('/api/penumpang/bookings', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seatNumber],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Jalan Baru, Pontianak',
            ],
            'payment_method' => 'qris',
            'passengers' => [
                [
                    'seat_number' => $seatNumber,
                    'nama_penumpang' => 'Penumpang Pengganti',
                    'no_hp_penumpang' => '081200009999',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'menunggu_pembayaran');

        $expiredBooking->refresh();
        $seat->refresh();

        $this->assertSame('dibatalkan', $expiredBooking->status_pemesanan);
        $this->assertDatabaseHas('pembayaran', [
            'pemesanan_id' => $expiredBooking->id,
            'status_bayar' => 'kedaluwarsa',
        ]);
        $this->assertSame('terkunci', $seat->status_kursi);
        $this->assertSame($userB->id, $seat->dikunci_oleh);
    }

    public function test_passenger_can_simulate_payment_and_receive_tickets(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatNumber] = $this->availableSeatNumbers($jadwal, 1);

        $booking = Pemesanan::query()->create([
            'pengguna_id' => $user->id,
            'jadwal_id' => $jadwal->id,
            'kode_booking' => 'TRAVS-TEST-001',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now(),
            'batas_pembayaran' => now()->addMinutes(5),
            'alamat_jemput' => 'Jalan Gajah Mada, Pontianak',
            'subtotal_tiket' => 225000,
            'biaya_bagasi' => 0,
            'biaya_admin' => 5000,
            'diskon' => 0,
            'total_harga' => 230000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);

        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('nomor_kursi', $seatNumber)
            ->firstOrFail();

        $seat->update([
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $user->id,
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes(5),
            'updated_at' => now(),
        ]);

        $detail = DetailPemesanan::query()->create([
            'pemesanan_id' => $booking->id,
            'kursi_jadwal_id' => $seat->id,
            'nomor_kursi' => $seatNumber,
            'nama_penumpang' => 'Penumpang Simulasi',
            'no_hp_penumpang' => '081200000003',
            'status_kehadiran' => 'menunggu',
        ]);

        $booking->pembayaran()->create([
            'order_id_gateway' => 'DRAFT-SIM-001',
            'metode_bayar' => 'qris',
            'jumlah_bayar' => 230000,
            'status_bayar' => 'menunggu',
            'expired_at' => now()->addMinutes(5),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/penumpang/bookings/{$booking->id}/simulate-payment");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'terbayar')
            ->assertJsonPath('data.payment.status', 'berhasil')
            ->assertJsonCount(1, 'data.passengers');

        $seat->refresh();
        $this->assertSame('dipesan', $seat->status_kursi);

        $this->assertDatabaseHas('tiket_elektronik', [
            'detail_pemesanan_id' => $detail->id,
        ]);

        $ticket = TiketElektronik::query()
            ->where('detail_pemesanan_id', $detail->id)
            ->firstOrFail();

        $this->assertFalse($ticket->sudah_dipindai);
    }

    public function test_cleanup_command_releases_expired_form_lock(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatNumber] = $this->availableSeatNumbers($jadwal, 1);

        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('nomor_kursi', $seatNumber)
            ->firstOrFail();

        $seat->update([
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $user->id,
            'locked_at' => now()->subMinutes(35),
            'lock_expires_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $this->artisan('travs:release-expired-bookings')->assertSuccessful();

        $seat->refresh();

        $this->assertSame('tersedia', $seat->status_kursi);
        $this->assertNull($seat->dikunci_oleh);
        $this->assertNull($seat->lock_expires_at);
    }

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
