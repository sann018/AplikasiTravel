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
use App\Support\PaymentMethodCatalog;
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
     * Melepas kunci kursi tahap pengisian form yang belum menjadi pemesanan.
     *
     * @param  array<string, mixed>  $payload
     */
    public function releaseFormLocks(User $user, array $payload): int
    {
        $scheduleId = (int) $payload['jadwal_id'];
        $seatNumbers = $this->normalizeSeatNumbers($payload['seat_numbers'] ?? []);
        $this->ensureSeatSelectionValid($seatNumbers);

        return DB::transaction(function () use ($user, $scheduleId, $seatNumbers) {
            $lockedSeats = KursiJadwal::query()
                ->where('jadwal_id', $scheduleId)
                ->whereIn('nomor_kursi', $seatNumbers->all())
                ->where('status_kursi', 'terkunci')
                ->where('dikunci_oleh', $user->id)
                ->whereDoesntHave('detailPemesanan')
                ->lockForUpdate()
                ->get();

            // Update per model diperlukan agar observer mengirim perubahan ke perangkat lain.
            $lockedSeats->each(fn (KursiJadwal $seat) => $seat->update([
                'status_kursi' => 'tersedia',
                'dikunci_oleh' => null,
                'locked_at' => null,
                'lock_expires_at' => null,
                'updated_at' => now(),
            ]));

            return $lockedSeats->count();
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
        return $this->createDraftForActor(
            actor: $user,
            passengerAccount: $user,
            payload: $payload,
            source: 'aplikasi_penumpang',
        );
    }

    /** Membuat pemesanan loket dengan akun penumpang yang bersifat opsional. */
    public function createCounterDraft(User $counterStaff, ?User $passengerAccount, array $payload): Pemesanan
    {
        if (! $counterStaff->isPetugasLoket()) {
            throw ValidationException::withMessages([
                'booking' => 'Hanya Petugas Loket yang dapat membuat pemesanan loket.',
            ]);
        }

        if ($passengerAccount !== null && ! $passengerAccount->isPenumpang()) {
            throw ValidationException::withMessages([
                'passenger_account_id' => 'Akun yang dipilih bukan akun penumpang.',
            ]);
        }

        return $this->createDraftForActor(
            actor: $counterStaff,
            passengerAccount: $passengerAccount,
            payload: $payload,
            source: 'loket',
        );
    }

    /**
     * Jalur inti pemesanan untuk penumpang dan Petugas Loket.
     *
     * @param  array<string, mixed>  $payload
     */
    private function createDraftForActor(
        User $actor,
        ?User $passengerAccount,
        array $payload,
        string $source,
    ): Pemesanan
    {
        $scheduleId = (int) $payload['jadwal_id'];
        $seatNumbers = $this->normalizeSeatNumbers($payload['seat_numbers'] ?? []);
        $this->ensureSeatSelectionValid($seatNumbers);

        $passengers = collect($payload['passengers'] ?? []);
        $this->ensurePassengersMatchSeats($passengers, $seatNumbers);

        $this->releaseExpiredResources($scheduleId);

        $booking = DB::transaction(function () use (
            $actor,
            $passengerAccount,
            $payload,
            $source,
            $scheduleId,
            $seatNumbers,
            $passengers,
        ) {
            $schedule = Jadwal::query()
                ->with(['rute', 'kendaraan'])
                ->lockForUpdate()
                ->findOrFail($scheduleId);

            $this->scheduleLifecycleService->closeSingleIfPastBookingWindow($schedule);
            $this->ensureScheduleOpen($schedule);

            $selectedSeats = $this->loadSeatRowsForUpdate($schedule->id, $seatNumbers);
            $this->ensureSeatRowsMatchSelection($selectedSeats, $seatNumbers);
            $this->ensureSeatsCanBeUsedByUser($selectedSeats, $actor->id);

            $now = now();
            $this->ensureActiveFormLocksOwnedByUser($selectedSeats, $actor->id, $now);

            $totals = $this->calculator->calculate(
                $schedule,
                $seatNumbers->count(),
            );

            $paymentDeadline = $this->resolvePaymentLockDeadline($now, $schedule);
            $formDeadline = $selectedSeats
                ->pluck('lock_expires_at')
                ->filter()
                ->sort()
                ->first();

            $booking = Pemesanan::query()->create([
                'pengguna_id' => $passengerAccount?->id,
                'dibuat_oleh' => $source === 'loket' ? $actor->id : null,
                'sumber_pemesanan' => $source,
                'nama_pemesan' => data_get($payload, 'customer.name', $passengerAccount?->nama),
                'no_hp_pemesan' => data_get($payload, 'customer.phone', $passengerAccount?->no_hp),
                'email_pemesan' => data_get($payload, 'customer.email', $passengerAccount?->email),
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
                    'dikunci_oleh' => $actor->id,
                    'locked_at' => $now,
                    'lock_expires_at' => $paymentDeadline,
                    'updated_at' => $now,
                ]);
            }

            foreach (($payload['baggage_items'] ?? []) as $index => $item) {
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
                    'posisi_barang' => $this->normalizeBaggagePlacement(data_get($item, 'posisi_barang')),
                    'kategori_ukuran' => $this->normalizeBaggageSizeCategory(data_get($item, 'kategori_ukuran')),
                    'jenis_barang_besar' => $this->normalizeLargeBaggageType(data_get($item, 'jenis_barang_besar')),
                    'berat_kg' => data_get($item, 'berat_kg'),
                    'ukuran_cm' => $ukuranCm,
                    'panjang_cm' => $panjangCm,
                    'lebar_cm' => $lebarCm,
                    'jumlah_item' => max(1, (int) data_get($item, 'jumlah_item', 1)),
                    'foto_url' => $fotoUrls[0] ?? null,
                    'foto_urls' => $fotoUrls,
                    // Barang bawaan biasa dicatat untuk operasional tanpa biaya otomatis.
                    'biaya_item' => 0,
                ]);
            }

            return $booking->fresh([
                'jadwal.rute',
                'jadwal.kendaraan',
                'detailPemesanan.kursiJadwal',
                'itemBagasi',
            ]);
        });

        $this->adminNotification->bookingCreated($booking);
        $this->adminNotification->capacityAlmostFull($booking->jadwal);

        return $booking;
    }

    public function releaseExpiredResources(?int $jadwalId = null): int
    {
        $now = now();
        $released = 0;

        if ($this->settingService->get('auto_cancel_booking', true)) {
            $expiredBookingIds = Pemesanan::query()
                ->when($jadwalId !== null, fn ($query) => $query->where('jadwal_id', $jadwalId))
                ->where('status_pemesanan', 'menunggu_pembayaran')
                ->whereNotNull('batas_pembayaran')
                ->where('batas_pembayaran', '<=', $now)
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
                ->get()
                ->each(fn (KursiJadwal $seat) => $seat->update([
                    'status_kursi' => 'tersedia',
                    'dikunci_oleh' => null,
                    'locked_at' => null,
                    'lock_expires_at' => null,
                    'updated_at' => $now,
                ]));

            $released += $expiredFormLockIds->count();
        }

        return $released;
    }

    /** Memilih metode lalu membuat pembayaran; booking sudah dibuat sebelumnya. */
    public function choosePaymentMethod(Pemesanan $booking, User $user, string $paymentMethod): Pemesanan
    {
        $this->ensureUserCanAccessBooking($booking, $user);

        $this->ensurePaymentMethodEnabled($paymentMethod);

        $prepared = DB::transaction(function () use ($booking, $paymentMethod) {
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

            if ($lockedBooking->status_pemesanan !== 'menunggu_pembayaran') {
                throw ValidationException::withMessages([
                    'payment_method' => 'Pemesanan tidak lagi menunggu pembayaran.',
                ]);
            }

            if ($lockedBooking->pembayaran) {
                if ($lockedBooking->pembayaran->metode_bayar !== $paymentMethod) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Metode pembayaran sudah dipilih dan tidak dapat diganti.',
                    ]);
                }

                $gatewayResponse = $this->pakasirService->decodeGatewayResponse(
                    $lockedBooking->pembayaran->gateway_response,
                );
                $needsGatewayRecovery = $this->pakasirService->handlesMethod($paymentMethod)
                    && $lockedBooking->pembayaran->status_bayar === 'menunggu'
                    && ! is_array(data_get($gatewayResponse, 'create_payment'));

                return [
                    'booking' => $lockedBooking,
                    'payment_created' => false,
                    // Request berikutnya hanya mengecek transaksi pertama agar invoice tidak ganda.
                    'needs_gateway_recovery' => $needsGatewayRecovery,
                ];
            }

            if ($lockedBooking->batas_pembayaran === null || $lockedBooking->batas_pembayaran->isPast()) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Waktu memilih metode pembayaran telah berakhir.',
                ]);
            }

            Pembayaran::query()->create([
                'pemesanan_id' => $lockedBooking->id,
                // Tunai tidak mempunyai order ID provider; kode hanya dibuat untuk QRIS/VA.
                'order_id_gateway' => $paymentMethod === PaymentMethodCatalog::CASH
                    ? null
                    : $this->generatePaymentTransactionCode(),
                'metode_bayar' => $paymentMethod,
                'nominal_transaksi' => $this->rupiah($lockedBooking->total_harga),
                'biaya_admin_provider' => $paymentMethod === PaymentMethodCatalog::CASH ? 0 : null,
                'total_dibayar_penumpang' => $paymentMethod === PaymentMethodCatalog::CASH
                    ? $this->rupiah($lockedBooking->total_harga)
                    : null,
                'payment_number' => null,
                'jumlah_bayar' => $this->rupiah($lockedBooking->total_harga),
                'status_bayar' => 'menunggu',
                'gateway_response' => $paymentMethod === PaymentMethodCatalog::CASH
                    ? 'Metode pembayaran tunai dipilih, menunggu konfirmasi.'
                    : $this->pakasirService->encodeGatewayResponse([
                        'provider' => 'pakasir',
                        'integration_mode' => 'direct_api',
                        // Penanda ini mencegah dua request membuat invoice berbeda.
                        'activation_status' => 'creating',
                        'activation_started_at' => now()->toIso8601String(),
                    ]),
                // Masa berlaku provider baru diisi dari response QRIS/VA.
                'expired_at' => null,
            ]);

            if ($paymentMethod === PaymentMethodCatalog::CASH) {
                // Setelah memilih tunai, kursi ditahan sampai pembayaran dikonfirmasi pengemudi.
                $lockedBooking->update(['batas_pembayaran' => null]);
                $lockedBooking->detailPemesanan
                    ->pluck('kursiJadwal')
                    ->filter()
                    ->each(fn (KursiJadwal $seat) => $seat->update([
                        'lock_expires_at' => null,
                        'updated_at' => now(),
                    ]));
            }

            return [
                'booking' => $this->freshBookingWithRelations($lockedBooking->id),
                'payment_created' => true,
                'needs_gateway_recovery' => false,
            ];
        });

        /** @var Pemesanan $preparedBooking */
        $preparedBooking = $prepared['booking'];
        if (! $prepared['payment_created']) {
            if ($prepared['needs_gateway_recovery']) {
                return $this->recoverUncertainPakasirActivation($preparedBooking, $paymentMethod);
            }

            return $this->freshBookingWithRelations($preparedBooking->id);
        }

        if ($paymentMethod === PaymentMethodCatalog::CASH) {
            $this->adminNotification->cashPaymentSelected($preparedBooking);

            return $this->freshBookingWithRelations($preparedBooking->id);
        }

        if (! $this->pakasirService->isConfigured()) {
            $this->cancelBookingBecauseGatewayFailed($preparedBooking, 'Integrasi Pakasir belum dikonfigurasi.');

            throw ValidationException::withMessages([
                'payment_method' => 'Pembayaran digital sedang tidak tersedia. Silakan ulangi pemesanan.',
            ]);
        }

        try {
            return $this->activatePakasirPayment($preparedBooking, $paymentMethod);
        } catch (PakasirUncertainException $exception) {
            return $this->recoverUncertainPakasirActivation(
                $preparedBooking,
                $paymentMethod,
                $exception,
            );
        } catch (PakasirException $exception) {
            $this->cancelBookingBecauseGatewayFailed($preparedBooking, $exception->getMessage());

            throw ValidationException::withMessages([
                'payment_method' => 'Pembayaran melalui Pakasir belum dapat dibuat. Silakan ulangi pemesanan.',
            ]);
        }
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
                'booking' => 'Simulasi pembayaran hanya tersedia pada lingkungan lokal atau pengujian.',
            ]);
        }

        $this->ensureUserCanAccessBooking($booking, $user);

        if (! $booking->pembayaran()->exists()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Pilih metode pembayaran terlebih dahulu.',
            ]);
        }

        if ($booking->status_pemesanan === 'terbayar') {
            return $this->freshBookingWithRelations($booking->id);
        }

        $this->releaseExpiredResources($booking->jadwal_id);
        $booking = $this->freshBookingWithRelations($booking->id);

        if ($booking->status_pemesanan !== 'menunggu_pembayaran') {
            throw ValidationException::withMessages([
                'booking' => 'Sesi pembayaran telah berakhir. Pemesanan ini sudah dibatalkan.',
            ]);
        }

        if (
            $booking->pembayaran &&
            $this->pakasirService->handlesMethod($booking->pembayaran->metode_bayar) &&
            $this->pakasirService->usesRemoteSimulation()
        ) {
            $this->pakasirService->simulatePayment(
                $this->paymentOrderId($booking),
                $booking->pembayaran->nominal_transaksi
                    ?? $this->rupiah($booking->total_harga),
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
     * Menyimpan hasil pembayaran tunai dari Petugas Loket atau Admin Web.
     *
     * @throws ValidationException
     */
    public function confirmCashPayment(Pemesanan $booking, User $actor): Pemesanan
    {
        if (! $actor->isPetugasLoket() && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'payment' => 'Hanya Petugas Loket atau Admin yang dapat memverifikasi pembayaran tunai.',
            ]);
        }

        $source = $actor->isPetugasLoket()
            ? 'counter_staff_cash_confirm'
            : 'admin_cash_confirm';

        return $this->confirmCashPaymentForActor($booking, $actor, $source);
    }

    /**
     * @throws ValidationException
     */
    public function confirmCashPaymentByDriver(Pemesanan $booking, User $driver): Pemesanan
    {
        if (! $driver->isPengemudi()) {
            throw ValidationException::withMessages([
                'payment' => 'Hanya pengemudi yang dapat mengonfirmasi pembayaran tunai di manifes.',
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
                    'payment' => 'Data pembayaran tidak ditemukan untuk pemesanan ini.',
                ]);
            }

            if ($payment->metode_bayar !== 'cash') {
                throw ValidationException::withMessages([
                    'payment' => 'Proses ini hanya digunakan untuk konfirmasi pembayaran tunai.',
                ]);
            }

            if (
                in_array($lockedBooking->status_pemesanan, ['terbayar', 'selesai'], true)
                && $payment->status_bayar === 'berhasil'
            ) {
                // Klik ulang tetap aman dan tidak menerbitkan tiket kedua.
                return $this->freshBookingWithRelations($lockedBooking->id);
            }

            if (
                $lockedBooking->status_pemesanan !== 'menunggu_pembayaran'
                || $payment->status_bayar !== 'menunggu'
            ) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran tunai sudah diproses atau pemesanan tidak lagi menunggu pembayaran.',
                ]);
            }

            $now = now();
            $cashReference = 'CASH-'.$lockedBooking->kode_booking;

            $payment->update([
                // Pembayaran tunai tidak disambungkan dengan transaksi provider.
                'order_id_gateway' => null,
                'gateway_reference' => $cashReference.'-'.$now->format('YmdHis'),
                'paid_channel' => 'Bayar Tunai',
                'nominal_transaksi' => $this->rupiah($lockedBooking->total_harga),
                'biaya_admin_provider' => 0,
                'total_dibayar_penumpang' => $this->rupiah($lockedBooking->total_harga),
                'payment_number' => null,
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
                'webhook_verified_at' => null,
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
        $this->ensureUserCanAccessBooking($booking, $user);

        if (! $booking->pembayaran()->exists()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Pilih metode pembayaran terlebih dahulu.',
            ]);
        }

        $this->releaseExpiredResources($booking->jadwal_id);
        $booking = $this->freshBookingWithRelations($booking->id);

        if ($booking->status_pemesanan !== 'menunggu_pembayaran') {
            return $booking;
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
                'project' => 'Layanan pembayaran Pakasir belum aktif.',
            ]);
        }

        if (! $this->pakasirService->validateProject((string) $payload['project'])) {
            throw ValidationException::withMessages([
                'project' => 'Identitas layanan Pakasir tidak sesuai dengan konfigurasi pembayaran.',
            ]);
        }

        // Cari lewat order ID; kode booking hanya dipakai untuk transaksi lama.
        $payment = Pembayaran::query()
            ->with('pemesanan.pembayaran')
            ->where('order_id_gateway', $payload['order_id'])
            ->first();
        $booking = $payment?->pemesanan;

        if (! $booking) {
            $booking = Pemesanan::query()
                ->with(['pembayaran'])
                ->where('kode_booking', $payload['order_id'])
                ->first();
        }

        if (! $booking || ! $booking->pembayaran) {
            return null;
        }

        if (! $this->pakasirService->handlesMethod($booking->pembayaran->metode_bayar)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Webhook Pakasir hanya berlaku untuk pembayaran digital.',
            ]);
        }

        if (! PaymentMethodCatalog::gatewayChannelMatches(
            $booking->pembayaran->metode_bayar,
            (string) $payload['payment_method'],
        )) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran webhook tidak sesuai dengan pembayaran lokal.',
            ]);
        }

        $bookingAmount = $booking->pembayaran?->nominal_transaksi
            ?? $this->rupiah($booking->total_harga);
        if ($bookingAmount !== (int) $payload['amount']) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal pembayaran dari Pakasir tidak sesuai dengan total pemesanan.',
            ]);
        }

        $transaction = $this->pakasirService->transactionDetail(
            $this->paymentOrderId($booking),
            $bookingAmount,
        );
        $this->ensureGatewayTransactionMatches($booking, $transaction);

        $webhookStatus = (string) $payload['status'];
        $transactionStatus = (string) data_get($transaction, 'status');

        // Tiket dibuat jika webhook dan hasil pengecekan sama-sama completed.
        if (
            $this->pakasirService->isCompletedStatus($webhookStatus)
            && $this->pakasirService->isCompletedStatus($transactionStatus)
        ) {
            return $this->syncPakasirPayment($booking, $transaction, $payload);
        }

        if (
            $this->pakasirService->isCompletedStatus($webhookStatus)
            !== $this->pakasirService->isCompletedStatus($transactionStatus)
        ) {
            throw ValidationException::withMessages([
                'status' => 'Status webhook tidak sesuai dengan hasil verifikasi transaksi Pakasir.',
            ]);
        }

        if ($this->pakasirService->isExpiredStatus($transactionStatus)) {
            return $this->markGatewayPaymentUnsuccessful($booking, $transaction, $payload, 'kedaluwarsa');
        }

        if ($this->pakasirService->isFailedStatus($transactionStatus)) {
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

    /** Membuat transaksi Pakasir lalu menyimpan nominal, fee, total, QR atau VA. */
    private function activatePakasirPayment(Pemesanan $booking, string $paymentMethod): Pemesanan
    {
        if (! $this->pakasirService->canCreateTransaction($paymentMethod)) {
            throw new PakasirException("Metode pembayaran Pakasir {$paymentMethod} tidak dikenali.");
        }

        $booking->loadMissing('pembayaran');
        $nominalTransaction = $booking->pembayaran?->nominal_transaksi
            ?? $this->rupiah($booking->total_harga);
        $gatewayOrderId = $this->paymentOrderId($booking);

        $paymentPayload = $this->pakasirService->createTransaction(
            $paymentMethod,
            $gatewayOrderId,
            $nominalTransaction,
        );
        $this->ensureGatewayCreationMatches(
            $paymentMethod,
            $gatewayOrderId,
            $nominalTransaction,
            $paymentPayload,
        );

        $providerAmount = $this->providerRupiah($paymentPayload, 'amount', false);
        $providerFee = $this->providerRupiah($paymentPayload, 'fee', true);
        $this->providerRupiah($paymentPayload, 'total_payment', false);
        // Total pelanggan memakai nominal ditambah fee asli dari Pakasir.
        $customerTotal = $providerAmount + $providerFee;
        $paymentNumber = trim((string) data_get($paymentPayload, 'payment_number', ''));
        $providerExpiredAt = $this->resolveGatewayExpiredAt((string) data_get($paymentPayload, 'expired_at'));
        $checkoutUrl = $this->pakasirService->checkoutUrl(
            $gatewayOrderId,
            $providerAmount,
            $paymentMethod === PaymentMethodCatalog::QRIS,
            $this->pakasirService->merchantReturnUrl(),
            $paymentMethod,
        );

        return DB::transaction(function () use ($booking, $paymentPayload, $paymentMethod, $gatewayOrderId, $providerAmount, $providerFee, $customerTotal, $paymentNumber, $providerExpiredAt, $checkoutUrl) {
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

            $adminDeadline = $lockedBooking->batas_pembayaran
                ?? $this->resolvePaymentLockDeadline(now(), $lockedBooking->jadwal, $paymentMethod);

            // Deadline aplikasi tidak boleh diperpanjang oleh masa berlaku Pakasir.
            // Jika provider berakhir lebih cepat, gunakan batas provider agar pembayaran tetap valid.
            $expiredAt = $providerExpiredAt !== null && $providerExpiredAt->lessThan($adminDeadline)
                ? $providerExpiredAt
                : $adminDeadline;

            $payment->update([
                'order_id_gateway' => (string) data_get($paymentPayload, 'order_id', $gatewayOrderId),
                'gateway_reference' => (string) data_get($paymentPayload, 'payment_number', ''),
                'paid_channel' => (string) data_get($paymentPayload, 'payment_method', $paymentMethod),
                'nominal_transaksi' => $providerAmount,
                'biaya_admin_provider' => $providerFee,
                'total_dibayar_penumpang' => $customerTotal,
                'payment_number' => $paymentNumber !== '' ? $paymentNumber : null,
                // jumlah_bayar disandingkan dengan total yang ditagihkan ke penumpang.
                'jumlah_bayar' => $customerTotal,
                'status_bayar' => 'menunggu',
                'gateway_response' => $this->pakasirService->encodeGatewayResponse([
                    'provider' => 'pakasir',
                    'integration_mode' => 'direct_api',
                    'checkout_url' => $checkoutUrl,
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

    /** Mengecek hasil request yang terputus tanpa membuat transaksi Pakasir kedua. */
    private function recoverUncertainPakasirActivation(
        Pemesanan $booking,
        string $paymentMethod,
        ?PakasirUncertainException $cause = null,
    ): Pemesanan {
        $booking = $this->freshBookingWithRelations($booking->id);
        $payment = $booking->pembayaran;

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment_method' => 'Data pembayaran tidak ditemukan untuk proses rekonsiliasi.',
            ]);
        }

        if ($cause) {
            $this->recordGatewaySnapshot($payment, [
                'activation_status' => 'verification_pending',
                'activation_error' => $cause->getMessage(),
                'activation_checked_at' => now()->toIso8601String(),
            ]);
        }

        try {
            $transaction = $this->pakasirService->transactionDetail(
                $this->paymentOrderId($booking),
                $payment->nominal_transaksi ?? $this->rupiah($booking->total_harga),
            );
        } catch (PakasirException $exception) {
            // Status belum pasti; kursi tetap dikunci dan transaksi tidak dibuat ulang.
            $this->recordGatewaySnapshot($payment->fresh(), [
                'activation_status' => 'verification_pending',
                'reconciliation_error' => $exception->getMessage(),
                'activation_checked_at' => now()->toIso8601String(),
            ]);

            throw ValidationException::withMessages([
                'payment_method' => 'Status transaksi Pakasir masih diperiksa. Jangan membuat pembayaran baru; silakan coba cek kembali beberapa saat lagi.',
            ]);
        }

        $booking = $this->freshBookingWithRelations($booking->id);
        $this->ensureGatewayTransactionMatches($booking, $transaction);
        $status = (string) data_get($transaction, 'status');

        if ($this->pakasirService->isCompletedStatus($status)) {
            return $this->syncPakasirPayment($booking, $transaction);
        }

        if ($this->pakasirService->isExpiredStatus($status)) {
            return $this->markGatewayPaymentUnsuccessful($booking, $transaction, null, 'kedaluwarsa');
        }

        if ($this->pakasirService->isFailedStatus($status)) {
            return $this->markGatewayPaymentUnsuccessful($booking, $transaction, null, 'gagal');
        }

        $this->recordGatewaySnapshot($booking->pembayaran, [
            'activation_status' => 'provider_transaction_found',
            'transaction_detail' => $transaction,
            'activation_checked_at' => now()->toIso8601String(),
        ]);

        throw ValidationException::withMessages([
            'payment_method' => 'Transaksi Pakasir sudah tercatat dan masih diproses. Sistem tidak membuat transaksi kedua; silakan cek kembali beberapa saat lagi.',
        ]);
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
                    'payment' => 'Data pembayaran tidak ditemukan untuk pemesanan ini.',
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
                    'payment' => 'Data pembayaran tidak ditemukan untuk pemesanan ini.',
                ]);
            }

            // Webhook berulang hanya memperbarui catatan, bukan membuat tiket lagi.
            if (in_array($lockedBooking->status_pemesanan, ['terbayar', 'selesai'], true)) {
                $amountSnapshots = $this->gatewayAmountSnapshots($payment, $transaction);
                $payment->update([
                    'paid_channel' => (string) data_get($transaction, 'payment_method', $payment->paid_channel),
                    ...$amountSnapshots,
                    'payment_number' => filled(data_get($transaction, 'payment_number'))
                        && blank($payment->payment_number)
                        ? trim((string) data_get($transaction, 'payment_number'))
                        : $payment->payment_number,
                    'jumlah_bayar' => $amountSnapshots['total_dibayar_penumpang'] ?? $payment->jumlah_bayar,
                    'gateway_response' => $this->pakasirService->mergeGatewayResponse(
                        $payment->gateway_response,
                        [
                            'transaction_detail' => $transaction,
                            'duplicate_success_webhook' => true,
                        ],
                    ),
                    'raw_webhook_payload' => $webhookPayload ?? $payment->raw_webhook_payload,
                    'webhook_verified_at' => now(),
                ]);

                return $this->freshBookingWithRelations($lockedBooking->id);
            }

            $gatewayResponse = $this->pakasirService->mergeGatewayResponse(
                $payment->gateway_response,
                ['transaction_detail' => $transaction],
            );

            $amountSnapshots = $this->gatewayAmountSnapshots($payment, $transaction);

            $payment->update([
                'paid_channel' => (string) data_get($transaction, 'payment_method', $payment->paid_channel),
                ...$amountSnapshots,
                'payment_number' => filled(data_get($transaction, 'payment_number'))
                    && blank($payment->payment_number)
                    ? trim((string) data_get($transaction, 'payment_number'))
                    : $payment->payment_number,
                'jumlah_bayar' => $amountSnapshots['total_dibayar_penumpang'] ?? $payment->jumlah_bayar,
                'status_bayar' => 'berhasil',
                'gateway_response' => $gatewayResponse,
                'raw_webhook_payload' => $webhookPayload ?? $payment->raw_webhook_payload,
                'webhook_verified_at' => now(),
                'dibayar_pada' => $this->resolveGatewayExpiredAt((string) data_get($transaction, 'completed_at')) ?? now(),
            ]);

            // Pembayaran yang masuk setelah booking batal tidak boleh menerbitkan tiket.
            if ($lockedBooking->status_pemesanan === 'dibatalkan'
                || $lockedBooking->detailPemesanan->isEmpty()) {
                $this->adminNotification->paymentReceivedAfterCancellation($lockedBooking);

                return $this->freshBookingWithRelations($lockedBooking->id);
            }

            $lockedBooking->update([
                'status_pemesanan' => 'terbayar',
            ]);

            $this->finalizePaidBooking($lockedBooking);

            return $this->freshBookingWithRelations($lockedBooking->id);
        });
    }

    /** Membatalkan booking dan melepas kursi jika pembuatan pembayaran gagal. */
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

    /** Mengunci kursi dan menerbitkan satu tiket untuk setiap penumpang. */
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
        // Pemesanan loket tanpa akun tetap berhasil tanpa notifikasi pengguna.
        if ($booking->pengguna_id === null) {
            return;
        }

        $booking->loadMissing(['jadwal.rute']);

        $routeName = $booking->jadwal?->rute
            ? $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan
            : 'perjalanan Anda';
        $departure = $booking->jadwal?->waktu_berangkat?->translatedFormat('d F Y H:i') ?? '-';

        $message = $this->notificationTemplate->render(
            'Pembayaran Anda untuk rute {rute} telah berhasil dikonfirmasi.',
            [
                'nama' => $booking->namaKontakPemesan(),
                'rute' => $routeName,
                // Placeholder lama tetap didukung, tetapi nilainya tidak lagi
                // membuka kode internal pemesanan pada notifikasi pengguna.
                'kode_booking' => $departure,
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
                'pesan' => trim($message.' Tiket elektronik untuk jadwal keberangkatan '.$departure.' sudah aktif.'),
                'sudah_dibaca' => false,
            ],
        );
    }

    /** Menambahkan hasil komunikasi Pakasir ke catatan pembayaran. */
    private function recordGatewaySnapshot(Pembayaran $payment, array $payload): void
    {
        $payment->update([
            'gateway_response' => $this->pakasirService->mergeGatewayResponse(
                $payment->gateway_response,
                $payload,
            ),
        ]);
    }

    /** Mengecek status Pakasir lalu menyesuaikan status pembayaran lokal. */
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
            $this->paymentOrderId($booking),
            $payment->nominal_transaksi ?? $this->rupiah($booking->total_harga),
        );
        $this->ensureGatewayTransactionMatches($booking, $transaction);

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
                'booking' => 'Simulasi pembayaran belum memperoleh status berhasil dari Pakasir.',
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
            // Cek Pakasir dulu agar pembayaran valid tidak ikut dibatalkan.
            return $this->refreshGatewayPaymentStatusFromSystem($booking);
        } catch (Throwable) {
            // Jika Pakasir tidak dapat dihubungi, batas waktu lokal tetap berlaku.
            return $booking;
        }
    }

    private function freshBookingWithRelations(int $bookingId): Pemesanan
    {
        return Pemesanan::query()->with([
            'pengguna',
            'dibuatOleh',
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
            // Ubah waktu Pakasir ke zona waktu aplikasi sebelum disimpan.
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
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
        $isEnabled = match (true) {
            $paymentMethod === PaymentMethodCatalog::QRIS => $this->settingService->get('payment_qris_enabled', false),
            PaymentMethodCatalog::isVirtualAccount($paymentMethod) => $this->settingService->get('payment_transfer_enabled', false),
            $paymentMethod === PaymentMethodCatalog::CASH => $this->settingService->get('payment_cash_enabled', false),
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
                'seat_numbers' => 'Jumlah kursi melebihi batas maksimal dalam satu pemesanan.',
            ]);
        }
    }

    /** Memastikan pemesanan hanya dibuka oleh pemilik atau Petugas Loket pembuatnya. */
    public function ensureUserCanAccessBooking(Pemesanan $booking, User $user): void
    {
        $isPassengerOwner = $user->isPenumpang()
            && (int) $booking->pengguna_id === (int) $user->id;
        $isCounterCreator = $user->isPetugasLoket()
            && $booking->isPemesananLoket()
            && (int) $booking->dibuat_oleh === (int) $user->id;

        if (! $isPassengerOwner && ! $isCounterCreator) {
            // Respons 404 mencegah pengguna menebak ID pemesanan milik orang lain.
            abort(404, 'Pemesanan tidak ditemukan untuk akun ini.');
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
            if ($seat->status_kursi === 'nonaktif') {
                throw ValidationException::withMessages([
                    'seat_numbers' => "Kursi {$seat->nomor_kursi} sedang ditutup untuk jadwal ini.",
                ]);
            }

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
                    'seat_numbers' => "Kursi {$seat->nomor_kursi} sudah dipesan dalam pemesanan lain.",
                ]);
            }
        }
    }

    /**
     * Draft hanya boleh dibuat dari kunci form yang masih aktif dan memang
     * dimiliki pengguna. Status "tersedia" tidak cukup karena dapat berarti
     * sesi form sebelumnya sudah kedaluwarsa atau sengaja dilepas.
     *
     * @param  Collection<int, KursiJadwal>  $selectedSeats
     *
     * @throws ValidationException
     */
    private function ensureActiveFormLocksOwnedByUser(
        Collection $selectedSeats,
        int $userId,
        CarbonInterface $now,
    ): void {
        $hasInvalidLock = $selectedSeats->contains(function (KursiJadwal $seat) use ($userId, $now) {
            return $seat->status_kursi !== 'terkunci'
                || (int) $seat->dikunci_oleh !== $userId
                || $seat->lock_expires_at === null
                || $seat->lock_expires_at->lessThanOrEqualTo($now);
        });

        if ($hasInvalidLock) {
            throw ValidationException::withMessages([
                'seat_numbers' => 'Sesi konfirmasi pemesanan telah berakhir. Kursi sudah dilepas, silakan pilih jadwal dan kursi kembali.',
            ]);
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

    private function normalizeBaggagePlacement(mixed $value): string
    {
        $placement = is_string($value) ? strtolower($value) : 'masuk_bagasi';

        return in_array($placement, ['dipegang_sendiri', 'masuk_bagasi'], true)
            ? $placement
            : 'masuk_bagasi';
    }

    private function normalizeBaggageSizeCategory(mixed $value): ?string
    {
        $category = is_string($value) ? strtolower($value) : null;

        return in_array($category, ['kecil', 'besar'], true) ? $category : null;
    }

    private function normalizeLargeBaggageType(mixed $value): ?string
    {
        $type = is_string($value) ? strtolower($value) : null;

        return in_array($type, ['sepeda', 'sepeda_motor', 'beko', 'lainnya'], true) ? $type : null;
    }

    private function resolveFormLockDeadline($now)
    {
        return $now->copy()->addMinutes(
            (int) $this->settingService->get('seat_lock_form_minutes', 30),
        );
    }

    private function resolvePaymentLockDeadline($now, ?Jadwal $schedule = null, ?string $paymentMethod = null)
    {
        return $now->copy()->addMinutes(
            (int) $this->settingService->get('seat_lock_payment_minutes', 5),
        );
    }

    private function rupiah(int|float|string|null $value): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal pembayaran lokal tidak valid.',
            ]);
        }

        $numeric = (float) $value;
        if ($numeric < 1 || floor($numeric) !== $numeric) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal pembayaran wajib berupa rupiah bulat.',
            ]);
        }

        return (int) $numeric;
    }

    /** Membaca nominal rupiah dari response Pakasir sebagai bilangan bulat. */
    private function providerRupiah(array $payload, string $key, bool $allowZero): int
    {
        $value = data_get($payload, $key);
        $stringValue = is_int($value) ? (string) $value : trim((string) $value);

        if (! preg_match('/^\d+$/', $stringValue)) {
            throw new PakasirException("Response Pakasir tidak memiliki {$key} rupiah yang valid.");
        }

        $amount = (int) $stringValue;
        if ((! $allowZero && $amount < 1) || ($allowZero && $amount < 0)) {
            throw new PakasirException("Response Pakasir memiliki {$key} yang tidak valid.");
        }

        return $amount;
    }

    /**
     * Menjaga snapshot Transaction Create; Transaction Detail hanya melengkapi data lama.
     *
     * @return array{
     *     nominal_transaksi: int,
     *     biaya_admin_provider: int|null,
     *     total_dibayar_penumpang: int|null
     * }
     */
    private function gatewayAmountSnapshots(Pembayaran $payment, array $transaction): array
    {
        return [
            'nominal_transaksi' => $payment->nominal_transaksi
                ?? $this->providerRupiah($transaction, 'amount', false),
            'biaya_admin_provider' => $payment->biaya_admin_provider
                ?? (data_get($transaction, 'fee') === null
                    ? null
                    : $this->providerRupiah($transaction, 'fee', true)),
            'total_dibayar_penumpang' => $payment->total_dibayar_penumpang
                ?? (data_get($transaction, 'total_payment') === null
                    ? null
                    : $this->providerRupiah($transaction, 'total_payment', false)),
        ];
    }

    /** Memastikan detail Pakasir cocok dengan pembayaran milik booking ini. */
    private function ensureGatewayTransactionMatches(Pemesanan $booking, array $transaction): void
    {
        $expectedOrderId = $this->paymentOrderId($booking);
        $transactionOrderId = (string) data_get($transaction, 'order_id', '');
        if ($transactionOrderId !== '' && $transactionOrderId !== $expectedOrderId) {
            throw ValidationException::withMessages([
                'order_id' => 'Kode transaksi Pakasir tidak sesuai dengan pembayaran lokal.',
            ]);
        }

        $transactionProject = (string) data_get($transaction, 'project', '');
        if ($transactionProject !== '' && ! $this->pakasirService->validateProject($transactionProject)) {
            throw ValidationException::withMessages([
                'project' => 'Identitas project pada transaksi Pakasir tidak sesuai.',
            ]);
        }

        $transactionAmount = data_get($transaction, 'amount');
        $expectedAmount = $booking->pembayaran?->nominal_transaksi
            ?? $this->rupiah($booking->total_harga);
        if ($transactionAmount === null || $this->rupiah($transactionAmount) !== $expectedAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal transaksi Pakasir tidak sesuai dengan total pemesanan.',
            ]);
        }

        $selectedMethod = (string) $booking->pembayaran?->metode_bayar;
        $reportedMethod = trim((string) data_get($transaction, 'payment_method', ''));
        if (
            $reportedMethod !== ''
            && ! PaymentMethodCatalog::gatewayChannelMatches($selectedMethod, $reportedMethod)
        ) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode transaksi Pakasir tidak sesuai dengan Virtual Account yang dipilih.',
            ]);
        }
    }

    /** Memastikan hasil Transaction Create sama dengan metode dan nominal yang diminta. */
    private function ensureGatewayCreationMatches(
        string $selectedMethod,
        string $expectedOrderId,
        int $expectedAmount,
        array $payment,
    ): void {
        $project = trim((string) data_get($payment, 'project', ''));
        if ($project === '' || ! $this->pakasirService->validateProject($project)) {
            throw new PakasirException('Project pada response Pakasir tidak sesuai.');
        }

        $orderId = trim((string) data_get($payment, 'order_id', ''));
        if ($orderId === '' || $orderId !== $expectedOrderId) {
            throw new PakasirException('Kode transaksi pada response Pakasir tidak sesuai.');
        }

        $method = trim((string) data_get($payment, 'payment_method', ''));
        if ($method === '' || ! PaymentMethodCatalog::gatewayChannelMatches($selectedMethod, $method)) {
            throw new PakasirException('Metode pembayaran pada response Pakasir tidak sesuai.');
        }

        $providerAmount = $this->providerRupiah($payment, 'amount', false);
        $this->providerRupiah($payment, 'fee', true);
        $this->providerRupiah($payment, 'total_payment', false);

        if ($providerAmount !== $expectedAmount) {
            throw new PakasirException('Nominal pada response Pakasir tidak sesuai.');
        }

        // Fee tetap memakai nilai Pakasir; aplikasi hanya menjumlahkannya ke nominal.

        if (trim((string) data_get($payment, 'payment_number', '')) === '') {
            throw new PakasirException('Nomor pembayaran dari Pakasir belum tersedia.');
        }

        if ($this->resolveGatewayExpiredAt((string) data_get($payment, 'expired_at')) === null) {
            throw new PakasirException('Batas waktu pembayaran dari Pakasir tidak valid.');
        }
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

    private function generatePaymentTransactionCode(): string
    {
        do {
            $code = sprintf(
                'TRX-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(5)),
            );
        } while (Pembayaran::query()->where('order_id_gateway', $code)->exists());

        return $code;
    }

    private function paymentOrderId(Pemesanan $booking): string
    {
        return filled($booking->pembayaran?->order_id_gateway)
            ? (string) $booking->pembayaran->order_id_gateway
            : $booking->kode_booking;
    }
}
