<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Pemesanan;
use App\Services\BookingCalculator;
use App\Services\BookingService;
use App\Services\PakasirService;
use App\Services\ScheduleLifecycleService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                    'transfer' => (bool) $this->settingService->get('payment_transfer_enabled', false),
                    'qris' => (bool) $this->settingService->get('payment_qris_enabled', false),
                    'cash' => (bool) $this->settingService->get('payment_cash_enabled', false),
                ],
                'bagasi' => [
                    // Nilai legacy tetap dikirim agar aplikasi lama tidak gagal
                    // parsing, tetapi seluruh tarif dinonaktifkan dan bernilai nol.
                    'weight_enabled' => false,
                    'free_weight_kg' => 0,
                    'max_weight_per_person_kg' => 0,
                    'extra_fee_per_kg' => 0,
                    // Ukuran/cm sudah tidak dipakai di alur pemesanan versi ini.
                    // Field tetap dikirim sebagai nilai aman agar aplikasi lama tidak error.
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
            $validated['baggage_items'] ?? [],
            (int) ($validated['passenger_count'] ?? $validated['seat_count']),
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jadwal_id' => ['required', 'integer', 'exists:jadwal,id'],
            'seat_numbers' => ['required', 'array', 'min:1'],
            'seat_numbers.*' => ['required', 'integer', 'min:1'],
            'pickup' => ['required', 'array'],
            'pickup.latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup.longitude' => ['required', 'numeric', 'between:-180,180'],
            'pickup.address' => ['required', 'string', 'max:1000'],
            'pickup.note' => ['nullable', 'string', 'max:500'],
            'dropoff' => ['required', 'array'],
            'dropoff.latitude' => ['required', 'numeric', 'between:-90,90'],
            'dropoff.longitude' => ['required', 'numeric', 'between:-180,180'],
            'dropoff.address' => ['required', 'string', 'max:1000'],
            'dropoff.note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'string', 'in:transfer,qris,cash'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.seat_number' => ['required', 'integer', 'min:1'],
            'passengers.*.nama_penumpang' => ['required', 'string', 'max:100'],
            'passengers.*.no_hp_penumpang' => ['required', 'string', 'max:20'],
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
        ], [
            'baggage_items.*.foto_urls.max' => 'Foto barang bawaan maksimal 3 foto untuk setiap pemesanan.',
            'pickup.address.required' => 'Alamat titik jemput wajib diisi.',
            'dropoff.address.required' => 'Alamat titik turun wajib diisi.',
            'passengers.*.nama_penumpang.required' => 'Nama penumpang wajib diisi.',
            'passengers.*.no_hp_penumpang.required' => 'Nomor HP penumpang wajib diisi.',
        ]);
        $booking = $this->bookingService->createDraft($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Draft pemesanan berhasil dibuat.',
            'data' => $this->formatBooking($booking),
        ], 201);
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
                'jadwal.rute',
                'jadwal.kendaraan',
                'jadwal.pengemudi.pengguna',
                'detailPemesanan.tiketElektronik',
                'pembayaran',
            ])
            ->where('pengguna_id', $request->user()->id)
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
        abort_unless($booking->pengguna_id === $request->user()->id, 404);

        $booking->load([
            'pengguna',
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
        abort_unless($booking->pengguna_id === $request->user()->id, 404);

        $booking = $this->bookingService->refreshGatewayPaymentStatus($booking, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Status pembayaran berhasil diperbarui.',
            'data' => $this->formatBooking($booking, true),
        ]);
    }

    private function formatBooking(Pemesanan $booking, bool $full = false): array
    {
        $gatewayMeta = $this->pakasirService->decodeGatewayResponse($booking->pembayaran?->gateway_response);
        $createPayment = data_get($gatewayMeta, 'create_payment', []);
        $transactionDetail = data_get($gatewayMeta, 'transaction_detail', []);
        $paymentNumber = (string) data_get($createPayment, 'payment_number', $booking->pembayaran?->gateway_reference);
        $amount = (float) data_get($createPayment, 'amount', $booking->total_harga);
        $fee = (float) data_get($createPayment, 'fee', 0);
        $totalPayment = (float) data_get($createPayment, 'total_payment', $booking->pembayaran?->jumlah_bayar);

        // Tautan ini membuka halaman Pakasir agar pembayaran diselesaikan
        // melalui browser, bukan diproses langsung oleh aplikasi mobile.
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
            && $this->pakasirService->handlesMethod((string) $booking->pembayaran?->metode_bayar)
            && $this->pakasirService->isConfigured()
        ) {
            $checkoutUrl = $this->pakasirService->checkoutUrl(
                $booking->kode_booking,
                $booking->total_harga,
                $booking->pembayaran?->metode_bayar === 'qris',
                $this->pakasirService->merchantReturnUrl(),
            );
        }

        $paymentNumber = (string) data_get(
            $createPayment,
            'payment_number',
            $booking->pembayaran?->gateway_reference,
        );

        $response = [
            'id' => $booking->id,
            'kode_booking' => $booking->kode_booking,
            'status' => $booking->status_pemesanan,
            'jumlah_kursi' => $booking->jumlah_kursi,
            'batas_pembayaran' => $booking->batas_pembayaran?->toIso8601String(),
            'subtotal_tiket' => (float) $booking->subtotal_tiket,
            'biaya_bagasi' => (float) $booking->biaya_bagasi,
            'diskon' => (float) $booking->diskon,
            'total_harga' => (float) $booking->total_harga,
            'pemesan' => [
                'nama' => $booking->pengguna?->nama,
                'email' => $booking->pengguna?->email,
                'no_hp' => $booking->pengguna?->no_hp,
                'foto_url' => $this->publicStorageUrl($booking->pengguna?->foto_profil),
            ],
            'rute' => $booking->jadwal?->rute
                ? $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan
                : null,
            'waktu_berangkat' => $booking->jadwal?->waktu_berangkat?->toIso8601String(),
            'kendaraan' => $this->formatVehicle($booking->jadwal),
            'pengemudi' => $this->formatDriver($booking->jadwal),
            'payment' => [
                'status' => $booking->pembayaran?->status_bayar,
                'method' => $booking->pembayaran?->metode_bayar,
                'expired_at' => $booking->pembayaran?->expired_at?->toIso8601String(),
                // Waktu pembayaran berasal dari catatan backend, bukan perkiraan mobile.
                'paid_at' => $booking->pembayaran?->dibayar_pada?->toIso8601String(),
                'can_simulate' => app()->environment(['local', 'testing']),
                'uses_gateway' => $this->pakasirService->handlesMethod((string) $booking->pembayaran?->metode_bayar) && $this->pakasirService->isConfigured(),
                'provider' => data_get($gatewayMeta, 'provider'),
                'project' => data_get($createPayment, 'project'),
                'amount' => $amount,
                'fee' => $fee,
                'total_payment' => $totalPayment,
                'payment_number' => $paymentNumber,
                'checkout_url' => $checkoutUrl,
                'gateway_status' => data_get($transactionDetail, 'status'),
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
