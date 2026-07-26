<?php

namespace Tests\Feature;

use App\Models\DetailPemesanan;
use App\Models\Jadwal;
use App\Models\KursiJadwal;
use App\Models\Notifikasi;
use App\Models\Pemesanan;
use App\Models\TiketElektronik;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PakasirService;
use App\Support\PaymentMethodCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
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
        Http::fake(fn ($request) => Http::response([
            'payment' => [
                'project' => 'travs-test',
                'order_id' => $request['order_id'],
                'payment_number' => 'QRIS-REAL-001',
                'payment_method' => 'qris',
                'amount' => $request['amount'],
                'fee' => 4000,
                'total_payment' => $request['amount'] + 4000,
                'expired_at' => now()->addMinutes(15)->toIso8601String(),
            ],
        ]));

        $user = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('status_kursi', 'tersedia')
            ->orderByDesc('nomor_kursi')
            ->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seat->nomor_kursi],
        ])->assertOk();

        $bookingResponse = $this->postJson('/api/penumpang/bookings', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seat->nomor_kursi],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Jalan Ahmad Yani, Pontianak',
                'note' => 'Depan gerbang utama',
            ],
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
                'note' => 'Pintu masuk terminal',
            ],
            'passengers' => [
                [
                    'seat_number' => $seat->nomor_kursi,
                    'nama_penumpang' => 'Penumpang QRIS',
                    'no_hp_penumpang' => '081200000099',
                ],
            ],
        ]);

        $bookingResponse
            ->assertCreated()
            ->assertJsonPath('data.booking_id', $bookingResponse->json('data.id'))
            ->assertJsonPath('data.status_pemesanan', 'menunggu_pembayaran')
            ->assertJsonPath('data.payment.method', null)
            ->assertJsonPath('data.payment.status_pembayaran', 'menunggu_pemilihan_metode')
            ->assertJsonPath('data.payment.can_choose', true);
        Http::assertNothingSent();

        $response = $this->putJson(
            '/api/penumpang/bookings/'.$bookingResponse->json('data.id').'/payment',
            ['payment_method' => 'qris'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.payment.uses_gateway', true)
            ->assertJsonPath('data.payment.payment_number', 'QRIS-REAL-001')
            ->assertJsonPath('data.payment.provider_fee', 4000)
            ->assertJsonPath('data.payment.biaya_admin_provider', 4000)
            ->assertJsonPath(
                'data.payment.total_dibayar_penumpang',
                (int) $bookingResponse->json('data.total_harga') + 4000,
            )
            ->assertJsonPath(
                'data.payment.checkout_url',
                fn ($url) => is_string($url)
                    && str_contains($url, 'https://app.pakasir.com/pay/travs-test/')
                    && str_contains($url, 'qris_only=1')
                    && str_contains($url, 'payment_method=qris'),
            );

        $booking = Pemesanan::query()->latest('id')->firstOrFail();
        $payment = $booking->pembayaran()->firstOrFail();

        $this->assertSame('qris', $payment->metode_bayar);
        $this->assertSame('menunggu', $payment->status_bayar);
        $this->assertSame('QRIS-REAL-001', $payment->gateway_reference);
        $this->assertSame(
            number_format((int) $booking->total_harga + 4000, 2, '.', ''),
            $payment->jumlah_bayar,
        );
        $this->assertSame((int) $booking->total_harga, $payment->nominal_transaksi);
        $this->assertSame(4000, $payment->biaya_admin_provider);
        $this->assertSame((int) $booking->total_harga + 4000, $payment->total_dibayar_penumpang);
        $this->assertSame('QRIS-REAL-001', $payment->payment_number);
        $this->assertMatchesRegularExpression('/^TRX-\d{8}-[A-Z0-9]{5}$/', $payment->order_id_gateway);

        Http::assertSent(fn ($request) => $request->url() === 'https://app.pakasir.test/api/transactioncreate/qris'
            && $request['project'] === 'travs-test'
            && $request['order_id'] === $payment->order_id_gateway
            && $request['api_key'] === 'secret-test-key'
            && $request['amount'] === $payment->nominal_transaksi);

        $this->putJson(
            "/api/penumpang/bookings/{$booking->id}/payment",
            ['payment_method' => 'qris'],
        )->assertOk()->assertJsonPath('data.payment.payment_number', 'QRIS-REAL-001');
        Http::assertSentCount(1);

        $this->putJson(
            "/api/penumpang/bookings/{$booking->id}/payment",
            ['payment_method' => 'bri_va'],
        )->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        Http::assertSentCount(1);
    }

    public function test_bri_va_booking_creates_transaction_with_hosted_payment_link(): void
    {
        Http::fake(fn ($request) => Http::response([
            'payment' => [
                'project' => 'travs-test',
                'order_id' => $request['order_id'],
                'payment_number' => '888801234567890',
                'payment_method' => 'bri_va',
                'amount' => $request['amount'],
                'fee' => 3500,
                'total_payment' => $request['amount'] + 3500,
                'expired_at' => now()->addMinutes(15)->toIso8601String(),
            ],
        ]));

        $user = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('status_kursi', 'tersedia')
            ->orderByDesc('nomor_kursi')
            ->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seat->nomor_kursi],
        ])->assertOk();

        $bookingResponse = $this->postJson('/api/penumpang/bookings', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seat->nomor_kursi],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Jalan Ahmad Yani, Pontianak',
                'note' => 'Depan gerbang utama',
            ],
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
                'note' => 'Pintu masuk terminal',
            ],
            'passengers' => [
                [
                    'seat_number' => $seat->nomor_kursi,
                    'nama_penumpang' => 'Penumpang BRI VA',
                    'no_hp_penumpang' => '081200000077',
                ],
            ],
        ]);

        $bookingResponse->assertCreated();
        Http::assertNothingSent();

        $response = $this->putJson(
            '/api/penumpang/bookings/'.$bookingResponse->json('data.id').'/payment',
            ['payment_method' => 'bri_va'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.payment.uses_gateway', true)
            ->assertJsonPath('data.payment.method', 'bri_va')
            ->assertJsonPath('data.payment.method_label', 'Virtual Account BRI')
            ->assertJsonPath('data.payment.method_family', 'va')
            ->assertJsonPath('data.payment.provider_fee', 3500)
            ->assertJsonPath('data.payment.nominal_transaksi', (int) $bookingResponse->json('data.total_harga'))
            ->assertJsonPath('data.payment.biaya_admin_provider', 3500)
            ->assertJsonPath(
                'data.payment.total_dibayar_penumpang',
                (int) $bookingResponse->json('data.total_harga') + 3500,
            )
            ->assertJsonPath('data.payment.payment_number', '888801234567890')
            ->assertJsonPath(
                'data.payment.checkout_url',
                fn ($url) => is_string($url)
                    && str_contains($url, 'https://app.pakasir.com/pay/travs-test/')
                    && str_contains($url, 'payment_method=bri_va')
                    && ! str_contains($url, 'qris_only=1'),
            );

        $booking = Pemesanan::query()->latest('id')->firstOrFail();
        $payment = $booking->pembayaran()->firstOrFail();
        $gatewayResponse = json_decode((string) $payment->gateway_response, true);

        $this->assertSame('bri_va', $payment->metode_bayar);
        $this->assertSame('bri_va', $payment->paid_channel);
        $this->assertMatchesRegularExpression('/^TRX-\d{8}-[A-Z0-9]{5}$/', $payment->order_id_gateway);
        $this->assertSame('direct_api', $gatewayResponse['integration_mode']);
        $this->assertSame('888801234567890', $gatewayResponse['create_payment']['payment_number']);
        $this->assertSame((int) $booking->total_harga, $payment->nominal_transaksi);
        $this->assertSame(3500, $payment->biaya_admin_provider);
        $this->assertSame((int) $booking->total_harga + 3500, $payment->total_dibayar_penumpang);
        $this->assertSame('888801234567890', $payment->payment_number);

        Http::assertSent(fn ($request) => $request->url() === 'https://app.pakasir.test/api/transactioncreate/bri_va'
            && $request['amount'] === $payment->nominal_transaksi
            && $request['order_id'] === $payment->order_id_gateway);
    }

    public function test_cash_selection_uses_local_snapshot_without_calling_pakasir(): void
    {
        Http::fake();
        $booking = $this->makeBookingWithoutPayment();
        Sanctum::actingAs($booking->pengguna);

        $response = $this->putJson(
            "/api/penumpang/bookings/{$booking->id}/payment",
            ['payment_method' => 'cash'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.payment.method', 'cash')
            ->assertJsonPath('data.payment.status', 'menunggu')
            ->assertJsonPath('data.payment.status_pembayaran', 'menunggu_konfirmasi_tunai')
            ->assertJsonPath('data.payment.nominal_transaksi', 230000)
            ->assertJsonPath('data.payment.biaya_admin_provider', 0)
            ->assertJsonPath('data.payment.total_dibayar_penumpang', 230000)
            ->assertJsonPath('data.payment.provider_order_id', null)
            ->assertJsonPath('data.payment.payment_number', null)
            ->assertJsonPath('data.payment.expired_at', null)
            ->assertJsonPath('data.batas_pembayaran', null)
            ->assertJsonPath('data.payment.uses_gateway', false);

        // Request berulang tetap mengembalikan transaksi tunai yang sama.
        $this->putJson(
            "/api/penumpang/bookings/{$booking->id}/payment",
            ['payment_method' => 'cash'],
        )
            ->assertOk()
            ->assertJsonPath('data.payment.method', 'cash')
            ->assertJsonPath('data.batas_pembayaran', null);

        $booking->refresh();
        $payment = $booking->pembayaran()->firstOrFail();
        $seat = $booking->detailPemesanan()->firstOrFail()->kursiJadwal()->firstOrFail();
        $this->assertNull($booking->batas_pembayaran);
        $this->assertNull($seat->lock_expires_at);
        $this->assertNull($payment->order_id_gateway);
        $this->assertSame(230000, $payment->nominal_transaksi);
        $this->assertSame(0, $payment->biaya_admin_provider);
        $this->assertSame(230000, $payment->total_dibayar_penumpang);
        $this->assertSame('menunggu', $payment->status_bayar);
        $this->assertSame(1, $booking->pembayaran()->count());
        Http::assertNothingSent();
    }

    public function test_sandbox_zero_fee_is_saved_without_replacement(): void
    {
        $booking = $this->makeBookingWithoutPayment();
        Http::fake(fn ($request) => Http::response([
            'payment' => [
                'project' => 'travs-test',
                'order_id' => $request['order_id'],
                'payment_number' => '7777000011112222',
                'payment_method' => 'bni_va',
                'amount' => $request['amount'],
                'fee' => 0,
                'total_payment' => $request['amount'],
                'expired_at' => now()->addMinutes(15)->toIso8601String(),
            ],
        ]));
        Sanctum::actingAs($booking->pengguna);

        $response = $this->putJson(
            "/api/penumpang/bookings/{$booking->id}/payment",
            ['payment_method' => 'bni_va'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.payment.nominal_transaksi', 230000)
            ->assertJsonPath('data.payment.biaya_admin_provider', 0)
            ->assertJsonPath('data.payment.total_dibayar_penumpang', 230000);

        $payment = $booking->pembayaran()->firstOrFail();
        $this->assertSame(230000, $payment->nominal_transaksi);
        $this->assertSame(0, $payment->biaya_admin_provider);
        $this->assertSame(230000, $payment->total_dibayar_penumpang);
    }

    public function test_customer_total_adds_provider_fee_and_keeps_raw_provider_response(): void
    {
        $booking = $this->makeBookingWithoutPayment();
        Http::fake(fn ($request) => Http::response([
            'payment' => [
                'project' => 'travs-test',
                'order_id' => $request['order_id'],
                'payment_number' => 'QRIS-SANDBOX-DEDUCTED-FEE',
                'payment_method' => 'qris',
                'amount' => $request['amount'],
                'fee' => 2300,
                // Sandbox dapat memotong fee dari dana merchant.
                'total_payment' => $request['amount'],
                'received' => $request['amount'] - 2300,
                'expired_at' => now()->addMinutes(15)->toIso8601String(),
            ],
        ]));
        Sanctum::actingAs($booking->pengguna);

        $response = $this->putJson(
            "/api/penumpang/bookings/{$booking->id}/payment",
            ['payment_method' => 'qris'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.payment.nominal_transaksi', 230000)
            ->assertJsonPath('data.payment.biaya_admin_provider', 2300)
            ->assertJsonPath('data.payment.total_dibayar_penumpang', 232300);

        $payment = $booking->pembayaran()->firstOrFail();
        $gatewayResponse = json_decode((string) $payment->gateway_response, true);
        $this->assertSame(230000, $payment->nominal_transaksi);
        $this->assertSame(2300, $payment->biaya_admin_provider);
        $this->assertSame(232300, $payment->total_dibayar_penumpang);
        $this->assertSame(230000, $gatewayResponse['create_payment']['total_payment']);
        $this->assertSame('menunggu_pembayaran', $booking->fresh()->status_pemesanan);
    }

    public function test_incomplete_transaction_create_response_is_rejected_without_ticket(): void
    {
        $booking = $this->makeBookingWithoutPayment();
        Http::fake(fn ($request) => Http::response([
            'payment' => [
                'project' => 'travs-test',
                'order_id' => $request['order_id'],
                'payment_method' => 'qris',
                'amount' => $request['amount'],
                'fee' => 1000,
                'total_payment' => $request['amount'] + 1000,
                // payment_number dan expired_at sengaja tidak ada.
            ],
        ]));
        Sanctum::actingAs($booking->pengguna);

        $this->putJson(
            "/api/penumpang/bookings/{$booking->id}/payment",
            ['payment_method' => 'qris'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        $this->assertSame('dibatalkan', $booking->fresh()->status_pemesanan);
        $this->assertSame('gagal', $booking->pembayaran()->firstOrFail()->status_bayar);
        $this->assertSame(0, TiketElektronik::query()
            ->whereHas('detailPemesanan', fn ($query) => $query->where('pemesanan_id', $booking->id))
            ->count());
    }

    public function test_every_documented_direct_pakasir_method_is_allowed(): void
    {
        $service = app(PakasirService::class);
        Http::fake(fn ($request) => Http::response([
            'payment' => [
                'order_id' => $request['order_id'],
                'payment_method' => basename($request->url()),
                'payment_number' => 'PAYMENT-NUMBER',
                'amount' => $request['amount'],
                'fee' => 0,
                'total_payment' => $request['amount'],
            ],
        ]));

        foreach (PaymentMethodCatalog::directGatewayCodes() as $method) {
            $this->assertTrue($service->canCreateTransaction($method), $method);
            $this->assertTrue($service->handlesMethod($method), $method);
            $payment = $service->createTransaction($method, "METHOD-{$method}", 100000);
            $this->assertSame($method, $payment['payment_method']);
            Http::assertSent(fn ($request) => $request->url() === "https://app.pakasir.test/api/transactioncreate/{$method}");
        }

        $this->assertFalse($service->canCreateTransaction('transfer'));
        $this->assertTrue($service->handlesMethod('transfer'));
        $this->assertFalse($service->canCreateTransaction('mandiri_va'));
    }

    public function test_schedule_exposes_all_va_options_from_single_transfer_toggle(): void
    {
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();

        $response = $this->getJson("/api/guest/jadwal/{$jadwal->id}");

        $response->assertOk();
        $options = collect($response->json('data.payment_methods.options'));

        foreach (array_keys(PaymentMethodCatalog::VIRTUAL_ACCOUNTS) as $method) {
            $this->assertTrue($options->contains('code', $method), $method);
        }

        $this->assertFalse($options->contains('code', 'transfer'));
    }

    public function test_failed_va_creation_cancels_booking_and_releases_seat(): void
    {
        Http::fake([
            'app.pakasir.test/api/transactioncreate/bni_va' => Http::response([
                'message' => 'Gateway sedang tidak tersedia.',
            ], 503),
        ]);

        $user = User::query()->where('email', 'fitri@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $seat = KursiJadwal::query()
            ->where('jadwal_id', $jadwal->id)
            ->where('status_kursi', 'tersedia')
            ->orderByDesc('nomor_kursi')
            ->firstOrFail();

        Sanctum::actingAs($user);
        $this->postJson('/api/penumpang/bookings/lock-form', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seat->nomor_kursi],
        ])->assertOk();

        $bookingResponse = $this->postJson('/api/penumpang/bookings', [
            'jadwal_id' => $jadwal->id,
            'seat_numbers' => [$seat->nomor_kursi],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Jalan Ahmad Yani, Pontianak',
                'note' => 'Depan gerbang utama',
            ],
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
                'note' => 'Pintu masuk terminal',
            ],
            'passengers' => [[
                'seat_number' => $seat->nomor_kursi,
                'nama_penumpang' => 'Penumpang BNI VA',
                'no_hp_penumpang' => '081200000088',
            ]],
        ]);

        $bookingResponse->assertCreated();
        $this->putJson(
            '/api/penumpang/bookings/'.$bookingResponse->json('data.id').'/payment',
            ['payment_method' => 'bni_va'],
        )->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $booking = Pemesanan::query()->latest('id')->firstOrFail();
        $this->assertSame('dibatalkan', $booking->status_pemesanan);
        $this->assertSame('gagal', $booking->pembayaran()->firstOrFail()->status_bayar);
        $this->assertSame(0, $booking->detailPemesanan()->count());
        $this->assertSame('tersedia', $seat->fresh()->status_kursi);
    }

    public function test_timeout_after_create_recovers_completed_transaction_without_creating_second_invoice(): void
    {
        $booking = $this->makeBookingWithoutPayment();
        $capturedOrderId = null;

        Http::fake(function ($request) use ($booking, &$capturedOrderId) {
            if (str_contains($request->url(), '/transactioncreate/qris')) {
                $capturedOrderId = (string) $request['order_id'];

                return Http::failedConnection('Respons create terputus setelah dikirim.');
            }

            return Http::response([
                'transaction' => [
                    'project' => 'travs-test',
                    'order_id' => $capturedOrderId,
                    'amount' => (int) $booking->total_harga,
                    'payment_method' => 'qris',
                    'status' => 'completed',
                    'completed_at' => now()->toIso8601String(),
                ],
            ]);
        });

        $user = $booking->pengguna;
        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/penumpang/bookings/{$booking->id}/payment",
            ['payment_method' => 'qris'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'terbayar');

        $this->assertSame('terbayar', $booking->fresh()->status_pemesanan);
        $this->assertSame('berhasil', $booking->pembayaran()->firstOrFail()->status_bayar);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/transactiondetail'));
        $this->assertSame(1, collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), '/transactioncreate/'))
            ->count());
    }

    public function test_pending_transaction_after_timeout_is_reconciled_without_retrying_create(): void
    {
        $booking = $this->makeBookingWithoutPayment();
        $orderId = 'TRX-TIMEOUT-PENDING';
        $payment = $booking->pembayaran()->create([
            'order_id_gateway' => $orderId,
            'metode_bayar' => 'qris',
            'nominal_transaksi' => (int) $booking->total_harga,
            'jumlah_bayar' => (int) $booking->total_harga,
            'status_bayar' => 'menunggu',
            'gateway_response' => json_encode([
                'provider' => 'pakasir',
                'integration_mode' => 'direct_api',
                'activation_status' => 'verification_pending',
            ]),
        ]);

        Http::fake([
            'app.pakasir.test/api/transactiondetail*' => Http::response([
                'transaction' => [
                    'project' => 'travs-test',
                    'order_id' => $orderId,
                    'amount' => (int) $booking->total_harga,
                    'payment_method' => 'qris',
                    'status' => 'pending',
                ],
            ]),
        ]);

        Sanctum::actingAs($booking->pengguna);

        $this->putJson(
            "/api/penumpang/bookings/{$booking->id}/payment",
            ['payment_method' => 'qris'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        $this->assertSame('menunggu_pembayaran', $booking->fresh()->status_pemesanan);
        $this->assertSame('menunggu', $payment->fresh()->status_bayar);
        $this->assertGreaterThan(0, $booking->detailPemesanan()->count());
        $gatewayResponse = json_decode((string) $payment->fresh()->gateway_response, true);
        $this->assertSame('provider_transaction_found', $gatewayResponse['activation_status']);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/transactioncreate/'));
    }

    public function test_overlapping_payment_selection_uses_existing_claim_instead_of_creating_second_invoice(): void
    {
        $booking = $this->makeBookingWithoutPayment();
        $user = $booking->pengguna;
        $overlappingRequestWasReconciled = false;

        Http::fake(function ($request) use ($booking, $user, &$overlappingRequestWasReconciled) {
            if (str_contains($request->url(), '/transactiondetail')) {
                return Http::response([
                    'transaction' => [
                        'project' => 'travs-test',
                        'order_id' => (string) $request['order_id'],
                        'amount' => (int) $request['amount'],
                        'payment_method' => 'qris',
                        'status' => 'pending',
                    ],
                ]);
            }

            // Saat request pertama masih menunggu response create, request kedua
            // sudah melihat klaim lokal dan hanya memanggil detail transaksi.
            try {
                app(BookingService::class)->choosePaymentMethod(
                    $booking->fresh(),
                    $user,
                    'qris',
                );
            } catch (ValidationException) {
                $overlappingRequestWasReconciled = true;
            }

            return Http::response([
                'payment' => [
                    'project' => 'travs-test',
                    'order_id' => (string) $request['order_id'],
                    'amount' => (int) $request['amount'],
                    'fee' => 1000,
                    'total_payment' => (int) $request['amount'] + 1000,
                    'payment_method' => 'qris',
                    'payment_number' => 'QRIS-OVERLAP-001',
                    'expired_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ]);
        });

        Sanctum::actingAs($user);

        $this->putJson(
            "/api/penumpang/bookings/{$booking->id}/payment",
            ['payment_method' => 'qris'],
        )
            ->assertOk()
            ->assertJsonPath('data.payment.payment_number', 'QRIS-OVERLAP-001');

        $this->assertTrue($overlappingRequestWasReconciled);
        $this->assertSame(1, collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), '/transactioncreate/'))
            ->count());
        $this->assertSame(1, collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), '/transactiondetail'))
            ->count());
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
        $notification = Notifikasi::query()
            ->where('pengguna_id', $booking->pengguna_id)
            ->where('judul', 'Pembayaran berhasil dikonfirmasi')
            ->where('referensi_id', $booking->id)
            ->firstOrFail();
        $this->assertStringNotContainsString($booking->kode_booking, $notification->pesan);
        $this->assertStringContainsString('jadwal keberangkatan', $notification->pesan);
    }

    public function test_webhook_keeps_transaction_create_fee_and_total_snapshots(): void
    {
        $booking = $this->makePendingQrisBooking();
        $booking->pembayaran()->update([
            'nominal_transaksi' => 230000,
            'biaya_admin_provider' => 3500,
            'total_dibayar_penumpang' => 233500,
            'jumlah_bayar' => 233500,
        ]);

        Http::fake([
            'app.pakasir.test/api/transactiondetail*' => Http::response([
                'transaction' => [
                    'project' => 'travs-test',
                    'order_id' => $booking->kode_booking,
                    'status' => 'completed',
                    'payment_method' => 'qris',
                    'amount' => 230000,
                    // Detail API tidak boleh mengganti snapshot hasil create.
                    'fee' => 9999,
                    'total_payment' => 239999,
                    'completed_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        $this->postJson('/api/payments/pakasir/webhook', [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 230000,
            'status' => 'completed',
            'payment_method' => 'qris',
            'completed_at' => now()->toIso8601String(),
        ])->assertOk();

        $payment = $booking->pembayaran()->firstOrFail();
        $this->assertSame(230000, $payment->nominal_transaksi);
        $this->assertSame(3500, $payment->biaya_admin_provider);
        $this->assertSame(233500, $payment->total_dibayar_penumpang);
        $this->assertSame(number_format(233500, 2, '.', ''), $payment->jumlah_bayar);
    }

    public function test_webhook_does_not_issue_ticket_when_payload_status_disagrees_with_completed_detail(): void
    {
        $booking = $this->makePendingQrisBooking();

        Http::fake([
            'app.pakasir.test/api/transactiondetail*' => Http::response([
                'transaction' => [
                    'project' => 'travs-test',
                    'order_id' => $booking->kode_booking,
                    'status' => 'completed',
                    'payment_method' => 'qris',
                    'amount' => 230000,
                    'completed_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        $this->postJson('/api/payments/pakasir/webhook', [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 230000,
            'status' => 'failed',
            'payment_method' => 'qris',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('menunggu_pembayaran', $booking->fresh()->status_pemesanan);
        $this->assertSame('menunggu', $booking->pembayaran()->firstOrFail()->status_bayar);
        $this->assertSame(0, TiketElektronik::query()
            ->whereHas('detailPemesanan', fn ($query) => $query->where('pemesanan_id', $booking->id))
            ->count());
    }

    public function test_gateway_paid_time_is_converted_to_application_timezone(): void
    {
        $booking = $this->makePendingQrisBooking();
        $completedAtUtc = '2026-07-16T11:14:00Z';

        Http::fake([
            'app.pakasir.test/api/transactiondetail*' => Http::response([
                'transaction' => [
                    'order_id' => $booking->kode_booking,
                    'status' => 'completed',
                    'payment_method' => 'qris',
                    'amount' => 230000,
                    'total_payment' => 231500,
                    'completed_at' => $completedAtUtc,
                ],
            ]),
        ]);

        $this->postJson('/api/payments/pakasir/webhook', [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 230000,
            'status' => 'completed',
            'payment_method' => 'qris',
            'completed_at' => $completedAtUtc,
        ])->assertOk();

        $paidAt = $booking->pembayaran()->firstOrFail()->dibayar_pada;

        $this->assertSame('2026-07-16 18:14:00', $paidAt?->format('Y-m-d H:i:s'));
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
        $this->assertSame(1, $booking->pembayaran()->count());
        $this->assertSame('terbayar', $booking->fresh()->status_pemesanan);
        $this->assertSame(
            1,
            Notifikasi::query()
                ->where('pengguna_id', $booking->pengguna_id)
                ->where('judul', 'Pembayaran berhasil dikonfirmasi')
                ->where('referensi_id', $booking->id)
                ->count(),
        );
    }

    public function test_webhook_rejects_fractional_and_mismatched_amounts(): void
    {
        $booking = $this->makePendingQrisBooking();

        $this->postJson('/api/payments/pakasir/webhook', [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 230000.5,
            'status' => 'completed',
            'payment_method' => 'qris',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->postJson('/api/payments/pakasir/webhook', [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 229999,
            'status' => 'completed',
            'payment_method' => 'qris',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertSame('menunggu_pembayaran', $booking->fresh()->status_pemesanan);
        $this->assertSame(
            0,
            TiketElektronik::query()
                ->whereIn('detail_pemesanan_id', $booking->detailPemesanan()->pluck('id'))
                ->count(),
        );
    }

    public function test_webhook_rejects_transaction_detail_for_another_order_or_amount(): void
    {
        $booking = $this->makePendingQrisBooking();

        Http::fake([
            'app.pakasir.test/api/transactiondetail*' => Http::sequence()
                ->push([
                    'transaction' => [
                        'order_id' => 'TRANSAKSI-LAIN',
                        'project' => 'travs-test',
                        'status' => 'completed',
                        'payment_method' => 'qris',
                        'amount' => 230000,
                        'total_payment' => 230000,
                    ],
                ])
                ->push([
                    'transaction' => [
                        'order_id' => $booking->kode_booking,
                        'project' => 'travs-test',
                        'status' => 'completed',
                        'payment_method' => 'qris',
                        'amount' => 230001,
                        'total_payment' => 230001,
                    ],
                ]),
        ]);

        $payload = [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 230000,
            'status' => 'completed',
            'payment_method' => 'qris',
        ];

        $this->postJson('/api/payments/pakasir/webhook', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_id');

        $this->postJson('/api/payments/pakasir/webhook', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame('menunggu_pembayaran', $booking->fresh()->status_pemesanan);
        $this->assertSame(
            0,
            TiketElektronik::query()
                ->whereIn('detail_pemesanan_id', $booking->detailPemesanan()->pluck('id'))
                ->count(),
        );
    }

    public function test_pakasir_webhook_rejects_cash_payment(): void
    {
        $booking = $this->makePendingGatewayBooking('cash');

        $this->postJson('/api/payments/pakasir/webhook', [
            'project' => 'travs-test',
            'order_id' => $booking->kode_booking,
            'amount' => 230000,
            'status' => 'completed',
            'payment_method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        Http::assertNothingSent();
        $this->assertSame('menunggu', $booking->pembayaran()->firstOrFail()->status_bayar);
    }

    public function test_late_success_webhook_does_not_reopen_completed_booking(): void
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
        $booking->update(['status_pemesanan' => 'selesai']);

        $this->postJson('/api/payments/pakasir/webhook', $payload)->assertOk();

        $detailIds = $booking->detailPemesanan()->pluck('id');
        $this->assertSame('selesai', $booking->fresh()->status_pemesanan);
        $this->assertSame(1, $booking->pembayaran()->count());
        $this->assertSame(1, TiketElektronik::query()->whereIn('detail_pemesanan_id', $detailIds)->count());
    }

    public function test_passenger_can_refresh_qris_payment_after_pakasir_simulation(): void
    {
        $booking = $this->makePendingGatewayBooking('qris');
        $user = User::query()->findOrFail($booking->pengguna_id);

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

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/penumpang/bookings/{$booking->id}/refresh-payment-status");

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'terbayar')
            ->assertJsonPath('data.payment.status', 'berhasil');

        $booking->refresh();
        $payment = $booking->pembayaran()->firstOrFail();

        $this->assertSame('terbayar', $booking->status_pemesanan);
        $this->assertSame('berhasil', $payment->status_bayar);
        $this->assertDatabaseHas('tiket_elektronik', [
            'detail_pemesanan_id' => $booking->detailPemesanan()->firstOrFail()->id,
        ]);
    }

    public function test_passenger_can_refresh_transfer_payment_after_pakasir_simulation(): void
    {
        $booking = $this->makePendingGatewayBooking('transfer');
        $user = User::query()->findOrFail($booking->pengguna_id);

        Http::fake([
            'app.pakasir.test/api/transactiondetail*' => Http::response([
                'transaction' => [
                    'order_id' => $booking->kode_booking,
                    'status' => 'completed',
                    'payment_method' => 'bri_va',
                    'amount' => 230000,
                    'fee' => 3500,
                    'total_payment' => 231500,
                    'completed_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/penumpang/bookings/{$booking->id}/refresh-payment-status");

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'terbayar')
            ->assertJsonPath('data.payment.status', 'berhasil');

        $booking->refresh();
        $payment = $booking->pembayaran()->firstOrFail();

        $this->assertSame('terbayar', $booking->status_pemesanan);
        $this->assertSame('berhasil', $payment->status_bayar);
        $this->assertSame('bri_va', $payment->paid_channel);
        $this->assertSame(230000, $payment->nominal_transaksi);
        $this->assertSame(3500, $payment->biaya_admin_provider);
        $this->assertSame(231500, $payment->total_dibayar_penumpang);
        $this->assertDatabaseHas('tiket_elektronik', [
            'detail_pemesanan_id' => $booking->detailPemesanan()->firstOrFail()->id,
        ]);
    }

    public function test_gateway_completed_payment_is_synced_before_expired_booking_cleanup(): void
    {
        $booking = $this->makePendingGatewayBooking('qris');
        $detailId = $booking->detailPemesanan()->firstOrFail()->id;

        $booking->update([
            'batas_pembayaran' => now()->subMinutes(10),
        ]);
        $booking->pembayaran()->update([
            'expired_at' => now()->subMinutes(10),
        ]);

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

        app(BookingService::class)->releaseExpiredResources($booking->jadwal_id);

        $booking->refresh();
        $payment = $booking->pembayaran()->firstOrFail();

        $this->assertSame('terbayar', $booking->status_pemesanan);
        $this->assertSame('berhasil', $payment->status_bayar);
        $this->assertDatabaseHas('tiket_elektronik', [
            'detail_pemesanan_id' => $detailId,
        ]);
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
        return $this->makePendingGatewayBooking('qris');
    }

    private function makeBookingWithoutPayment(): Pemesanan
    {
        $booking = $this->makePendingGatewayBooking('qris');
        $booking->pembayaran()->delete();

        return $booking->fresh(['pengguna', 'detailPemesanan', 'pembayaran']);
    }

    private function makePendingGatewayBooking(string $paymentMethod): Pemesanan
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
            'gateway_reference' => strtoupper($paymentMethod).'-PKS-001',
            'metode_bayar' => $paymentMethod,
            'paid_channel' => $paymentMethod,
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
