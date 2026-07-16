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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
                    ],
                    'seat_map',
                    'payment_methods',
                    'bagasi',
                ],
            ]);
    }

    public function test_guest_schedule_list_hides_schedule_one_hour_before_departure(): void
    {
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $jadwal->update([
            'waktu_berangkat' => Carbon::parse('2026-06-19 10:00:00'),
            'estimasi_tiba' => Carbon::parse('2026-06-19 16:00:00'),
            'status' => 'dibuka',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00'));

        try {
            $response = $this->getJson('/api/guest/jadwal');

            $response
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonMissing(['id' => $jadwal->id]);

            $this->assertSame('ditutup', $jadwal->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_schedule_index_marks_schedule_closed_one_hour_before_departure(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $jadwal->update([
            'waktu_berangkat' => Carbon::parse('2026-06-19 10:00:00'),
            'estimasi_tiba' => Carbon::parse('2026-06-19 16:00:00'),
            'status' => 'dibuka',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00'));

        try {
            $response = $this
                ->actingAs($admin)
                ->get(route('admin.jadwal.index'));

            $response
                ->assertOk()
                ->assertSee('Ditutup');

            $this->assertSame('ditutup', $jadwal->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_passenger_cannot_estimate_closed_schedule_one_hour_before_departure(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $jadwal->update([
            'waktu_berangkat' => Carbon::parse('2026-06-19 10:00:00'),
            'estimasi_tiba' => Carbon::parse('2026-06-19 16:00:00'),
            'status' => 'dibuka',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00'));

        try {
            Sanctum::actingAs($user);

            $response = $this->postJson('/api/penumpang/bookings/estimate', [
                'jadwal_id' => $jadwal->id,
                'seat_count' => 1,
                'baggage_items' => [],
            ]);

            $response
                ->assertStatus(422)
                ->assertJsonPath('success', false);

            $this->assertSame('ditutup', $jadwal->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_console_command_closes_schedule_one_hour_before_departure(): void
    {
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $jadwal->update([
            'waktu_berangkat' => Carbon::parse('2026-06-19 10:00:00'),
            'estimasi_tiba' => Carbon::parse('2026-06-19 16:00:00'),
            'status' => 'dibuka',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00'));

        try {
            $this->artisan('travs:close-near-departure-schedules')
                ->assertExitCode(0);

            $this->assertSame('ditutup', $jadwal->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
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

    public function test_passenger_can_release_form_seat_lock(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatNumber] = $this->availableSeatNumbers($jadwal, 1);

        Sanctum::actingAs($user);

        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seatNumber],
        ])->assertOk();

        $response = $this->postJson('/api/penumpang/bookings/release-form-lock', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seatNumber],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.released', 1);

        $this->assertDatabaseHas('kursi_jadwal', [
            'jadwal_id' => $jadwal->id,
            'nomor_kursi' => $seatNumber,
            'status_kursi' => 'tersedia',
            'dikunci_oleh' => null,
        ]);
    }

    public function test_closed_schedule_seat_is_visible_and_cannot_be_locked(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        [$seatNumber] = $this->availableSeatNumbers($jadwal, 1);

        KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('nomor_kursi', $seatNumber)
            ->firstOrFail()
            ->update(['status_kursi' => 'nonaktif']);

        $this->getJson("/api/guest/jadwal/{$jadwal->id}")
            ->assertOk()
            ->assertJsonFragment([
                'nomor_kursi' => $seatNumber,
                'status' => 'nonaktif',
            ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seatNumber],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.seat_numbers.0', "Kursi {$seatNumber} sedang ditutup untuk jadwal ini.");
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
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal Sintang, Kalimantan Barat',
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

        $this->assertSame(0.1285, (float) $booking->lintang_turun);
        $this->assertSame(111.4986, (float) $booking->bujur_turun);
        $this->assertSame('Terminal Sintang, Kalimantan Barat', $booking->alamat_turun);

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

    public function test_baggage_photo_larger_than_three_mb_returns_friendly_message(): void
    {
        Storage::fake('public');

        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/penumpang/bookings/baggage-photo', [
            'foto' => UploadedFile::fake()->image('bagasi-besar.jpg')->size(4096),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame(
            'Ukuran foto maksimal 3 MB. Silakan pilih foto yang lebih kecil.',
            $response->json('errors.foto.0')
        );
    }

    public function test_booking_records_barang_bawaan_without_automatic_fee(): void
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
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
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
            ->assertJsonPath('data.biaya_bagasi', 0);

        $booking = Pemesanan::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('item_bagasi', [
            'pemesanan_id' => $booking->id,
            'metode_hitung' => 'berat',
            'jumlah_item' => 3,
            'biaya_item' => 0,
        ]);
    }

    public function test_self_held_baggage_is_saved_without_baggage_fee(): void
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
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
            ],
            'payment_method' => 'qris',
            'passengers' => [
                [
                    'seat_number' => $seatNumber,
                    'nama_penumpang' => 'Penumpang Bawa Tas',
                    'no_hp_penumpang' => '081200000014',
                ],
            ],
            'baggage_items' => [
                [
                    'deskripsi_item' => 'Tas kecil dipegang sendiri',
                    'metode_hitung' => 'berat',
                    'posisi_barang' => 'dipegang_sendiri',
                    'kategori_ukuran' => 'kecil',
                    'berat_kg' => 20,
                    'jumlah_item' => 3,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.biaya_bagasi', 0);

        $booking = Pemesanan::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('item_bagasi', [
            'pemesanan_id' => $booking->id,
            'posisi_barang' => 'dipegang_sendiri',
            'kategori_ukuran' => 'kecil',
            'biaya_item' => 0,
        ]);
    }

    public function test_legacy_item_fee_setting_does_not_change_booking_total(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'baggage_free_item_count'],
            ['group' => 'baggage', 'value' => '0', 'type' => 'integer', 'description' => 'Item gratis', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'baggage_extra_fee_per_item'],
            ['group' => 'baggage', 'value' => '6000', 'type' => 'float', 'description' => 'Biaya item tambahan', 'updated_at' => now(), 'created_at' => now()]
        );
        Cache::forget('system_settings');

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
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
            ],
            'payment_method' => 'qris',
            'passengers' => [
                [
                    'seat_number' => $seatNumber,
                    'nama_penumpang' => 'Penumpang Item Kecil',
                    'no_hp_penumpang' => '081200000015',
                ],
            ],
            'baggage_items' => [
                [
                    'deskripsi_item' => 'Kardus kecil',
                    'metode_hitung' => 'berat',
                    'posisi_barang' => 'masuk_bagasi',
                    'berat_kg' => 1,
                    'jumlah_item' => 2,
                    'foto_url' => '/storage/baggage/demo.jpg',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.biaya_bagasi', 0);
    }

    public function test_legacy_free_item_limit_does_not_create_barang_fee(): void
    {
        foreach ([
            'baggage_free_item_count' => '1',
            'baggage_extra_fee_per_item' => '5000',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['group' => 'baggage', 'value' => $value, 'type' => 'float', 'description' => 'Pengaturan uji biaya item bagasi', 'updated_at' => now(), 'created_at' => now()]
            );
        }
        Cache::forget('system_settings');

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
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
            ],
            'payment_method' => 'qris',
            'passengers' => [
                [
                    'seat_number' => $seatNumber,
                    'nama_penumpang' => 'Penumpang Bawa Sepeda',
                    'no_hp_penumpang' => '081200000016',
                ],
            ],
            'baggage_items' => [
                [
                    'deskripsi_item' => 'Kardus tambahan',
                    'metode_hitung' => 'berat',
                    'posisi_barang' => 'masuk_bagasi',
                    'jumlah_item' => 3,
                    'foto_url' => '/storage/baggage/kardus.jpg',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.biaya_bagasi', 0);

        $booking = Pemesanan::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('item_bagasi', [
            'pemesanan_id' => $booking->id,
            'jumlah_item' => 3,
            'biaya_item' => 0,
        ]);
    }

    public function test_large_baggage_type_held_by_passenger_is_not_charged(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'baggage_large_vehicle_enabled'],
            ['group' => 'baggage', 'value' => '1', 'type' => 'boolean', 'description' => 'Pengaturan uji bagasi besar', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'baggage_bicycle_fee'],
            ['group' => 'baggage', 'value' => '30000', 'type' => 'float', 'description' => 'Pengaturan uji bagasi besar', 'updated_at' => now(), 'created_at' => now()]
        );
        Cache::forget('system_settings');

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
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
            ],
            'payment_method' => 'qris',
            'passengers' => [
                [
                    'seat_number' => $seatNumber,
                    'nama_penumpang' => 'Penumpang Pegang Barang',
                    'no_hp_penumpang' => '081200000017',
                ],
            ],
            'baggage_items' => [
                [
                    'deskripsi_item' => 'Sepeda lipat kecil',
                    'metode_hitung' => 'berat',
                    'posisi_barang' => 'dipegang_sendiri',
                    'kategori_ukuran' => 'besar',
                    'jenis_barang_besar' => 'sepeda',
                    'jumlah_item' => 1,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.biaya_bagasi', 0);
    }

    public function test_legacy_luggage_capacity_does_not_reject_barang_note(): void
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
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
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
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.biaya_bagasi', 0);
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
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
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
            ->assertJsonStructure(['data' => ['payment' => ['paid_at']]])
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

    public function test_passenger_dashboard_recent_trip_only_contains_paid_upcoming_booking(): void
    {
        $user = User::factory()->create([
            'peran' => 'penumpang',
            'status_aktif' => true,
        ]);
        $jadwal = Jadwal::query()
            ->where('status', 'dibuka')
            ->where('waktu_berangkat', '>=', now())
            ->firstOrFail();

        $paidBooking = Pemesanan::query()->create([
            'pengguna_id' => $user->id,
            'jadwal_id' => $jadwal->id,
            'kode_booking' => 'TRAVS-PAID-DASH',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now()->addMinutes(30),
            'batas_pembayaran' => now()->addMinutes(5),
            'lintang_jemput' => -0.0225,
            'bujur_jemput' => 109.3425,
            'alamat_jemput' => 'Jalan Ahmad Yani, Pontianak',
            'subtotal_tiket' => 230000,
            'biaya_bagasi' => 0,
            'diskon' => 0,
            'total_harga' => 230000,
            'status_pemesanan' => 'terbayar',
        ]);
        $paidBooking->pembayaran()->create([
            'order_id_gateway' => 'PAID-DASH-001',
            'metode_bayar' => 'qris',
            'jumlah_bayar' => 230000,
            'status_bayar' => 'berhasil',
            'dibayar_pada' => now(),
            'expired_at' => now()->addMinutes(5),
        ]);

        $pendingBooking = Pemesanan::query()->create([
            'pengguna_id' => $user->id,
            'jadwal_id' => $jadwal->id,
            'kode_booking' => 'TRAVS-PENDING-DASH',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now()->addMinutes(30),
            'batas_pembayaran' => now()->addMinutes(5),
            'lintang_jemput' => -0.0225,
            'bujur_jemput' => 109.3425,
            'alamat_jemput' => 'Jalan Ahmad Yani, Pontianak',
            'subtotal_tiket' => 230000,
            'biaya_bagasi' => 0,
            'diskon' => 0,
            'total_harga' => 230000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);
        $pendingBooking->pembayaran()->create([
            'order_id_gateway' => 'PENDING-DASH-001',
            'metode_bayar' => 'qris',
            'jumlah_bayar' => 230000,
            'status_bayar' => 'menunggu',
            'expired_at' => now()->addMinutes(5),
        ]);

        $cancelledBooking = Pemesanan::query()->create([
            'pengguna_id' => $user->id,
            'jadwal_id' => $jadwal->id,
            'kode_booking' => 'TRAVS-CANCEL-DASH',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now()->addMinutes(30),
            'batas_pembayaran' => now()->addMinutes(5),
            'lintang_jemput' => -0.0225,
            'bujur_jemput' => 109.3425,
            'alamat_jemput' => 'Jalan Ahmad Yani, Pontianak',
            'subtotal_tiket' => 230000,
            'biaya_bagasi' => 0,
            'diskon' => 0,
            'total_harga' => 230000,
            'status_pemesanan' => 'dibatalkan',
        ]);
        $cancelledBooking->pembayaran()->create([
            'order_id_gateway' => 'CANCEL-DASH-001',
            'metode_bayar' => 'qris',
            'jumlah_bayar' => 230000,
            'status_bayar' => 'kedaluwarsa',
            'expired_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/penumpang/dashboard');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.recent_bookings')
            ->assertJsonPath('data.recent_bookings.0.kode_booking', 'TRAVS-PAID-DASH')
            ->assertJsonPath('data.recent_bookings.0.status', 'terbayar')
            ->assertJsonPath('data.recent_bookings.0.payment_status', 'berhasil');
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
