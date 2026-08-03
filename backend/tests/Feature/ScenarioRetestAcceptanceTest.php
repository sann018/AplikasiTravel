<?php

namespace Tests\Feature;

use App\Models\DetailPemesanan;
use App\Models\Jadwal;
use App\Models\KursiJadwal;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Bukti penerimaan tambahan untuk skenario yang pada audit pertama belum
 * mempunyai pengujian end-to-end yang cukup spesifik. File ini tidak mengubah
 * logika produksi dan seluruh state memakai SQLite :memory:.
 */
class ScenarioRetestAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_tp_12_each_passenger_receives_a_distinct_electronic_ticket_and_qr_token(): void
    {
        $booking = Pemesanan::query()
            ->where('kode_booking', 'TRAVS-DEMO-001')
            ->with('detailPemesanan.tiketElektronik')
            ->firstOrFail();

        Sanctum::actingAs($booking->pengguna);

        $response = $this->getJson("/api/penumpang/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'terbayar')
            ->assertJsonCount(2, 'data.passengers');

        $tickets = collect($response->json('data.passengers'))
            ->pluck('ticket')
            ->filter()
            ->values();

        $this->assertCount(2, $tickets);
        $this->assertSame(2, $tickets->pluck('kode_qr')->unique()->count());
        $this->assertSame(2, $tickets->pluck('token')->unique()->count());
        $this->assertTrue($tickets->every(fn ($ticket) => filled($ticket['kode_qr']) && filled($ticket['token'])));
    }

    public function test_pg_01_driver_logs_in_with_credentials_and_only_receives_owned_trips(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $driverProfileId = $driver->pengemudiProfile()->firstOrFail()->id;

        $login = $this->postJson('/api/auth/login', [
            'login' => 'pengemudi@travs.com',
            'password' => 'driver12345',
            'device_name' => 'scenario-retest',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.peran', 'pengemudi');

        $token = (string) $login->json('data.token');
        $this->assertNotSame('', $token);

        $response = $this->withToken($token)
            ->getJson('/api/pengemudi/trips')
            ->assertOk()
            ->assertJsonPath('success', true);

        $returnedIds = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $ownedIds = Jadwal::query()
            ->where('pengemudi_id', $driverProfileId)
            ->where('status', '!=', 'dibatalkan')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        $this->assertNotEmpty($returnedIds);
        $this->assertSame($ownedIds->all(), $returnedIds->all());
        $this->assertFalse(
            Jadwal::query()->whereIn('id', $returnedIds)->where('pengemudi_id', '!=', $driverProfileId)->exists(),
        );
    }

    public function test_pg_05_driver_status_transitions_are_ordered_until_trip_is_finished(): void
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $schedule = Jadwal::query()
            ->whereHas('pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->firstOrFail();
        $schedule->update([
            'status' => 'dibuka',
            'waktu_berangkat' => now()->addDay(),
            'estimasi_tiba' => now()->addDay()->addHours(6),
        ]);

        Sanctum::actingAs($driver);

        $this->postJson("/api/pengemudi/trips/{$schedule->id}/status", ['status' => 'selesai'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Status perjalanan tidak dapat diloncati.');

        $this->postJson("/api/pengemudi/trips/{$schedule->id}/status", ['status' => 'berangkat'])
            ->assertOk()
            ->assertJsonPath('data.status', 'berangkat');

        $this->postJson("/api/pengemudi/trips/{$schedule->id}/status", ['status' => 'berangkat'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Status perjalanan tidak dapat diloncati.');

        $this->postJson("/api/pengemudi/trips/{$schedule->id}/status", ['status' => 'selesai'])
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai');

        $this->assertSame('selesai', $schedule->fresh()->status);
    }

    public function test_pg_08_finishing_trip_cancels_unconfirmed_cash_as_no_show_without_successful_payment(): void
    {
        [$driver, $schedule, $booking, $seat] = $this->createCashPendingBookingForDriver();
        $schedule->update([
            'status' => 'berangkat',
            'waktu_berangkat' => now()->subHour(),
            'estimasi_tiba' => now()->addHour(),
        ]);

        Sanctum::actingAs($driver);

        $this->postJson("/api/pengemudi/trips/{$schedule->id}/status", ['status' => 'selesai'])
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai');

        $this->assertSame('dibatalkan', $booking->fresh()->status_pemesanan);
        $this->assertSame('kedaluwarsa', $booking->pembayaran()->firstOrFail()->status_bayar);
        $this->assertNull($booking->pembayaran()->firstOrFail()->dibayar_pada);
        $this->assertSame('tersedia', $seat->fresh()->status_kursi);
        $this->assertNull($seat->fresh()->dikunci_oleh);
        $this->assertSame(0, $booking->detailPemesanan()->count());
    }

    public function test_ad_08_new_settings_apply_only_to_new_locks_and_bookings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 10:00:00'));

        try {
            $passenger = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
            $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
            $schedule->update([
                'waktu_berangkat' => now()->addDays(2),
                'estimasi_tiba' => now()->addDays(2)->addHours(6),
            ]);
            $seats = $this->availableSeats($schedule, 2);
            Sanctum::actingAs($passenger);

            $this->setSettings([
                'seat_lock_form_minutes' => 5,
                'seat_lock_payment_minutes' => 5,
            ]);
            $this->postJson('/api/penumpang/bookings/lock-form', [
                'jadwal_id' => $schedule->id,
                'seat_numbers' => [$seats[0]],
            ])->assertOk()->assertJsonPath('data.lock_minutes', 5);

            $oldBookingId = $this->postJson(
                '/api/penumpang/bookings',
                $this->bookingPayload($schedule, $seats[0]),
            )->assertCreated()->json('data.id');
            $oldDeadline = Pemesanan::query()->findOrFail($oldBookingId)->batas_pembayaran;
            $this->assertTrue($oldDeadline->equalTo(now()->addMinutes(5)));

            $this->setSettings([
                'seat_lock_form_minutes' => 12,
                'seat_lock_payment_minutes' => 9,
            ]);
            $this->postJson('/api/penumpang/bookings/lock-form', [
                'jadwal_id' => $schedule->id,
                'seat_numbers' => [$seats[1]],
            ])->assertOk()->assertJsonPath('data.lock_minutes', 12);

            $newBookingId = $this->postJson(
                '/api/penumpang/bookings',
                $this->bookingPayload($schedule, $seats[1]),
            )->assertCreated()->json('data.id');

            $this->assertTrue(
                Pemesanan::query()->findOrFail($newBookingId)->batas_pembayaran->equalTo(now()->addMinutes(9)),
            );
            $this->assertTrue(
                Pemesanan::query()->findOrFail($oldBookingId)->batas_pembayaran->equalTo($oldDeadline),
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_focused_pakasir_expiry_shorter_than_admin_becomes_effective_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 11:00:00'));

        try {
            $this->setSettings(['seat_lock_payment_minutes' => 5]);
            config()->set('services.pakasir.enabled', true);
            config()->set('services.pakasir.base_url', 'https://app.pakasir.test/api');
            config()->set('services.pakasir.project', 'travs-test');
            config()->set('services.pakasir.api_key', 'secret-test-key');
            $providerDeadline = now()->addMinutes(3);
            Http::fake(fn ($request) => Http::response([
                'payment' => [
                    'project' => 'travs-test',
                    'order_id' => $request['order_id'],
                    'payment_method' => 'qris',
                    'payment_number' => 'QRIS-SHORTER-RETEST',
                    'amount' => $request['amount'],
                    'fee' => 0,
                    'total_payment' => $request['amount'],
                    'expired_at' => $providerDeadline->toIso8601String(),
                ],
            ]));

            $passenger = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
            $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
            $schedule->update([
                'waktu_berangkat' => now()->addDays(2),
                'estimasi_tiba' => now()->addDays(2)->addHours(6),
            ]);
            $seat = $this->availableSeats($schedule, 1)[0];
            Sanctum::actingAs($passenger);

            $this->postJson('/api/penumpang/bookings/lock-form', [
                'jadwal_id' => $schedule->id,
                'seat_numbers' => [$seat],
            ])->assertOk();
            $bookingResponse = $this->postJson(
                '/api/penumpang/bookings',
                $this->bookingPayload($schedule, $seat),
            )->assertCreated();
            $adminDeadline = Carbon::parse($bookingResponse->json('data.batas_pembayaran'));
            $this->assertTrue($adminDeadline->equalTo(now()->addMinutes(5)));

            $this->putJson(
                '/api/penumpang/bookings/'.$bookingResponse->json('data.id').'/payment',
                ['payment_method' => 'qris'],
            )
                ->assertOk()
                ->assertJsonPath('data.batas_pembayaran', $providerDeadline->toIso8601String())
                ->assertJsonPath('data.payment.expired_at', $providerDeadline->toIso8601String());
        } finally {
            Carbon::setTestNow();
        }
    }

    /** @return array{0: User, 1: Jadwal, 2: Pemesanan, 3: KursiJadwal} */
    private function createCashPendingBookingForDriver(): array
    {
        $driver = User::query()->where('email', 'pengemudi@travs.com')->firstOrFail();
        $schedule = Jadwal::query()
            ->whereHas('pengemudi.pengguna', fn ($query) => $query->whereKey($driver->id))
            ->firstOrFail();
        $seat = KursiJadwal::query()
            ->where('jadwal_id', $schedule->id)
            ->whereDoesntHave('detailPemesanan')
            ->firstOrFail();
        $passenger = User::factory()->create([
            'nama' => 'Penumpang Cash Retest',
            'email' => 'cash-retest@example.com',
            'no_hp' => '089900000011',
            'peran' => 'penumpang',
        ]);
        $booking = Pemesanan::query()->create([
            'pengguna_id' => $passenger->id,
            'jadwal_id' => $schedule->id,
            'kode_booking' => 'RETEST-CASH-'.$seat->id,
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => null,
            'batas_pembayaran' => null,
            'lintang_jemput' => -0.077963,
            'bujur_jemput' => 109.400901,
            'alamat_jemput' => 'Jalan Ampera Raya, Pontianak',
            'subtotal_tiket' => 150000,
            'total_harga' => 150000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);
        DetailPemesanan::query()->create([
            'pemesanan_id' => $booking->id,
            'kursi_jadwal_id' => $seat->id,
            'nomor_kursi' => $seat->nomor_kursi,
            'nama_penumpang' => 'Penumpang Cash Retest',
            'no_hp_penumpang' => '089900000011',
            'status_kehadiran' => 'menunggu',
        ]);
        Pembayaran::query()->create([
            'pemesanan_id' => $booking->id,
            'order_id_gateway' => 'CASH-'.$booking->kode_booking,
            'metode_bayar' => 'cash',
            'jumlah_bayar' => 0,
            'status_bayar' => 'menunggu',
        ]);
        $seat->update([
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $passenger->id,
            'locked_at' => now(),
            'lock_expires_at' => null,
        ]);

        return [$driver, $schedule, $booking, $seat];
    }

    private function setSettings(array $values): void
    {
        foreach ($values as $key => $value) {
            SystemSetting::query()->where('kunci', $key)->firstOrFail()->update([
                'nilai' => (string) $value,
            ]);
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

    private function bookingPayload(Jadwal $schedule, int $seat): array
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
            'passengers' => [[
                'seat_number' => $seat,
                'nama_penumpang' => 'Penumpang Retest',
                'no_hp_penumpang' => '081234567890',
            ]],
            'baggage_items' => [],
        ];
    }
}
