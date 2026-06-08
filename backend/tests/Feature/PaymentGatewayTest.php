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
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        config()->set('services.pakasir.enabled', true);
        config()->set('services.pakasir.base_url', 'https://app.pakasir.test/api');
        config()->set('services.pakasir.project', 'travs-test');
        config()->set('services.pakasir.api_key', 'secret-test-key');
    }

    public function test_configured_qris_booking_creates_real_pakasir_invoice_metadata(): void
    {
        Http::fake([
            'app.pakasir.test/api/transactioncreate/qris' => Http::response([
                'payment' => [
                    'order_id' => 'TRAVS-REAL-001',
                    'payment_number' => 'QRIS-REAL-001',
                    'payment_method' => 'qris',
                    'amount' => 230000,
                    'total_payment' => 231500,
                    'expired_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ]),
        ]);

        $user = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('status_kursi', 'tersedia')
            ->orderByDesc('nomor_kursi')
            ->firstOrFail();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/penumpang/bookings', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seat->nomor_kursi],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Jalan Ahmad Yani, Pontianak',
            ],
            'payment_method' => 'qris',
            'passengers' => [
                [
                    'seat_number' => $seat->nomor_kursi,
                    'nama_penumpang' => 'Penumpang QRIS',
                    'no_hp_penumpang' => '081200000099',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.payment.uses_gateway', true)
            ->assertJsonPath('data.payment.payment_number', 'QRIS-REAL-001')
            ->assertJsonPath(
                'data.payment.checkout_url',
                fn (string $url) => str_starts_with($url, 'https://app.pakasir.com/pay/travs-test/')
                    && str_contains($url, 'order_id=')
                    && str_contains($url, 'qris_only=1')
                    && ! str_contains($url, 'redirect='),
            );

        $booking = Pemesanan::query()->latest('id')->firstOrFail();
        $payment = $booking->pembayaran()->firstOrFail();

        $this->assertSame('qris', $payment->metode_bayar);
        $this->assertSame('menunggu', $payment->status_bayar);
        $this->assertSame('QRIS-REAL-001', $payment->gateway_reference);
        $this->assertSame('231500.00', $payment->jumlah_bayar);

        Http::assertSent(fn ($request) => $request->url() === 'https://app.pakasir.test/api/transactioncreate/qris'
            && $request['project'] === 'travs-test'
            && $request['order_id'] === $booking->kode_booking
            && $request['api_key'] === 'secret-test-key');
    }

    public function test_transfer_booking_uses_pakasir_checkout_without_qris_only_mode(): void
    {
        Http::fake();

        $user = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('status_kursi', 'tersedia')
            ->orderByDesc('nomor_kursi')
            ->firstOrFail();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/penumpang/bookings', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seat->nomor_kursi],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Jalan Ahmad Yani, Pontianak',
            ],
            'payment_method' => 'transfer',
            'passengers' => [
                [
                    'seat_number' => $seat->nomor_kursi,
                    'nama_penumpang' => 'Penumpang Transfer',
                    'no_hp_penumpang' => '081200000077',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.payment.uses_gateway', true)
            ->assertJsonPath('data.payment.method', 'transfer')
            ->assertJsonPath(
                'data.payment.checkout_url',
                fn (string $url) => str_starts_with($url, 'https://app.pakasir.com/pay/travs-test/')
                    && str_contains($url, 'order_id=')
                    && ! str_contains($url, 'redirect=')
                    && ! str_contains($url, 'qris_only=1'),
            );

        $booking = Pemesanan::query()->latest('id')->firstOrFail();
        $payment = $booking->pembayaran()->firstOrFail();
        $gatewayResponse = json_decode((string) $payment->gateway_response, true);

        $this->assertSame('transfer', $payment->metode_bayar);
        $this->assertSame('transfer', $payment->paid_channel);
        $this->assertSame($booking->kode_booking, $payment->order_id_gateway);
        $this->assertSame('transfer', $gatewayResponse['checkout_method']);
        $this->assertSame([], $gatewayResponse['create_payment']);

        Http::assertNothingSent();
    }

    public function test_pakasir_webhook_success_reconciles_transaction_before_issuing_ticket(): void
    {
        $booking = $this->makePendingQrisBooking();

        Http::fake([
            'app.pakasir.test/api/transactiondetail*' => Http::response([
                'transaction' => [
                    'order_id' => $booking->kode_booking,
                    'status' => 'completed',
                    'payment_method' => 'qris',
                    'amount' => 230000,
                    'total_payment' => 231500,
                    'completed_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        $response = $this->postJson('/api/payments/pakasir/webhook', [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 230000,
            'status' => 'completed',
            'payment_method' => 'qris',
            'completed_at' => now()->toIso8601String(),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $booking->refresh();
        $payment = $booking->pembayaran()->firstOrFail();

        $this->assertSame('terbayar', $booking->status_pemesanan);
        $this->assertSame('berhasil', $payment->status_bayar);
        $this->assertNotNull($payment->webhook_verified_at);
        $this->assertDatabaseHas('tiket_elektronik', [
            'detail_pemesanan_id' => $booking->detailPemesanan()->firstOrFail()->id,
            'sudah_dipindai' => false,
        ]);
    }

    public function test_duplicate_pakasir_webhook_is_idempotent(): void
    {
        $booking = $this->makePendingQrisBooking();

        Http::fake([
            'app.pakasir.test/api/transactiondetail*' => Http::response([
                'transaction' => [
                    'order_id' => $booking->kode_booking,
                    'status' => 'completed',
                    'payment_method' => 'qris',
                    'amount' => 230000,
                    'total_payment' => 231500,
                    'completed_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        $payload = [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 230000,
            'status' => 'completed',
            'payment_method' => 'qris',
            'completed_at' => now()->toIso8601String(),
        ];

        $this->postJson('/api/payments/pakasir/webhook', $payload)->assertOk();
        $this->postJson('/api/payments/pakasir/webhook', $payload)->assertOk();

        $detailIds = $booking->detailPemesanan()->pluck('id');
        $this->assertSame(1, TiketElektronik::query()->whereIn('detail_pemesanan_id', $detailIds)->count());
        $this->assertSame('terbayar', $booking->fresh()->status_pemesanan);
    }

    public function test_expired_pakasir_webhook_marks_payment_expired_without_issuing_ticket(): void
    {
        $booking = $this->makePendingQrisBooking();

        Http::fake([
            'app.pakasir.test/api/transactiondetail*' => Http::response([
                'transaction' => [
                    'order_id' => $booking->kode_booking,
                    'status' => 'expired',
                    'payment_method' => 'qris',
                    'amount' => 230000,
                    'total_payment' => 231500,
                ],
            ]),
        ]);

        $response = $this->postJson('/api/payments/pakasir/webhook', [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 230000,
            'status' => 'expired',
            'payment_method' => 'qris',
        ]);

        $response->assertOk();

        $booking->refresh();
        $payment = $booking->pembayaran()->firstOrFail();

        $this->assertSame('dibatalkan', $booking->status_pemesanan);
        $this->assertSame('kedaluwarsa', $payment->status_bayar);
        $this->assertSame(
            0,
            TiketElektronik::query()
                ->whereHas('detailPemesanan', fn ($query) => $query->where('pemesanan_id', $booking->id))
                ->count(),
        );
    }

    public function test_failed_pakasir_webhook_marks_payment_failed_without_issuing_ticket(): void
    {
        $booking = $this->makePendingQrisBooking();

        Http::fake([
            'app.pakasir.test/api/transactiondetail*' => Http::response([
                'transaction' => [
                    'order_id' => $booking->kode_booking,
                    'status' => 'failed',
                    'payment_method' => 'qris',
                    'amount' => 230000,
                    'total_payment' => 231500,
                ],
            ]),
        ]);

        $response = $this->postJson('/api/payments/pakasir/webhook', [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 230000,
            'status' => 'failed',
            'payment_method' => 'qris',
        ]);

        $response->assertOk();

        $booking->refresh();
        $payment = $booking->pembayaran()->firstOrFail();

        $this->assertSame('dibatalkan', $booking->status_pemesanan);
        $this->assertSame('gagal', $payment->status_bayar);
        $this->assertSame(
            0,
            TiketElektronik::query()
                ->whereHas('detailPemesanan', fn ($query) => $query->where('pemesanan_id', $booking->id))
                ->count(),
        );
    }

    private function makePendingQrisBooking(): Pemesanan
    {
        $user = User::query()->where('email', 'penumpang@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('status_kursi', 'tersedia')
            ->orderByDesc('nomor_kursi')
            ->firstOrFail();

        $booking = Pemesanan::query()->create([
            'pengguna_id' => $user->id,
            'jadwal_id' => $jadwal->id,
            'kode_booking' => 'TRAVS-PKS-001',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now()->addMinutes(30),
            'batas_pembayaran' => now()->addMinutes(15),
            'lintang_jemput' => -0.0225,
            'bujur_jemput' => 109.3425,
            'alamat_jemput' => 'Jalan Ahmad Yani, Pontianak',
            'subtotal_tiket' => 225000,
            'biaya_bagasi' => 0,
            'biaya_admin' => 5000,
            'diskon' => 0,
            'total_harga' => 230000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);

        DetailPemesanan::query()->create([
            'pemesanan_id' => $booking->id,
            'kursi_jadwal_id' => $seat->id,
            'nomor_kursi' => $seat->nomor_kursi,
            'nama_penumpang' => 'Penumpang Pakasir',
            'no_hp_penumpang' => '081200000088',
            'status_kehadiran' => 'menunggu',
        ]);

        $booking->pembayaran()->create([
            'order_id_gateway' => $booking->kode_booking,
            'gateway_reference' => 'QRIS-PKS-001',
            'metode_bayar' => 'qris',
            'paid_channel' => 'qris',
            'jumlah_bayar' => 231500,
            'status_bayar' => 'menunggu',
            'expired_at' => now()->addMinutes(15),
        ]);

        $seat->update([
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $user->id,
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes(15),
            'updated_at' => now(),
        ]);

        return $booking->fresh(['detailPemesanan', 'pembayaran']);
    }
}
