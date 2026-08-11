<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Pemesanan;
use App\Models\User;
use App\Services\BookingCalculator;
use App\Services\BookingService;
use App\Services\PakasirService;
use App\Services\ScheduleLifecycleService;
use App\Services\SettingService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingCalculator $bookingCalculator,
        private readonly PakasirService $pakasirService,
        private readonly ScheduleLifecycleService $scheduleLifecycleService,
        private readonly SettingService $settingService,
    ) {}

    public function scheduleDetail(Jadwal $jadwal): JsonResponse
    {
        $this->bookingService->releaseExpiredResources($jadwal->id);
        $this->scheduleLifecycleService->closeSingleIfPastBookingWindow($jadwal);
        $jadwal->load(['rute', 'kendaraan', 'pengemudi.pengguna', 'kursi']);
        $qrisEnabled = (bool) $this->settingService->get('payment_qris_enabled', false);
        $virtualAccountEnabled = (bool) $this->settingService->get('payment_transfer_enabled', false);
        $cashEnabled = (bool) $this->settingService->get('payment_cash_enabled', false);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $jadwal->id,
                'status' => $jadwal->status,
                'waktu_berangkat' => $jadwal->waktu_berangkat?->toIso8601String(),
                'estimasi_tiba' => $jadwal->estimasi_tiba?->toIso8601String(),
                'rute' => [
                    'asal' => $jadwal->rute->kota_asal,
                    'tujuan' => $jadwal->rute->kota_tujuan,
                    'jalur' => $jadwal->rute->deskripsi_jalur,
                    'harga_tiket' => (float) $jadwal->rute->harga_tiket,
                    'durasi_menit' => $jadwal->rute->estimasi_durasi_menit,
                    'durasi_label' => $jadwal->rute->durasi_label,
                ],
                'kendaraan' => $this->formatVehicle($jadwal),
                'pengemudi' => $this->formatDriver($jadwal),
                'seat_map' => $jadwal->kursi
                    ->sortBy('nomor_kursi')
                    ->values()
                    ->map(fn ($seat) => [
                        'id' => $seat->id,
                        'nomor_kursi' => $seat->nomor_kursi,
                        'status' => $seat->status_kursi,
                        'lock_expires_at' => $seat->lock_expires_at?->toIso8601String(),
                    ])
                    ->all(),
                'booking_rules' => [
                    'max_seat_per_booking' => (int) $this->settingService->get('max_seat_per_booking', 4),
                    'seat_lock_form_minutes' => (int) $this->settingService->get('seat_lock_form_minutes', 30),
                    'seat_lock_payment_minutes' => (int) $this->settingService->get('seat_lock_payment_minutes', 5),
                ],
                'payment_methods' => [
                    // Field boolean lama dipertahankan untuk client versi lama.
                    'transfer' => $virtualAccountEnabled,
                    'qris' => $qrisEnabled,
                    'cash' => $cashEnabled,
                    'options' => PaymentMethodCatalog::selectableOptions(
                        $qrisEnabled,
                        $virtualAccountEnabled,
                        $cashEnabled,
                    ),
                ],
                'bagasi' => [
                    // Nilai legacy tetap dikirim agar aplikasi lama tidak gagal
                    // parsing, tetapi seluruh tarif dinonaktifkan dan bernilai nol.
                    'weight_enabled' => false,
                    'free_weight_kg' => 0,
                    'max_weight_per_person_kg' => 0,
                    'extra_fee_per_kg' => 0,
                    'size_enabled' => false,
                    'free_size_cm' => 0,
                    'max_size_per_person_cm' => 0,
                    'extra_fee_per_cm' => 0,
                    'item_enabled' => false,
                    'free_item_count' => 0,
                    'max_item_per_person' => 0,
                    'extra_fee_per_item' => 0,
                    'small_item_fee' => 0,
                    'large_item_fee' => 0,
                    'large_vehicle_enabled' => false,
                    'bicycle_fee' => 0,
                    'motorcycle_fee' => 0,
                    'heavy_item_fee' => 0,
                    'other_large_item_fee' => 0,
                    'photo_required' => false,
                ],
            ],
        ]);
    }

    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jadwal_id' => ['required', 'integer', 'exists:jadwal,id'],
            'seat_count' => ['required', 'integer', 'min:1'],
            'passenger_count' => ['nullable', 'integer', 'min:1'],
            'baggage_items' => ['nullable', 'array'],
            'baggage_items.*.deskripsi_item' => ['required_with:baggage_items', 'string', 'max:255'],
            'baggage_items.*.metode_hitung' => ['nullable', 'string', 'in:berat,ukuran'],
            'baggage_items.*.posisi_barang' => ['nullable', 'string', 'in:dipegang_sendiri,masuk_bagasi'],
            'baggage_items.*.kategori_ukuran' => ['nullable', 'string', 'in:kecil,besar'],
            'baggage_items.*.jenis_barang_besar' => ['nullable', 'string', 'in:sepeda,sepeda_motor,beko,lainnya'],
            'baggage_items.*.berat_kg' => ['nullable', 'numeric', 'min:0'],
            'baggage_items.*.ukuran_cm' => ['nullable', 'integer', 'min:0'],
            'baggage_items.*.panjang_cm' => ['nullable', 'integer', 'min:0'],
            'baggage_items.*.lebar_cm' => ['nullable', 'integer', 'min:0'],
            'baggage_items.*.jumlah_item' => ['nullable', 'integer', 'min:1'],
            'baggage_items.*.foto_url' => ['nullable', 'string', 'max:255'],
            'baggage_items.*.foto_urls' => ['nullable', 'array', 'max:3'],
            'baggage_items.*.foto_urls.*' => ['nullable', 'string', 'max:255'],
        ]);
        $schedule = Jadwal::query()->with('rute')->findOrFail($validated['jadwal_id']);
        $this->scheduleLifecycleService->closeSingleIfPastBookingWindow($schedule);

        if ($schedule->status !== 'dibuka') {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal yang dipilih sudah ditutup untuk pemesanan.',
            ], 422);
        }

        $totals = $this->bookingCalculator->calculate(
            $schedule,
            (int) $validated['seat_count'],
        );

        return response()->json([
            'success' => true,
            'data' => $totals,
        ]);
    }

    public function lockForm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jadwal_id' => ['required', 'integer', 'exists:jadwal,id'],
            'seat_numbers' => ['required', 'array', 'min:1'],
            'seat_numbers.*' => ['required', 'integer', 'min:1'],
        ]);

        $lock = $this->bookingService->lockSeatsForForm($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Kursi berhasil dikunci untuk proses checkout.',
            'data' => $lock,
        ]);
    }

    public function releaseFormLock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jadwal_id' => ['required', 'integer', 'exists:jadwal,id'],
            'seat_numbers' => ['required', 'array', 'min:1'],
            'seat_numbers.*' => ['required', 'integer', 'min:1'],
        ]);

        $released = $this->bookingService->releaseFormLocks($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Kunci kursi pengisian form berhasil dilepas.',
            'data' => [
                'released' => $released,
            ],
        ]);
    }

    /** Membuat booking lebih dulu tanpa memilih metode atau memanggil Pakasir. */
    public function store(Request $request): JsonResponse
    {
        $rules = [
            'jadwal_id' => ['required', 'integer', 'exists:jadwal,id'],
            'seat_numbers' => ['required', 'array', 'min:1'],
            'seat_numbers.*' => ['required', 'integer', 'min:1'],
            'pickup' => ['required', 'array'],
            'pickup.latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup.longitude' => ['required', 'numeric', 'between:-180,180'],
            'pickup.address' => ['required', 'string', 'max:1000'],
            'pickup.note' => ['required', 'string', 'max:500'],
            'dropoff' => ['required', 'array'],
            'dropoff.latitude' => ['required', 'numeric', 'between:-90,90'],
            'dropoff.longitude' => ['required', 'numeric', 'between:-180,180'],
            'dropoff.address' => ['required', 'string', 'max:1000'],
            'dropoff.note' => ['required', 'string', 'max:500'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.seat_number' => ['required', 'integer', 'min:1'],
            // Nama pada tiket hanya menerima huruf dan spasi.
            'passengers.*.nama_penumpang' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z ]+$/'],
            'passengers.*.no_hp_penumpang' => ['required', 'string', 'regex:/^[0-9]{11,12}$/'],
            'baggage_items' => ['nullable', 'array'],
            'baggage_items.*.deskripsi_item' => ['required_with:baggage_items', 'string', 'max:255'],
            'baggage_items.*.metode_hitung' => ['nullable', 'string', 'in:berat,ukuran'],
            'baggage_items.*.posisi_barang' => ['nullable', 'string', 'in:dipegang_sendiri,masuk_bagasi'],
            'baggage_items.*.kategori_ukuran' => ['nullable', 'string', 'in:kecil,besar'],
            'baggage_items.*.jenis_barang_besar' => ['nullable', 'string', 'in:sepeda,sepeda_motor,beko,lainnya'],
            'baggage_items.*.berat_kg' => ['nullable', 'numeric', 'min:0'],
            'baggage_items.*.ukuran_cm' => ['nullable', 'integer', 'min:0'],
            'baggage_items.*.panjang_cm' => ['nullable', 'integer', 'min:0'],
            'baggage_items.*.lebar_cm' => ['nullable', 'integer', 'min:0'],
            'baggage_items.*.jumlah_item' => ['nullable', 'integer', 'min:1'],
            'baggage_items.*.foto_url' => ['nullable', 'string', 'max:255'],
            'baggage_items.*.foto_urls' => ['nullable', 'array', 'max:3'],
            'baggage_items.*.foto_urls.*' => ['nullable', 'string', 'max:255'],
        ];

        if ($request->user()->isPetugasLoket()) {
            $rules += [
                'customer' => ['required', 'array'],
                'customer.name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z ]+$/'],
                'customer.phone' => ['required', 'string', 'regex:/^[0-9]{11,12}$/'],
                'customer.email' => ['nullable', 'email:rfc', 'max:100'],
                'passenger_account_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('pengguna', 'id')->where('peran', 'penumpang'),
                ],
            ];
        }

        $validated = $request->validate($rules, [
            'baggage_items.*.foto_urls.max' => 'Foto barang bawaan maksimal 3 foto untuk setiap pemesanan.',
            'pickup.address.required' => 'Alamat titik jemput wajib diisi.',
            'pickup.note.required' => 'Catatan atau patokan titik jemput wajib diisi.',
            'dropoff.address.required' => 'Alamat titik turun wajib diisi.',
            'dropoff.note.required' => 'Catatan atau patokan titik turun wajib diisi.',
            'passengers.*.nama_penumpang.required' => 'Nama penumpang wajib diisi.',
            'passengers.*.nama_penumpang.regex' => 'Nama penumpang hanya boleh berisi huruf dan spasi.',
            'passengers.*.no_hp_penumpang.required' => 'Nomor HP penumpang wajib diisi.',
            'passengers.*.no_hp_penumpang.regex' => 'Nomor HP penumpang harus terdiri dari 11 sampai 12 digit angka.',
            'customer.name.required' => 'Nama pemesan loket wajib diisi.',
            'customer.name.regex' => 'Nama pemesan hanya boleh berisi huruf dan spasi.',
            'customer.phone.required' => 'Nomor HP pemesan loket wajib diisi.',
            'customer.phone.regex' => 'Nomor HP pemesan harus terdiri dari 11 sampai 12 digit angka.',
            'customer.email.email' => 'Format email pemesan belum valid.',
        ]);

        $booking = $request->user()->isPetugasLoket()
            ? $this->bookingService->createCounterDraft(
                $request->user(),
                isset($validated['passenger_account_id'])
                    ? User::query()->find((int) $validated['passenger_account_id'])
                    : null,
                $validated,
            )
            : $this->bookingService->createDraft($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Pemesanan berhasil dibuat. Silakan pilih metode pembayaran.',
            'data' => $this->formatBooking($booking),
        ], 201);
    }

    /** Memilih metode pembayaran setelah booking berhasil dibuat. */
    public function choosePayment(Request $request, Pemesanan $booking): JsonResponse
    {
        $this->bookingService->ensureUserCanAccessBooking($booking, $request->user());

        $validated = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(PaymentMethodCatalog::selectableCodes())],
        ]);

        $booking = $this->bookingService->choosePaymentMethod(
            $booking,
            $request->user(),
            (string) $validated['payment_method'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Metode pembayaran berhasil dipilih.',
            'data' => $this->formatBooking($booking, true),
        ]);
    }

    public function uploadBaggagePhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], [
            'foto.required' => 'Foto barang bawaan wajib dilampirkan.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Format foto harus JPG, JPEG, PNG, atau WEBP.',
            'foto.max' => 'Ukuran foto maksimal 3 MB. Silakan pilih foto yang lebih kecil.',
        ]);

        $file = $validated['foto'];
        $path = $file->store('baggage', 'public');

        return response()->json([
            'success' => true,
            'message' => 'Foto barang bawaan berhasil diunggah.',
            'data' => [
                'path' => $path,
                'url' => $this->publicStorageUrl($path),
                'original_name' => $file->getClientOriginalName(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->bookingService->releaseExpiredResources();

        $bookings = Pemesanan::query()
            ->with([
                'pengguna',
                'dibuatOleh',
                'jadwal.rute',
                'jadwal.kendaraan',
                'jadwal.pengemudi.pengguna',
                'detailPemesanan.tiketElektronik',
                'pembayaran',
            ])
            ->when(
                $request->user()->isPetugasLoket(),
                fn ($query) => $query
                    ->where('sumber_pemesanan', 'loket')
                    ->where('dibuat_oleh', $request->user()->id),
                fn ($query) => $query->where('pengguna_id', $request->user()->id),
            )
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bookings->map(fn (Pemesanan $booking) => $this->formatBooking($booking))->all(),
        ]);
    }

    public function show(Request $request, Pemesanan $booking): JsonResponse
    {
        $this->bookingService->releaseExpiredResources($booking->jadwal_id);
        $this->bookingService->ensureUserCanAccessBooking($booking, $request->user());

        // Route model binding terjadi sebelum proses kedaluwarsa dijalankan.
        // Muat ulang atribut supaya respons tidak membawa status lama.
        $booking->refresh();
        $booking->load([
            'pengguna',
            'dibuatOleh',
            'jadwal.rute',
            'jadwal.kendaraan',
            'jadwal.pengemudi.pengguna',
            'detailPemesanan.tiketElektronik',
            'itemBagasi',
            'pembayaran',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatBooking($booking, true),
        ]);
    }

    public function simulatePayment(Request $request, Pemesanan $booking): JsonResponse
    {
        $booking = $this->bookingService->simulatePaymentSuccess($booking, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran simulasi berhasil dan tiket telah diterbitkan.',
            'data' => $this->formatBooking($booking, true),
        ]);
    }

    public function refreshPaymentStatus(Request $request, Pemesanan $booking): JsonResponse
    {
        $this->bookingService->ensureUserCanAccessBooking($booking, $request->user());

        $booking = $this->bookingService->refreshGatewayPaymentStatus($booking, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pemeriksaan status pembayaran selesai.',
            'data' => $this->formatBooking($booking, true),
        ]);
    }

    public function confirmCash(Request $request, Pemesanan $booking): JsonResponse
    {
        $this->bookingService->ensureUserCanAccessBooking($booking, $request->user());
        $booking = $this->bookingService->confirmCashPayment($booking, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran tunai berhasil dikonfirmasi dan tiket telah diterbitkan.',
            'data' => $this->formatBooking($booking, true),
        ]);
    }

    /** Menyusun data booking dan pembayaran yang dikirim ke aplikasi mobile. */
    public function formatBooking(Pemesanan $booking, bool $full = false): array
    {
        $gatewayMeta = $this->pakasirService->decodeGatewayResponse($booking->pembayaran?->gateway_response);
        $createPayment = data_get($gatewayMeta, 'create_payment', []);
        $transactionDetail = data_get($gatewayMeta, 'transaction_detail', []);
        $payment = $booking->pembayaran;
        $amount = $booking->pembayaran?->nominal_transaksi
            ?? (int) data_get($createPayment, 'amount', $booking->total_harga);
        $providerFee = $booking->pembayaran?->biaya_admin_provider;
        $totalPayment = $booking->pembayaran?->total_dibayar_penumpang;
        if (
            $payment !== null
            && $this->pakasirService->canCreateTransaction($payment->metode_bayar)
            && $providerFee !== null
        ) {
            // Menjaga transaksi lama tetap menampilkan nominal ditambah fee provider.
            $totalPayment = $amount + $providerFee;
        }

        $checkoutUrl = (string) data_get(
            $gatewayMeta,
            'checkout_url',
            data_get(
                $createPayment,
                'checkout_url',
                data_get($createPayment, 'payment_url', ''),
            ),
        );

        if (
            $checkoutUrl === ''
            && $payment !== null
            && (
                $payment->metode_bayar === PaymentMethodCatalog::LEGACY_TRANSFER
                || $this->pakasirService->canCreateTransaction($payment->metode_bayar)
            )
            && $payment->status_bayar === 'menunggu'
            && $booking->status_pemesanan === 'menunggu_pembayaran'
            && ($payment->expired_at === null || $payment->expired_at->isFuture())
            && $this->pakasirService->isConfigured()
        ) {
            $checkoutUrl = $this->pakasirService->checkoutUrl(
                $payment->order_id_gateway ?: $booking->kode_booking,
                $amount,
                $payment->metode_bayar === PaymentMethodCatalog::QRIS,
                $this->pakasirService->merchantReturnUrl(),
                $payment->metode_bayar === PaymentMethodCatalog::LEGACY_TRANSFER
                    ? null
                    : $payment->metode_bayar,
            );
        }

        $paymentNumber = (string) (
            $payment?->payment_number
            ?? data_get($createPayment, 'payment_number', $payment?->gateway_reference)
        );
        $paymentStatus = match (true) {
            $payment === null && $booking->status_pemesanan === 'menunggu_pembayaran' => 'menunggu_pemilihan_metode',
            $payment?->metode_bayar === PaymentMethodCatalog::CASH
                && $payment->status_bayar === 'menunggu' => 'menunggu_konfirmasi_tunai',
            default => $payment?->status_bayar,
        };

        $response = [
            'id' => $booking->id,
            'booking_id' => $booking->id,
            'jadwal_id' => $booking->jadwal_id,
            'kode_booking' => $booking->kode_booking,
            'order_id' => $booking->kode_booking,
            'status' => $booking->status_pemesanan,
            'status_pemesanan' => $booking->status_pemesanan,
            'jumlah_kursi' => $booking->jumlah_kursi,
            'batas_pembayaran' => $booking->batas_pembayaran?->toIso8601String(),
            'subtotal_tiket' => (int) $booking->subtotal_tiket,
            'total_harga' => (int) $booking->total_harga,
            'sumber_pemesanan' => $booking->sumber_pemesanan ?: 'aplikasi_penumpang',
            'dibuat_oleh' => $booking->dibuatOleh ? [
                'id' => $booking->dibuatOleh->id,
                'nama' => $booking->dibuatOleh->nama,
            ] : null,
            'pemesan' => [
                'nama' => $booking->namaKontakPemesan(),
                'email' => $booking->emailKontakPemesan(),
                'no_hp' => $booking->nomorKontakPemesan(),
                'foto_url' => $this->publicStorageUrl($booking->pengguna?->foto_profil),
            ],
            'rute' => $booking->jadwal?->rute
                ? $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan
                : null,
            'waktu_berangkat' => $booking->jadwal?->waktu_berangkat?->toIso8601String(),
            'kendaraan' => $this->formatVehicle($booking->jadwal),
            'pengemudi' => $this->formatDriver($booking->jadwal),
            'payment' => [
                'status' => $payment?->status_bayar,
                'status_pembayaran' => $paymentStatus,
                'method' => $payment?->metode_bayar,
                'metode_pembayaran' => $payment?->metode_bayar,
                'method_label' => $payment ? PaymentMethodCatalog::label($payment->metode_bayar) : null,
                'method_family' => $payment ? PaymentMethodCatalog::family($payment->metode_bayar) : null,
                'provider_order_id' => $payment?->metode_bayar === PaymentMethodCatalog::CASH
                    ? null
                    : $payment?->order_id_gateway,
                'can_choose' => $payment === null
                    && $booking->status_pemesanan === 'menunggu_pembayaran'
                    && $booking->batas_pembayaran?->isFuture(),
                'selection_expires_at' => $payment === null
                    ? $booking->batas_pembayaran?->toIso8601String()
                    : null,
                'expired_at' => $payment?->expired_at?->toIso8601String(),
                // Waktu pembayaran berasal dari catatan backend, bukan perkiraan mobile.
                'paid_at' => $payment?->dibayar_pada?->toIso8601String(),
                'can_simulate' => $payment !== null && app()->environment(['local', 'testing']),
                'uses_gateway' => $payment !== null
                    && $this->pakasirService->handlesMethod($payment->metode_bayar)
                    && $this->pakasirService->isConfigured(),
                'provider' => data_get($gatewayMeta, 'provider'),
                'project' => data_get($createPayment, 'project'),
                // Field utama untuk mobile Sesi 2; alias lama tetap tersedia.
                'nominal_transaksi' => $amount,
                'biaya_admin_provider' => $providerFee,
                'total_dibayar_penumpang' => $totalPayment,
                'amount' => $amount,
                'provider_fee' => $providerFee,
                'total_payment' => $totalPayment,
                'payment_number' => $paymentNumber !== '' ? $paymentNumber : null,
                'checkout_url' => $checkoutUrl !== '' ? $checkoutUrl : null,
                'gateway_status' => data_get($transactionDetail, 'status'),
                'completed_at' => $payment?->dibayar_pada?->toIso8601String(),
            ],
        ];

        if (! $full) {
            return $response;
        }

        return [
            ...$response,
            'pickup' => [
                'latitude' => (float) $booking->lintang_jemput,
                'longitude' => (float) $booking->bujur_jemput,
                'address' => $booking->alamat_jemput,
                'note' => $booking->catatan_jemput,
            ],
            'dropoff' => [
                'latitude' => $booking->lintang_turun !== null ? (float) $booking->lintang_turun : null,
                'longitude' => $booking->bujur_turun !== null ? (float) $booking->bujur_turun : null,
                'address' => $booking->alamat_turun,
                'note' => $booking->catatan_turun,
            ],
            'passengers' => $booking->detailPemesanan->map(fn ($detail) => [
                'seat_number' => $detail->nomor_kursi,
                'nama_penumpang' => $detail->nama_penumpang,
                'no_hp_penumpang' => $detail->no_hp_penumpang,
                'status_kehadiran' => $detail->status_kehadiran,
                'ticket' => $detail->tiketElektronik
                    ? [
                        'kode_qr' => $detail->tiketElektronik->kode_qr,
                        'token' => $detail->tiketElektronik->token_unik_qr,
                        'sudah_dipindai' => $detail->tiketElektronik->sudah_dipindai,
                    ]
                    : null,
            ])->all(),
            'baggage_items' => $booking->itemBagasi->map(fn ($item) => [
                'deskripsi_item' => $item->deskripsi_item,
                'metode_hitung' => $item->metode_hitung,
                'posisi_barang' => $item->posisi_barang ?? 'masuk_bagasi',
                'kategori_ukuran' => $item->kategori_ukuran ?? 'besar',
                'jenis_barang_besar' => $item->jenis_barang_besar,
                'berat_kg' => $item->berat_kg !== null ? (float) $item->berat_kg : null,
                'ukuran_cm' => $item->ukuran_cm,
                'panjang_cm' => $item->panjang_cm,
                'lebar_cm' => $item->lebar_cm,
                'jumlah_item' => $item->jumlah_item,
                'biaya_item' => (float) ($item->biaya_item ?? 0),
                'foto_url' => $item->foto_url,
                'foto_urls' => $this->baggagePhotoUrls($item),
            ])->all(),
        ];
    }

    private function baggagePhotoUrls($item): array
    {
        $urls = collect($item->foto_urls ?? [])
            ->filter(fn ($url) => is_string($url) && trim($url) !== '')
            ->values();

        if ($urls->isEmpty() && is_string($item->foto_url) && trim($item->foto_url) !== '') {
            $urls->push($item->foto_url);
        }

        return $urls->take(3)->map(fn ($url) => $this->publicStorageUrl($url) ?? $url)->all();
    }

    private function publicStorageUrl(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return '/storage/'.ltrim($path, '/');
    }

    private function formatVehicle(?Jadwal $jadwal): ?array
    {
        $kendaraan = $jadwal?->kendaraan;
        if (! $kendaraan) {
            return null;
        }

        return [
            'label' => $kendaraan->label_armada,
            'merk' => $kendaraan->merk_kendaraan,
            'plat' => $kendaraan->nomor_plat,
            'warna' => $kendaraan->warna,
            'tipe' => $kendaraan->tipe_kendaraan,
            'kapasitas' => (int) $kendaraan->kapasitas_kursi,
            'seat_layout' => $kendaraan->getSeatLayoutConfig(),
            'fasilitas' => $kendaraan->fasilitas,
            'foto_url' => $this->publicStorageUrl($kendaraan->foto_kendaraan),
        ];
    }

    private function formatDriver(?Jadwal $jadwal): ?array
    {
        $pengemudi = $jadwal?->pengemudi;
        $pengguna = $pengemudi?->pengguna;
        if (! $pengemudi || ! $pengguna) {
            return null;
        }

        return [
            'nama' => $pengguna->nama,
            'email' => $pengguna->email,
            'no_hp' => $pengguna->no_hp,
            'nomor_sim' => $pengemudi->nomor_sim,
            'status' => $pengemudi->status,
            'foto_url' => $this->publicStorageUrl($pengguna->foto_profil),
        ];
    }
}
