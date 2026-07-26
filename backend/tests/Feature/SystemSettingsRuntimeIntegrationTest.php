<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\KursiJadwal;
use App\Models\Pemesanan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BookingService;
use App\Services\NotificationTemplateService;
use App\Services\SettingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemSettingsRuntimeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_mobile_settings_endpoint_only_returns_safe_configuration(): void
    {
        $response = $this->getJson('/api/settings/mobile');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.max_seat_per_booking', 4)
            ->assertJsonPath('data.payment_cash_enabled', true)
            ->assertJsonStructure(['success', 'message', 'data' => [
                'app_name',
                'app_logo_url',
                'service_whatsapp',
                'seat_lock_form_minutes',
                'seat_lock_payment_minutes',
                'baggage_photo_required',
            ]])
            ->assertJsonMissingPath('data.admin_contact')
            ->assertJsonMissingPath('data.bank_account_number')
            ->assertJsonMissingPath('data.api_key');

        $this->assertSame('/travs-logo.svg', $response->json('data.app_logo_url'));
    }

    public function test_maximum_seats_and_form_lock_follow_system_settings(): void
    {
        $this->setSettings([
            'max_seat_per_booking' => 1,
            'seat_lock_form_minutes' => 12,
        ]);

        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seats = $this->availableSeats($schedule, 2);
        Sanctum::actingAs($user);

        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => $seats,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.seat_numbers.0', 'Jumlah kursi melebihi batas maksimal dalam satu pemesanan.');

        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => [$seats[0]],
        ])->assertOk()
            ->assertJsonPath('data.lock_minutes', 12);
    }

    public function test_total_contains_ticket_only_when_barang_bawaan_is_recorded(): void
    {
        $this->setSettings([
            'baggage_photo_required' => false,
            'baggage_weight_enabled' => true,
            'baggage_size_enabled' => false,
            'baggage_item_enabled' => false,
            'baggage_free_weight_kg' => 10,
            'baggage_max_weight_per_person_kg' => 20,
            'baggage_extra_fee_per_kg' => 3000,
        ]);

        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $schedule = Jadwal::query()->where('status', 'dibuka')->with('rute')->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/penumpang/bookings/estimate', [
            'jadwal_id' => $schedule->id,
            'seat_count' => 1,
            'baggage_items' => [[
                'deskripsi_item' => 'Koper uji',
                'metode_hitung' => 'berat',
                'berat_kg' => 12,
                'jumlah_item' => 1,
            ]],
        ])->assertOk()
            ->assertJsonMissingPath('data.biaya_admin')
            ->assertJsonMissingPath('data.biaya_bagasi')
            ->assertJsonPath(
                'data.total_harga',
                (int) $schedule->rute->harga_tiket,
            );

        $this->assertDatabaseMissing('pengaturan_sistem', ['kunci' => 'admin_fee']);
    }

    public function test_cash_booking_is_rejected_when_cash_setting_is_disabled(): void
    {
        $this->setSettings(['payment_cash_enabled' => false]);

        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seat = $this->availableSeats($schedule, 1)[0];
        Sanctum::actingAs($user);
        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => [$seat],
        ])->assertOk();

        $bookingId = $this->postJson('/api/penumpang/bookings', $this->bookingPayload($schedule, $seat, 'cash'))
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/api/penumpang/bookings/{$bookingId}/payment", ['payment_method' => 'cash'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.payment_method.0', 'Pembayaran tunai sedang tidak tersedia. Silakan pilih metode lain.');
    }

    public function test_cash_booking_removes_deadline_after_method_is_selected(): void
    {
        $this->setSettings([
            'payment_cash_enabled' => true,
            'seat_lock_payment_minutes' => 7,
        ]);

        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seat = $this->availableSeats($schedule, 1)[0];
        Sanctum::actingAs($user);
        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => [$seat],
        ])->assertOk();

        $bookingResponse = $this->postJson(
            '/api/penumpang/bookings',
            $this->bookingPayload($schedule, $seat, 'cash'),
        );

        $bookingResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'menunggu_pembayaran')
            ->assertJsonPath('data.payment.method', null)
            ->assertJsonPath('data.batas_pembayaran', fn ($value) => is_string($value) && $value !== '');

        $this->putJson(
            '/api/penumpang/bookings/'.$bookingResponse->json('data.id').'/payment',
            ['payment_method' => 'cash'],
        )
            ->assertOk()
            ->assertJsonPath('data.payment.method', 'cash')
            ->assertJsonPath('data.payment.status_pembayaran', 'menunggu_konfirmasi_tunai')
            // Setelah tunai dipilih, penumpang membayar saat dijemput tanpa countdown.
            ->assertJsonPath('data.batas_pembayaran', null)
            ->assertJsonPath('data.payment.expired_at', null);
    }

    public function test_expired_booking_is_not_cancelled_when_auto_cancel_is_disabled(): void
    {
        $this->setSettings(['auto_cancel_booking' => false]);

        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $booking = Pemesanan::query()->create([
            'pengguna_id' => $user->id,
            'jadwal_id' => $schedule->id,
            'kode_booking' => 'RSLY-AUTO-CANCEL-OFF',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now()->subMinutes(10),
            'batas_pembayaran' => now()->subMinutes(5),
            'alamat_jemput' => 'Pontianak',
            'subtotal_tiket' => 100000,
            'total_harga' => 100000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);
        $booking->pembayaran()->create([
            'order_id_gateway' => 'AUTO-CANCEL-OFF',
            'metode_bayar' => 'qris',
            'jumlah_bayar' => 100000,
            'status_bayar' => 'menunggu',
            'expired_at' => now()->subMinutes(5),
        ]);

        app(BookingService::class)->releaseExpiredResources($schedule->id);

        $this->assertSame('menunggu_pembayaran', $booking->fresh()->status_pemesanan);
    }

    public function test_notification_template_replaces_supported_placeholders(): void
    {
        $message = app(NotificationTemplateService::class)->render(
            'Halo {nama}, booking {kode_booking} untuk {rute} berangkat {tanggal} pukul {jam}.',
            [
                'nama' => 'Budi',
                'kode_booking' => 'RSLY-001',
                'rute' => 'Pontianak - Sambas',
                'tanggal' => '21 Juni 2026',
                'jam' => '08.00',
            ],
        );

        $this->assertSame(
            'Halo Budi, booking RSLY-001 untuk Pontianak - Sambas berangkat 21 Juni 2026 pukul 08.00.',
            $message,
        );
    }

    public function test_notification_toggle_reads_current_system_setting(): void
    {
        $this->setSettings(['notif_departure_reminder' => false]);

        $this->assertFalse(
            app(NotificationTemplateService::class)->enabled('notif_departure_reminder'),
        );
        $this->assertTrue(
            app(NotificationTemplateService::class)->enabled('setting_not_available', true),
        );
    }

    public function test_payment_success_notification_remains_active_with_standard_template(): void
    {
        $this->setSettings([
            'notif_payment_success' => false,
            'notif_payment_success_template' => 'Halo {nama}, pembayaran {kode_booking} untuk {rute} sudah masuk.',
            'baggage_photo_required' => false,
        ]);

        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seat = $this->availableSeats($schedule, 1)[0];
        Sanctum::actingAs($user);
        config()->set('services.pakasir.enabled', true);
        config()->set('services.pakasir.base_url', 'https://app.pakasir.test/api');
        config()->set('services.pakasir.project', 'travs-test');
        config()->set('services.pakasir.api_key', 'secret-test-key');
        Http::fake(fn ($request) => Http::response([
            'payment' => [
                'project' => 'travs-test',
                'order_id' => $request['order_id'],
                'payment_method' => 'qris',
                'payment_number' => 'QRIS-NOTIFICATION-TEST',
                'amount' => $request['amount'],
                'fee' => 0,
                'total_payment' => $request['amount'],
                'expired_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ]));

        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => [$seat],
        ])->assertOk();

        $bookingId = $this->postJson(
            '/api/penumpang/bookings',
            $this->bookingPayload($schedule, $seat, 'qris'),
        )->assertCreated()->json('data.id');

        $this->putJson("/api/penumpang/bookings/{$bookingId}/payment", [
            'payment_method' => 'qris',
        ])->assertOk();

        $this->postJson("/api/penumpang/bookings/{$bookingId}/simulate-payment")
            ->assertOk();

        $this->assertDatabaseHas('notifikasi', [
            'pengguna_id' => $user->id,
            'jenis' => 'pembayaran',
        ]);
        $this->assertStringContainsString(
            'Pembayaran Anda untuk rute',
            (string) $user->notifikasi()
                ->where('jenis', 'pembayaran')
                ->where('referensi_id', $bookingId)
                ->where('judul', 'Pembayaran berhasil dikonfirmasi')
                ->value('pesan'),
        );
    }

    public function test_legacy_baggage_settings_do_not_add_fee_to_new_booking(): void
    {
        $this->setSettings([
            'baggage_photo_required' => false,
            'baggage_weight_enabled' => true,
            'baggage_item_enabled' => true,
            'baggage_free_weight_kg' => 7,
            'baggage_max_weight_per_person_kg' => 30,
            'baggage_extra_fee_per_kg' => 1000,
            'baggage_free_item_count' => 2,
            'baggage_max_item_per_person' => 10,
            'baggage_extra_fee_per_item' => 2000,
        ]);

        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $schedule = Jadwal::query()->where('status', 'dibuka')->with('rute')->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/penumpang/bookings/estimate', [
            'jadwal_id' => $schedule->id,
            'seat_count' => 2,
            'passenger_count' => 1,
            'baggage_items' => [[
                'deskripsi_item' => 'Koper dan kardus',
                'posisi_barang' => 'masuk_bagasi',
                'berat_kg' => 16,
                'jumlah_item' => 5,
            ]],
        ])->assertOk()
            ->assertJsonMissingPath('data.biaya_bagasi')
            ->assertJsonPath(
                'data.total_harga',
                (int) $schedule->rute->harga_tiket * 2,
            );
    }

    private function setSettings(array $values): void
    {
        foreach ($values as $key => $value) {
            $setting = SystemSetting::query()->where('kunci', $key)->firstOrFail();
            $setting->update(['nilai' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value]);
        }

        app(SettingService::class)->clearCache();
    }

    /** @return array<int, int> */
    private function availableSeats(Jadwal $schedule, int $count): array
    {
        return KursiJadwal::query()
            ->where('jadwal_id', $schedule->id)
            ->where('status_kursi', 'tersedia')
            ->orderBy('nomor_kursi')
            ->limit($count)
            ->pluck('nomor_kursi')
            ->map(fn ($seat) => (int) $seat)
            ->all();
    }

    private function bookingPayload(Jadwal $schedule, int $seat, string $paymentMethod): array
    {
        return [
            'jadwal_id' => $schedule->id,
            'seat_numbers' => [$seat],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Pontianak',
                'note' => 'Depan gerbang utama',
            ],
            'dropoff' => [
                'latitude' => 1.365,
                'longitude' => 109.31,
                'address' => 'Sambas',
                'note' => 'Pintu masuk terminal',
            ],
            'payment_method' => $paymentMethod,
            'passengers' => [[
                'seat_number' => $seat,
                'nama_penumpang' => 'Penumpang Uji',
                'no_hp_penumpang' => '081234567890',
            ]],
            'baggage_items' => [],
        ];
    }
}
