<?php

namespace App\Services;

use App\Models\DetailPemesanan;
use App\Models\ItemBagasi;
use App\Models\Jadwal;
use App\Models\KursiJadwal;
use App\Models\Notifikasi;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\TiketElektronik;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookingService
{
    public function __construct(
        private readonly BookingCalculator $calculator,
        private readonly PakasirService $pakasirService,
        private readonly ScheduleLifecycleService $scheduleLifecycleService,
        private readonly SettingService $settingService,
        private readonly NotificationTemplateService $notificationTemplate,
        private readonly AdminOperationalNotificationService $adminNotification,
    ) {}

    /**
     * Mengunci kursi sementara selama pengguna mengisi form pemesanan.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function lockSeatsForForm(User $user, array $payload): array
    {
        $scheduleId = (int) $payload['jadwal_id'];
        $seatNumbers = $this->normalizeSeatNumbers($payload['seat_numbers'] ?? []);
        $this->ensureSeatSelectionValid($seatNumbers);
        $this->releaseExpiredResources($scheduleId);

        return DB::transaction(function () use ($user, $scheduleId, $seatNumbers) {
            $schedule = Jadwal::query()
                ->lockForUpdate()
                ->findOrFail($scheduleId);

            $this->scheduleLifecycleService->closeSingleIfPastBookingWindow($schedule);
            $this->ensureScheduleOpen($schedule);

            $selectedSeats = $this->loadSeatRowsForUpdate($schedule->id, $seatNumbers);
            $this->ensureSeatRowsMatchSelection($selectedSeats, $seatNumbers);
            $this->ensureSeatsCanBeUsedByUser($selectedSeats, $user->id);

            $now = now();
            $formDeadline = $this->resolveFormLockDeadline($now);

            foreach ($selectedSeats as $seat) {
                $seat->update([
                    'status_kursi' => 'terkunci',
                    'dikunci_oleh' => $user->id,
                    'locked_at' => $now,
                    'lock_expires_at' => $formDeadline,
                    'updated_at' => $now,
                ]);
            }

            return [
                'jadwal_id' => $schedule->id,
                'seat_numbers' => $seatNumbers->all(),
                'lock_expires_at' => $formDeadline->toIso8601String(),
                'lock_minutes' => (int) $this->settingService->get('seat_lock_form_minutes', 30),
            ];
        });
    }

    /**
     * Membuat pemesanan menunggu pembayaran beserta kursi, penumpang, dan bagasi.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function createDraft(User $user, array $payload): Pemesanan
    {
        $scheduleId = (int) $payload['jadwal_id'];
        $seatNumbers = $this->normalizeSeatNumbers($payload['seat_numbers'] ?? []);
        $this->ensureSeatSelectionValid($seatNumbers);

        $paymentMethod = (string) $payload['payment_method'];
        $this->ensurePaymentMethodEnabled($paymentMethod);

        $passengers = collect($payload['passengers'] ?? []);
        $this->ensurePassengersMatchSeats($passengers, $seatNumbers);

        $this->releaseExpiredResources($scheduleId);

        $booking = DB::transaction(function () use ($user, $payload, $scheduleId, $paymentMethod, $seatNumbers, $passengers) {
            $schedule = Jadwal::query()
                ->with(['rute', 'kendaraan'])
                ->lockForUpdate()
                ->findOrFail($scheduleId);

            $this->scheduleLifecycleService->closeSingleIfPastBookingWindow($schedule);
            $this->ensureScheduleOpen($schedule);

            $selectedSeats = $this->loadSeatRowsForUpdate($schedule->id, $seatNumbers);
            $this->ensureSeatRowsMatchSelection($selectedSeats, $seatNumbers);
            $this->ensureSeatsCanBeUsedByUser($selectedSeats, $user->id);

            $totals = $this->calculator->calculate(
                $schedule,
                $seatNumbers->count(),
                $payload['baggage_items'] ?? [],
            );

            $now = now();
            $paymentDeadline = $this->resolvePaymentLockDeadline($now, $schedule, $paymentMethod);
            $formDeadline = $selectedSeats
                ->pluck('lock_expires_at')
                ->filter()
                ->sort()
                ->first() ?? $this->resolveFormLockDeadline($now);

            $booking = Pemesanan::query()->create([
                'pengguna_id' => $user->id,
                'jadwal_id' => $schedule->id,
                'kode_booking' => $this->generateBookingCode(),
                'jumlah_kursi' => $seatNumbers->count(),
                'batas_kunci_kursi' => $formDeadline,
                'batas_pembayaran' => $paymentDeadline,
                'lintang_jemput' => data_get($payload, 'pickup.latitude'),
                'bujur_jemput' => data_get($payload, 'pickup.longitude'),
                'alamat_jemput' => data_get($payload, 'pickup.address'),
                'catatan_jemput' => data_get($payload, 'pickup.note'),
                'lintang_turun' => data_get($payload, 'dropoff.latitude'),
                'bujur_turun' => data_get($payload, 'dropoff.longitude'),
                'alamat_turun' => data_get($payload, 'dropoff.address'),
                'catatan_turun' => data_get($payload, 'dropoff.note'),
                'subtotal_tiket' => $totals['subtotal_tiket'],
                'biaya_bagasi' => $totals['biaya_bagasi'],
                'diskon' => $totals['diskon'],
                'total_harga' => $totals['total_harga'],
                'status_pemesanan' => 'menunggu_pembayaran',
            ]);

            foreach ($selectedSeats as $seat) {
                $passenger = $passengers->firstWhere('seat_number', $seat->nomor_kursi);

                DetailPemesanan::query()->create([
                    'pemesanan_id' => $booking->id,
                    'kursi_jadwal_id' => $seat->id,
                    'nomor_kursi' => $seat->nomor_kursi,
                    'nama_penumpang' => (string) data_get($passenger, 'nama_penumpang'),
                    'no_hp_penumpang' => (string) data_get($passenger, 'no_hp_penumpang'),
                    'status_kehadiran' => 'menunggu',
                ]);

                $seat->update([
                    'status_kursi' => 'terkunci',
                    'dikunci_oleh' => $user->id,
                    'locked_at' => $now,
                    'lock_expires_at' => $paymentDeadline,
                    'updated_at' => $now,
                ]);
            }

            foreach (($payload['baggage_items'] ?? []) as $item) {
                $panjangCm = data_get($item, 'panjang_cm');
                $lebarCm = data_get($item, 'lebar_cm');
                $ukuranCm = data_get($item, 'ukuran_cm');
                if ($ukuranCm === null && ($panjangCm !== null || $lebarCm !== null)) {
                    $ukuranCm = (int) ($panjangCm ?? 0) + (int) ($lebarCm ?? 0);
                }
                $fotoUrls = $this->normalizeBaggagePhotoUrls($item);

                ItemBagasi::query()->create([
                    'pemesanan_id' => $booking->id,
                    'deskripsi_item' => (string) $item['deskripsi_item'],
                    'metode_hitung' => data_get($item, 'metode_hitung'),
                    'berat_kg' => data_get($item, 'berat_kg'),
                    'ukuran_cm' => $ukuranCm,
                    'panjang_cm' => $panjangCm,
                    'lebar_cm' => $lebarCm,
                    'jumlah_item' => max(1, (int) data_get($item, 'jumlah_item', 1)),
                    'foto_url' => $fotoUrls[0] ?? null,
                    'foto_urls' => $fotoUrls,
                    'biaya_item' => 0,
                ]);
            }

            Pembayaran::query()->create([
                'pemesanan_id' => $booking->id,
                'order_id_gateway' => 'DRAFT-'.Str::upper(Str::random(12)),
                'metode_bayar' => $paymentMethod,
                'jumlah_bayar' => $totals['total_harga'],
                'status_bayar' => 'menunggu',
                'gateway_response' => 'Draft pembayaran dibuat, menunggu proses selanjutnya.',
                'expired_at' => $paymentDeadline,
            ]);

            return $booking->fresh([
                'jadwal.rute',
                'jadwal.kendaraan',
                'detailPemesanan.kursiJadwal',
                'itemBagasi',
                'pembayaran',
            ]);
        });

        $this->adminNotification->bookingCreated($booking);
        $this->adminNotification->capacityAlmostFull($booking->jadwal);

        if ($this->pakasirService->handlesMethod($paymentMethod) && $this->pakasirService->isConfigured()) {
            try {
                return $this->activatePakasirPayment($booking, $paymentMethod);
            } catch (PakasirException $exception) {
                $this->cancelBookingBecauseGatewayFailed($booking, $exception->getMessage());

                throw ValidationException::withMessages([
                    'payment_method' => 'Gagal membuat pembayaran Pakasir. '.$exception->getMessage(),
                ]);
            }
        }

        return $booking;
    }

    public function releaseExpiredResources(?int $jadwalId = null): int
    {
        $now = now();
        $paymentGraceDeadline = $now->copy()->subMinute();
        $released = 0;

        if ($this->settingService->get('auto_cancel_booking', true)) {
            $expiredBookingIds = Pemesanan::query()
                ->when($jadwalId !== null, fn ($query) => $query->where('jadwal_id', $jadwalId))
                ->where('status_pemesanan', 'menunggu_pembayaran')
                ->whereNotNull('batas_pembayaran')
                ->where('batas_pembayaran', '<=', $paymentGraceDeadline)
                ->pluck('id');

            foreach ($expiredBookingIds as $bookingId) {
                $booking = $this->refreshGatewayBookingBeforeExpiry((int) $bookingId);
                if (! $booking || $booking->status_pemesanan !== 'menunggu_pembayaran') {
                    continue;
                }

                $released += $this->expirePendingBooking((int) $bookingId, $now);
            }
        }

        $expiredFormLockIds = KursiJadwal::query()
            ->when($jadwalId !== null, fn ($query) => $query->where('jadwal_id', $jadwalId))
            ->where('status_kursi', 'terkunci')
            ->whereNotNull('lock_expires_at')
            ->where('lock_expires_at', '<=', $now)
            ->doesntHave('detailPemesanan')
            ->pluck('id');

        if ($expiredFormLockIds->isNotEmpty()) {
            KursiJadwal::query()
                ->whereKey($expiredFormLockIds)
                ->update([
                    'status_kursi' => 'tersedia',
                    'dikunci_oleh' => null,
                    'locked_at' => null,
                    'lock_expires_at' => null,
                    'updated_at' => $now,
                ]);

            $released += $expiredFormLockIds->count();
        }

        return $released;
    }

    /**
     * Menandai pembayaran berhasil melalui fitur simulasi khusus lingkungan lokal.
     *
     * @throws ValidationException
     */
    public function simulatePaymentSuccess(Pemesanan $booking, User $user): Pemesanan
    {
        if (! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages([
                'booking' => 'Simulasi pembayaran hanya tersedia pada environment lokal/testing.',
            ]);
        }

        if ($booking->pengguna_id !== $user->id) {
            throw ValidationException::withMessages([
                'booking' => 'Booking ini bukan milik akun Anda.',
            ]);
        }

        if ($booking->status_pemesanan === 'terbayar') {
            return $this->freshBookingWithRelations($booking->id);
        }

        $booking = $this->freshBookingWithRelations($booking->id);

        if (
            $booking->pembayaran &&
            $this->pakasirService->handlesMethod($booking->pembayaran->metode_bayar) &&
            $this->pakasirService->usesRemoteSimulation()
        ) {
            $this->pakasirService->simulatePayment(
                $booking->kode_booking,
                $booking->total_harga,
            );

            return $this->refreshGatewayPaymentStatus($booking, $user, true);
        }

        return DB::transaction(function () use ($booking) {
            $lockedBooking = Pemesanan::query()
                ->lockForUpdate()
                ->with([
                    'detailPemesanan.kursiJadwal',
                    'jadwal.rute',
                    'pembayaran',
                ])
                ->findOrFail($booking->id);

            $lockedBooking->update([
                'status_pemesanan' => 'terbayar',
            ]);

            $lockedBooking->pembayaran()->update([
                'status_bayar' => 'berhasil',
                'dibayar_pada' => now(),
                'gateway_response' => 'Pembayaran berhasil melalui simulasi lokal.',
                'webhook_verified_at' => now(),
            ]);

            $this->finalizePaidBooking($lockedBooking);

            return $this->freshBookingWithRelations($lockedBooking->id);
        });
    }

    /**
     * Menyimpan hasil pembayaran tunai yang sudah dikonfirmasi admin.
     *
     * @throws ValidationException
     */
    public function confirmCashPayment(Pemesanan $booking, User $admin): Pemesanan
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages([
                'payment' => 'Hanya admin yang dapat memverifikasi pembayaran tunai.',
            ]);
        }

        return $this->confirmCashPaymentForActor($booking, $admin, 'admin_cash_confirm');
    }

    /**
     * @throws ValidationException
     */
    public function confirmCashPaymentByDriver(Pemesanan $booking, User $driver): Pemesanan
    {
        if (! $driver->isPengemudi()) {
            throw ValidationException::withMessages([
                'payment' => 'Hanya pengemudi yang dapat mengonfirmasi pembayaran tunai di manifest.',
            ]);
        }

        return $this->confirmCashPaymentForActor($booking, $driver, 'driver_cash_confirm');
    }

    /**
     * @throws ValidationException
     */
    private function confirmCashPaymentForActor(Pemesanan $booking, User $actor, string $source): Pemesanan
    {
        return DB::transaction(function () use ($booking, $actor, $source) {
            $lockedBooking = Pemesanan::query()
                ->lockForUpdate()
                ->with([
                    'pengguna',
                    'detailPemesanan.tiketElektronik',
                    'detailPemesanan.kursiJadwal',
                    'jadwal.pengemudi',
                    'jadwal.rute',
                    'jadwal.kendaraan',
                    'itemBagasi',
                    'pembayaran',
                ])
                ->findOrFail($booking->id);

            if ($source === 'driver_cash_confirm') {
                $driverProfile = $actor->pengemudiProfile()->first();

                if (! $driverProfile || (int) $lockedBooking->jadwal?->pengemudi_id !== (int) $driverProfile->id) {
                    throw ValidationException::withMessages([
                        'payment' => 'Pembayaran tunai ini bukan bagian dari jadwal pengemudi aktif.',
                    ]);
                }
            }

            $payment = $lockedBooking->pembayaran;
            if (! $payment) {
                throw ValidationException::withMessages([
                    'payment' => 'Data pembayaran lokal tidak ditemukan untuk booking ini.',
                ]);
            }

            if ($payment->metode_bayar !== 'cash') {
                throw ValidationException::withMessages([
                    'payment' => 'Route ini hanya untuk verifikasi pembayaran cash.',
                ]);
            }

            if (
                $lockedBooking->status_pemesanan !== 'menunggu_pembayaran'
                || $payment->status_bayar !== 'menunggu'
            ) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran cash sudah diproses atau booking tidak lagi menunggu pembayaran.',
                ]);
            }

            $now = now();
            $cashReference = 'CASH-'.$lockedBooking->kode_booking;
            $orderIdGateway = (
                filled($payment->order_id_gateway)
                && ! Str::startsWith((string) $payment->order_id_gateway, 'DRAFT-')
            )
                ? $payment->order_id_gateway
                : $cashReference;

            $payment->update([
                'order_id_gateway' => $orderIdGateway,
                'gateway_reference' => $cashReference.'-'.$now->format('YmdHis'),
                'paid_channel' => 'Bayar Tunai',
                'jumlah_bayar' => $lockedBooking->total_harga,
                'status_bayar' => 'berhasil',
                'gateway_response' => $this->pakasirService->mergeGatewayResponse(
                    $payment->gateway_response,
                    [
                        'cash_confirmation' => [
                            'source' => $source,
                            'confirmed_by_id' => $actor->id,
                            'confirmed_by_name' => $actor->nama,
                            'confirmed_by_role' => $actor->peran,
                            'confirmed_at' => $now->toIso8601String(),
                            'previous_booking_status' => $lockedBooking->status_pemesanan,
                            'previous_payment_status' => $payment->status_bayar,
                        ],
                    ],
                ),
                'webhook_verified_at' => $now,
                'dibayar_pada' => $now,
                'expired_at' => null,
            ]);

            $lockedBooking->update([
                'status_pemesanan' => 'terbayar',
                'batas_pembayaran' => null,
            ]);

            $this->finalizePaidBooking($lockedBooking);

            return $this->freshBookingWithRelations($lockedBooking->id);
        });
    }

    /**
     * Memeriksa ulang status transaksi Pakasir lalu menyinkronkannya ke pemesanan.
     *
     * @throws ValidationException
     */
    public function refreshGatewayPaymentStatus(
        Pemesanan $booking,
        User $user,
        bool $strictPendingState = false,
    ): Pemesanan {
        if ($booking->pengguna_id !== $user->id) {
            throw ValidationException::withMessages([
                'booking' => 'Booking ini bukan milik akun Anda.',
            ]);
        }

        return $this->refreshGatewayPaymentStatusForBooking($booking, $strictPendingState);
    }

    public function refreshGatewayPaymentStatusFromSystem(
        Pemesanan $booking,
        bool $strictPendingState = false,
    ): Pemesanan {
        return $this->refreshGatewayPaymentStatusForBooking($booking, $strictPendingState);
    }

    /**
     * Memproses notifikasi Pakasir dan mencari pemesanan yang sesuai.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function handlePakasirWebhook(array $payload): ?Pemesanan
    {
        if (! $this->pakasirService->isConfigured()) {
            throw ValidationException::withMessages([
                'project' => 'Integrasi Pakasir belum aktif pada server ini.',
            ]);
        }

        if (! $this->pakasirService->validateProject((string) $payload['project'])) {
            throw ValidationException::withMessages([
                'project' => 'Webhook project Pakasir tidak sesuai dengan konfigurasi server.',
            ]);
        }

        $booking = Pemesanan::query()
            ->with(['pembayaran'])
            ->where('kode_booking', $payload['order_id'])
            ->first();

        if (! $booking || ! $booking->pembayaran) {
            return null;
        }

        if ((int) round((float) $booking->total_harga) !== (int) round((float) $payload['amount'])) {
            throw ValidationException::withMessages([
                'amount' => 'Amount webhook Pakasir tidak cocok dengan transaksi lokal.',
            ]);
        }

        $transaction = $this->pakasirService->transactionDetail(
            $booking->kode_booking,
            $booking->total_harga,
        );

        if ($this->pakasirService->isCompletedStatus((string) data_get($transaction, 'status'))) {
            return $this->syncPakasirPayment($booking, $transaction, $payload);
        }

        if ($this->pakasirService->isExpiredStatus((string) data_get($transaction, 'status'))) {
            return $this->markGatewayPaymentUnsuccessful($booking, $transaction, $payload, 'kedaluwarsa');
        }

        if ($this->pakasirService->isFailedStatus((string) data_get($transaction, 'status'))) {
            return $this->markGatewayPaymentUnsuccessful($booking, $transaction, $payload, 'gagal');
        }

        $booking->pembayaran->update([
            'raw_webhook_payload' => $payload,
            'gateway_response' => $this->pakasirService->mergeGatewayResponse(
                $booking->pembayaran->gateway_response,
                ['transaction_detail' => $transaction],
            ),
        ]);

        return $this->freshBookingWithRelations($booking->id);
    }

    private function activatePakasirPayment(Pemesanan $booking, string $paymentMethod): Pemesanan
    {
        $qrisOnly = $paymentMethod === 'qris';
        $checkoutUrl = $this->pakasirService->checkoutUrl(
            $booking->kode_booking,
            $booking->total_harga,
            $qrisOnly,
            $this->pakasirService->merchantReturnUrl(),
        );

        $paymentPayload = [];
        if ($qrisOnly) {
            $paymentPayload = $this->pakasirService->createTransaction(
                'qris',
                $booking->kode_booking,
                $booking->total_harga,
            );
        }

        return DB::transaction(function () use ($booking, $paymentPayload, $checkoutUrl, $paymentMethod) {
            $lockedBooking = Pemesanan::query()
                ->lockForUpdate()
                ->with([
                    'pembayaran',
                    'detailPemesanan.kursiJadwal',
                    'jadwal.rute',
                    'jadwal.kendaraan',
                    'itemBagasi',
                ])
                ->findOrFail($booking->id);

            $payment = $lockedBooking->pembayaran;
            if (! $payment) {
                throw new PakasirException('Data pembayaran lokal tidak ditemukan.');
            }

            // Batas lokal dari pengaturan tetap menjadi acuan agar timer mobile dan backend sama.
            $expiredAt = $lockedBooking->batas_pembayaran
                ?? $this->resolvePaymentLockDeadline(now(), $lockedBooking->jadwal, $paymentMethod);

            $payment->update([
                'order_id_gateway' => (string) data_get($paymentPayload, 'order_id', $lockedBooking->kode_booking),
                'gateway_reference' => (string) data_get($paymentPayload, 'payment_number', ''),
                'paid_channel' => (string) data_get($paymentPayload, 'payment_method', $paymentMethod),
                'jumlah_bayar' => (float) data_get($paymentPayload, 'total_payment', $lockedBooking->total_harga),
                'status_bayar' => 'menunggu',
                'gateway_response' => $this->pakasirService->encodeGatewayResponse([
                    'provider' => 'pakasir',
                    'checkout_url' => $checkoutUrl,
                    'checkout_method' => $paymentMethod,
                    'create_payment' => $paymentPayload,
                ]),
                'expired_at' => $expiredAt,
            ]);

            $lockedBooking->update([
                'batas_pembayaran' => $expiredAt,
            ]);

            foreach ($lockedBooking->detailPemesanan as $detail) {
                $detail->kursiJadwal?->update([
                    'lock_expires_at' => $expiredAt,
                    'updated_at' => now(),
                ]);
            }

            return $this->freshBookingWithRelations($lockedBooking->id);
        });
    }

    /**
     * Mengubah status pemesanan ketika pembayaran gagal atau kedaluwarsa.
     *
     * @param  array<string, mixed>  $transaction
     * @param  array<string, mixed>|null  $webhookPayload
     */
    private function markGatewayPaymentUnsuccessful(
        Pemesanan $booking,
        array $transaction,
        ?array $webhookPayload,
        string $paymentStatus,
    ): Pemesanan {
        return DB::transaction(function () use ($booking, $transaction, $webhookPayload, $paymentStatus) {
            $lockedBooking = Pemesanan::query()
                ->lockForUpdate()
                ->with([
                    'detailPemesanan.kursiJadwal',
                    'jadwal.rute',
                    'jadwal.kendaraan',
                    'itemBagasi',
                    'pembayaran',
                ])
                ->findOrFail($booking->id);

            $payment = $lockedBooking->pembayaran;
            if (! $payment) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran lokal tidak ditemukan untuk booking ini.',
                ]);
            }

            if ($lockedBooking->status_pemesanan === 'terbayar') {
                $this->recordGatewaySnapshot($payment, [
                    'transaction_detail' => $transaction,
                    'ignored_gateway_status' => $paymentStatus,
                ]);

                return $this->freshBookingWithRelations($lockedBooking->id);
            }

            foreach ($lockedBooking->detailPemesanan as $detail) {
                $detail->kursiJadwal?->update([
                    'status_kursi' => 'tersedia',
                    'dikunci_oleh' => null,
                    'locked_at' => null,
                    'lock_expires_at' => null,
                    'updated_at' => now(),
                ]);
            }

            $lockedBooking->detailPemesanan()->delete();

            $payment->update([
                'status_bayar' => $paymentStatus,
                'gateway_response' => $this->pakasirService->mergeGatewayResponse(
                    $payment->gateway_response,
                    ['transaction_detail' => $transaction],
                ),
                'raw_webhook_payload' => $webhookPayload ?? $payment->raw_webhook_payload,
                'webhook_verified_at' => now(),
            ]);

            $lockedBooking->update([
                'status_pemesanan' => 'dibatalkan',
            ]);

            $this->adminNotification->paymentStatusChanged($lockedBooking, $paymentStatus);

            return $this->freshBookingWithRelations($lockedBooking->id);
        });
    }

    /**
     * Menyimpan pembayaran berhasil, mengaktifkan tiket, dan mengunci kursi permanen.
     *
     * @param  array<string, mixed>  $transaction
     * @param  array<string, mixed>|null  $webhookPayload
     */
    private function syncPakasirPayment(
        Pemesanan $booking,
        array $transaction,
        ?array $webhookPayload = null,
    ): Pemesanan {
        return DB::transaction(function () use ($booking, $transaction, $webhookPayload) {
            $lockedBooking = Pemesanan::query()
                ->lockForUpdate()
                ->with([
                    'detailPemesanan.tiketElektronik',
                    'detailPemesanan.kursiJadwal',
                    'jadwal.rute',
                    'jadwal.kendaraan',
                    'itemBagasi',
                    'pembayaran',
                ])
                ->findOrFail($booking->id);

            $payment = $lockedBooking->pembayaran;
            if (! $payment) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran lokal tidak ditemukan untuk booking ini.',
                ]);
            }

            $gatewayResponse = $this->pakasirService->mergeGatewayResponse(
                $payment->gateway_response,
                ['transaction_detail' => $transaction],
            );

            $payment->update([
                'paid_channel' => (string) data_get($transaction, 'payment_method', $payment->paid_channel),
                'status_bayar' => 'berhasil',
                'gateway_response' => $gatewayResponse,
                'raw_webhook_payload' => $webhookPayload ?? $payment->raw_webhook_payload,
                'webhook_verified_at' => now(),
                'dibayar_pada' => $this->resolveGatewayExpiredAt((string) data_get($transaction, 'completed_at')) ?? now(),
            ]);

            $lockedBooking->update([
                'status_pemesanan' => 'terbayar',
            ]);

            $this->finalizePaidBooking($lockedBooking);

            return $this->freshBookingWithRelations($lockedBooking->id);
        });
    }

    private function cancelBookingBecauseGatewayFailed(Pemesanan $booking, string $message): void
    {
        DB::transaction(function () use ($booking, $message) {
            $lockedBooking = Pemesanan::query()
                ->lockForUpdate()
                ->with(['detailPemesanan.kursiJadwal', 'pembayaran'])
                ->findOrFail($booking->id);

            foreach ($lockedBooking->detailPemesanan as $detail) {
                $detail->kursiJadwal?->update([
                    'status_kursi' => 'tersedia',
                    'dikunci_oleh' => null,
                    'locked_at' => null,
                    'lock_expires_at' => null,
                    'updated_at' => now(),
                ]);
            }

            $lockedBooking->detailPemesanan()->delete();

            $lockedBooking->update([
                'status_pemesanan' => 'dibatalkan',
            ]);

            if ($lockedBooking->pembayaran) {
                $lockedBooking->pembayaran->update([
                    'status_bayar' => 'gagal',
                    'gateway_response' => $this->pakasirService->mergeGatewayResponse(
                        $lockedBooking->pembayaran->gateway_response,
                        ['gateway_error' => $message],
                    ),
                ]);
            }

            $this->adminNotification->paymentStatusChanged($lockedBooking, 'gagal');
        });
    }

    private function finalizePaidBooking(Pemesanan $booking): void
    {
        foreach ($booking->detailPemesanan as $detail) {
            $detail->kursiJadwal?->update([
                'status_kursi' => 'dipesan',
                'dikunci_oleh' => null,
                'locked_at' => null,
                'lock_expires_at' => null,
                'updated_at' => now(),
            ]);

            TiketElektronik::query()->updateOrCreate(
                ['detail_pemesanan_id' => $detail->id],
                [
                    'kode_qr' => 'TRAVS-'.$booking->kode_booking.'-'.$detail->nomor_kursi,
                    'token_unik_qr' => $detail->tiketElektronik?->token_unik_qr ?: Str::uuid()->toString(),
                    'diterbitkan_pada' => $detail->tiketElektronik?->diterbitkan_pada ?: now(),
                ],
            );
        }

        $this->createPaymentSuccessNotification($booking);
        $this->adminNotification->paymentStatusChanged($booking, 'berhasil');
    }

    private function createPaymentSuccessNotification(Pemesanan $booking): void
    {
        $booking->loadMissing(['jadwal.rute']);

        $routeName = $booking->jadwal?->rute
            ? $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan
            : 'perjalanan Anda';

        $message = $this->notificationTemplate->render(
            'Pembayaran Anda untuk rute {rute} telah berhasil dikonfirmasi.',
            [
                'nama' => $booking->pengguna?->nama ?? 'Penumpang',
                'rute' => $routeName,
                'kode_booking' => $booking->kode_booking,
            ],
        );

        Notifikasi::query()->updateOrCreate(
            [
                'pengguna_id' => $booking->pengguna_id,
                'jenis' => 'pembayaran',
                'referensi_id' => $booking->id,
                'judul' => 'Pembayaran berhasil dikonfirmasi',
            ],
            [
                'pesan' => trim($message.' Tiket elektronik untuk booking '.$booking->kode_booking.' sudah aktif.'),
                'sudah_dibaca' => false,
            ],
        );
    }

    private function recordGatewaySnapshot(Pembayaran $payment, array $payload): void
    {
        $payment->update([
            'gateway_response' => $this->pakasirService->mergeGatewayResponse(
                $payment->gateway_response,
                $payload,
            ),
        ]);
    }

    private function refreshGatewayPaymentStatusForBooking(
        Pemesanan $booking,
        bool $strictPendingState = false,
    ): Pemesanan {
        $booking = $this->freshBookingWithRelations($booking->id);
        $payment = $booking->pembayaran;

        if (! $payment || ! $this->pakasirService->handlesMethod($payment->metode_bayar) || ! $this->pakasirService->isConfigured()) {
            return $booking;
        }

        $transaction = $this->pakasirService->transactionDetail(
            $booking->kode_booking,
            $booking->total_harga,
        );

        if ($this->pakasirService->isCompletedStatus((string) data_get($transaction, 'status'))) {
            return $this->syncPakasirPayment($booking, $transaction);
        }

        if ($this->pakasirService->isExpiredStatus((string) data_get($transaction, 'status'))) {
            return $this->markGatewayPaymentUnsuccessful($booking, $transaction, null, 'kedaluwarsa');
        }

        if ($this->pakasirService->isFailedStatus((string) data_get($transaction, 'status'))) {
            return $this->markGatewayPaymentUnsuccessful($booking, $transaction, null, 'gagal');
        }

        $this->recordGatewaySnapshot($payment, [
            'transaction_detail' => $transaction,
        ]);

        if ($strictPendingState) {
            throw ValidationException::withMessages([
                'booking' => 'Simulasi pembayaran belum menghasilkan status completed dari Pakasir.',
            ]);
        }

        return $this->freshBookingWithRelations($booking->id);
    }

    private function refreshGatewayBookingBeforeExpiry(int $bookingId): ?Pemesanan
    {
        $booking = $this->freshBookingWithRelations($bookingId);
        $payment = $booking->pembayaran;

        if ($booking->status_pemesanan !== 'menunggu_pembayaran') {
            return $booking;
        }

        if (! $payment || ! $this->pakasirService->handlesMethod($payment->metode_bayar) || ! $this->pakasirService->isConfigured()) {
            return $booking;
        }

        try {
            // Gateway dicek lebih dulu supaya transaksi yang sudah dibayar
            // tidak keliru dibatalkan hanya karena webhook/return terlambat.
            return $this->refreshGatewayPaymentStatusFromSystem($booking);
        } catch (Throwable) {
            return null;
        }
    }

    private function freshBookingWithRelations(int $bookingId): Pemesanan
    {
        return Pemesanan::query()->with([
            'pengguna',
            'jadwal.rute',
            'jadwal.kendaraan',
            'jadwal.pengemudi.pengguna',
            'detailPemesanan.tiketElektronik',
            'detailPemesanan.kursiJadwal',
            'itemBagasi',
            'pembayaran',
        ])->findOrFail($bookingId);
    }

    private function resolveGatewayExpiredAt(?string $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function expirePendingBooking(int $bookingId, $now): int
    {
        return DB::transaction(function () use ($bookingId, $now) {
            $booking = Pemesanan::query()
                ->lockForUpdate()
                ->with(['detailPemesanan', 'pembayaran'])
                ->find($bookingId);

            if (! $booking || $booking->status_pemesanan !== 'menunggu_pembayaran') {
                return 0;
            }

            if (! $booking->batas_pembayaran || $booking->batas_pembayaran->isFuture()) {
                return 0;
            }

            $seatIds = $booking->detailPemesanan
                ->pluck('kursi_jadwal_id')
                ->filter()
                ->values();

            if ($seatIds->isNotEmpty()) {
                KursiJadwal::query()
                    ->whereKey($seatIds)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (KursiJadwal $seat) use ($now) {
                        $seat->update([
                            'status_kursi' => 'tersedia',
                            'dikunci_oleh' => null,
                            'locked_at' => null,
                            'lock_expires_at' => null,
                            'updated_at' => $now,
                        ]);
                    });
            }

            $booking->detailPemesanan()->delete();

            $booking->update([
                'status_pemesanan' => 'dibatalkan',
            ]);

            if ($booking->pembayaran && $booking->pembayaran->status_bayar === 'menunggu') {
                $booking->pembayaran->update([
                    'status_bayar' => 'kedaluwarsa',
                ]);
            }

            $this->adminNotification->paymentStatusChanged($booking, 'kedaluwarsa');

            return $seatIds->count();
        });
    }

    /**
     * Memastikan data setiap penumpang cocok dengan kursi yang dipilih.
     *
     * @param  Collection<int, array<string, mixed>>  $passengers
     * @param  Collection<int, int>  $seatNumbers
     *
     * @throws ValidationException
     */
    private function ensurePassengersMatchSeats(Collection $passengers, Collection $seatNumbers): void
    {
        if ($passengers->count() !== $seatNumbers->count()) {
            throw ValidationException::withMessages([
                'passengers' => 'Jumlah data penumpang harus sama dengan jumlah kursi yang dipilih.',
            ]);
        }

        $passengerSeatNumbers = $passengers
            ->pluck('seat_number')
            ->map(fn ($seat) => (int) $seat)
            ->sort()
            ->values();

        if ($passengerSeatNumbers->all() !== $seatNumbers->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'passengers' => 'Data penumpang harus sesuai dengan nomor kursi yang dipilih.',
            ]);
        }
    }

    /**
     * Memastikan metode pembayaran yang dipilih sedang diaktifkan oleh admin.
     *
     * @throws ValidationException
     */
    private function ensurePaymentMethodEnabled(string $paymentMethod): void
    {
        $isEnabled = match ($paymentMethod) {
            'transfer' => $this->settingService->get('payment_transfer_enabled', false),
            'qris' => $this->settingService->get('payment_qris_enabled', false),
            'cash' => $this->settingService->get('payment_cash_enabled', false),
            default => false,
        };

        if (! $isEnabled) {
            throw ValidationException::withMessages([
                'payment_method' => $paymentMethod === 'cash'
                    ? 'Pembayaran tunai sedang tidak tersedia. Silakan pilih metode lain.'
                    : 'Metode pembayaran yang dipilih sedang tidak aktif.',
            ]);
        }
    }

    /**
     * Memastikan jumlah dan nomor kursi yang dipilih memenuhi aturan pemesanan.
     *
     * @param  Collection<int, int>  $seatNumbers
     *
     * @throws ValidationException
     */
    private function ensureSeatSelectionValid(Collection $seatNumbers): void
    {
        if ($seatNumbers->isEmpty()) {
            throw ValidationException::withMessages([
                'seat_numbers' => 'Minimal satu kursi harus dipilih.',
            ]);
        }

        if ($seatNumbers->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'seat_numbers' => 'Nomor kursi tidak boleh duplikat.',
            ]);
        }

        $maxSeatPerBooking = (int) $this->settingService->get('max_seat_per_booking', 4);
        if ($seatNumbers->count() > $maxSeatPerBooking) {
            throw ValidationException::withMessages([
                'seat_numbers' => 'Jumlah kursi melebihi batas maksimal per booking.',
            ]);
        }
    }

    /**
     * Memastikan semua kursi yang diminta benar-benar ada pada jadwal tersebut.
     *
     * @param  Collection<int, KursiJadwal>  $selectedSeats
     * @param  Collection<int, int>  $seatNumbers
     *
     * @throws ValidationException
     */
    private function ensureSeatRowsMatchSelection(Collection $selectedSeats, Collection $seatNumbers): void
    {
        if ($selectedSeats->count() !== $seatNumbers->count()) {
            throw ValidationException::withMessages([
                'seat_numbers' => 'Satu atau lebih kursi tidak ditemukan pada jadwal ini.',
            ]);
        }
    }

    /**
     * Memastikan kursi belum dipesan atau dikunci oleh pengguna lain.
     *
     * @param  Collection<int, KursiJadwal>  $selectedSeats
     *
     * @throws ValidationException
     */
    private function ensureSeatsCanBeUsedByUser(Collection $selectedSeats, int $userId): void
    {
        foreach ($selectedSeats as $seat) {
            if ($seat->status_kursi === 'dipesan') {
                throw ValidationException::withMessages([
                    'seat_numbers' => "Kursi {$seat->nomor_kursi} tidak tersedia.",
                ]);
            }

            if ($seat->status_kursi === 'terkunci' && (int) $seat->dikunci_oleh !== $userId) {
                throw ValidationException::withMessages([
                    'seat_numbers' => "Kursi {$seat->nomor_kursi} sedang dikunci pengguna lain.",
                ]);
            }

            $hasBooking = DetailPemesanan::query()
                ->where('kursi_jadwal_id', $seat->id)
                ->exists();

            if ($hasBooking) {
                throw ValidationException::withMessages([
                    'seat_numbers' => "Kursi {$seat->nomor_kursi} sudah dipesan dalam booking lain.",
                ]);
            }
        }
    }

    /**
     * Memastikan jadwal masih dibuka sebelum proses pemesanan dilanjutkan.
     *
     * @throws ValidationException
     */
    private function ensureScheduleOpen(Jadwal $schedule): void
    {
        if ($schedule->status !== 'dibuka') {
            throw ValidationException::withMessages([
                'jadwal_id' => 'Jadwal yang dipilih sudah ditutup atau belum dibuka untuk pemesanan.',
            ]);
        }
    }

    /**
     * Mengambil baris kursi sekaligus menguncinya selama transaksi database berjalan.
     *
     * @param  Collection<int, int>  $seatNumbers
     * @return Collection<int, KursiJadwal>
     */
    private function loadSeatRowsForUpdate(int $scheduleId, Collection $seatNumbers): Collection
    {
        return KursiJadwal::query()
            ->with('detailPemesanan')
            ->where('jadwal_id', $scheduleId)
            ->whereIn('nomor_kursi', $seatNumbers)
            ->lockForUpdate()
            ->get()
            ->sortBy('nomor_kursi')
            ->values();
    }

    /**
     * Membersihkan dan mengubah daftar nomor kursi menjadi kumpulan angka positif.
     *
     * @param  array<int, mixed>  $seatNumbers
     * @return Collection<int, int>
     */
    private function normalizeSeatNumbers(array $seatNumbers): Collection
    {
        return collect($seatNumbers)
            ->map(fn ($seat) => (int) $seat)
            ->filter(fn ($seat) => $seat > 0)
            ->values();
    }

    /**
     * Data lama hanya menyimpan satu foto. Data baru menyimpan daftar foto,
     * tetapi tetap dibatasi tiga agar bukti jelas tanpa memberatkan storage.
     */
    private function normalizeBaggagePhotoUrls(array $item): array
    {
        $urls = collect(data_get($item, 'foto_urls', []))
            ->filter(fn ($url) => is_string($url) && trim($url) !== '')
            ->map(fn ($url) => trim($url))
            ->values();

        $singleUrl = data_get($item, 'foto_url');
        if ($urls->isEmpty() && is_string($singleUrl) && trim($singleUrl) !== '') {
            $urls->push(trim($singleUrl));
        }

        return $urls->take(3)->values()->all();
    }

    private function resolveFormLockDeadline($now)
    {
        return $now->copy()->addMinutes(
            (int) $this->settingService->get('seat_lock_form_minutes', 30),
        );
    }

    private function resolvePaymentLockDeadline($now, ?Jadwal $schedule = null, ?string $paymentMethod = null)
    {
        if ($paymentMethod === 'cash') {
            return null;
        }

        return $now->copy()->addMinutes(
            (int) $this->settingService->get('seat_lock_payment_minutes', 5),
        );
    }

    private function generateBookingCode(): string
    {
        do {
            $code = sprintf(
                'TRAVS-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(5)),
            );
        } while (Pemesanan::query()->where('kode_booking', $code)->exists());

        return $code;
    }
}
