<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiayaOperasionalPerjalanan;
use App\Models\DetailPemesanan;
use App\Models\Jadwal;
use App\Models\Pemesanan;
use App\Models\Pengemudi;
use App\Models\TiketElektronik;
use App\Services\BookingService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly ScheduleLifecycleService $scheduleLifecycleService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->scheduleLifecycleService->closeOpenSchedulesNearDeparture();
        $pengemudi = $this->activeDriverFor($request);

        // Jadwal yang ditutup tetap jadi arsip, tapi tidak tampil sebagai tugas aktif
        // supaya pengemudi hanya fokus ke perjalanan yang masih bisa dijalankan.
        $jadwalAktif = $this->driverTripsQuery($pengemudi)
            ->whereIn('status', ['dibuka', 'berangkat'])
            ->get();
        $assignedVehicleTrip = $this->assignedVehicleTrip($pengemudi);

        return response()->json([
            'success' => true,
            'data' => [
                'akun' => [
                    'nama' => $request->user()->nama,
                    'email' => $request->user()->email,
                    'no_hp' => $request->user()->no_hp,
                    'role' => $request->user()->peran,
                    'foto_profil_url' => $this->publicStorageUrl($request->user()->foto_profil),
                ],
                'identitas_pengemudi' => [
                    'nomor_sim' => $pengemudi->nomor_sim,
                    'foto_sim_url' => $this->publicStorageUrl($pengemudi->foto_sim),
                    'status' => $pengemudi->status,
                    'status_verifikasi' => $pengemudi->status_verifikasi,
                ],
                'kendaraan_ditugaskan' => $assignedVehicleTrip
                    ? $this->formatAssignedVehicle($assignedVehicleTrip)
                    : null,
                'assigned_vehicle' => $assignedVehicleTrip
                    ? $this->formatAssignedVehicle($assignedVehicleTrip)
                    : null,
                'kendaraan_ditugaskan_message' => $assignedVehicleTrip
                    ? null
                    : 'Belum ada kendaraan yang ditugaskan. Kendaraan akan tampil setelah admin membuat jadwal perjalanan.',
                'summary' => [
                    'jadwal_aktif' => $jadwalAktif->count(),
                    'penumpang_terdaftar' => $jadwalAktif->sum(
                        fn (Jadwal $jadwal) => $this->manifestPassengers($jadwal)->count(),
                    ),
                ],
                'upcoming_trips' => $jadwalAktif->map(fn (Jadwal $jadwal) => $this->formatTrip($jadwal))->all(),
            ],
        ]);
    }

    public function trips(Request $request): JsonResponse
    {
        $this->scheduleLifecycleService->closeOpenSchedulesNearDeparture();
        $pengemudi = $this->activeDriverFor($request);

        $trips = $this->driverTripsQuery($pengemudi)->get();

        return response()->json([
            'success' => true,
            'data' => $trips->map(fn (Jadwal $jadwal) => $this->formatTrip($jadwal))->all(),
        ]);
    }

    public function manifest(Request $request, Jadwal $jadwal): JsonResponse
    {
        $this->ensureDriverOwnsTrip($request, $jadwal);
        $this->scheduleLifecycleService->closeSingleIfPastBookingWindow($jadwal);

        $jadwal->load($this->tripRelations());

        return response()->json([
            'success' => true,
            'data' => $this->formatTrip($jadwal),
        ]);
    }

    public function scanTicket(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'token' => ['required', 'string', 'max:160'],
        ]);

        $pengemudi = $this->activeDriverFor($request);

        $result = DB::transaction(function () use ($payload, $pengemudi) {
            $ticket = TiketElektronik::query()
                ->lockForUpdate()
                ->with([
                    'detailPemesanan.pemesanan.jadwal.rute',
                    'detailPemesanan.pemesanan.jadwal.kendaraan',
                    'detailPemesanan.pemesanan.jadwal.pengemudi',
                    'detailPemesanan.pemesanan.itemBagasi',
                    'detailPemesanan.pemesanan.pengguna',
                ])
                ->where('token_unik_qr', $payload['token'])
                ->first();

            if (! $ticket || ! $ticket->detailPemesanan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tiket tidak ditemukan.',
                ], 404);
            }

            $detail = $ticket->detailPemesanan;
            $booking = $detail->pemesanan;
            $jadwal = $booking?->jadwal;

            if (! $booking || ! $jadwal || (int) $jadwal->pengemudi_id !== (int) $pengemudi->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tiket ini bukan bagian dari manifest pengemudi aktif.',
                ], 403);
            }

            if ($booking->status_pemesanan !== 'terbayar') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tiket belum aktif karena pemesanan belum berstatus terbayar.',
                ], 422);
            }

            if ($ticket->sudah_dipindai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tiket sudah pernah dipindai.',
                    'data' => [
                        'valid' => false,
                        'scanned_at' => $ticket->dipindai_pada?->toIso8601String(),
                    ],
                ], 409);
            }

            $now = now();

            $ticket->update([
                'sudah_dipindai' => true,
                'dipindai_pada' => $now,
            ]);

            $detail->update([
                'status_kehadiran' => 'hadir',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tiket valid dan berhasil dipindai.',
                'data' => [
                    'valid' => true,
                    'scanned_at' => $now->toIso8601String(),
                    'trip' => $this->formatTripSummary($jadwal),
                    'passenger' => $this->formatManifestPassenger($booking, $detail),
                ],
            ]);
        });

        return $result;
    }

    public function markPassengerPickup(Request $request, Jadwal $jadwal, DetailPemesanan $detail): JsonResponse
    {
        $this->ensureDriverOwnsTrip($request, $jadwal);

        $passenger = DB::transaction(function () use ($jadwal, $detail) {
            $lockedDetail = DetailPemesanan::query()
                ->lockForUpdate()
                ->with([
                    'tiketElektronik',
                    'pemesanan.itemBagasi',
                    'pemesanan.pengguna',
                    'pemesanan.jadwal.rute',
                    'pemesanan.jadwal.kendaraan',
                ])
                ->whereKey($detail->id)
                ->whereHas('pemesanan', fn ($query) => $this->scopeManifestVisibleBookings(
                    $query->where('jadwal_id', $jadwal->id),
                ))
                ->first();

            if (! $lockedDetail || ! $lockedDetail->pemesanan) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Penumpang tidak ditemukan pada manifest aktif.',
                ], 404));
            }

            if ($lockedDetail->status_kehadiran !== 'menunggu') {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Penumpang sudah boarding atau tidak dapat ditandai pickup.',
                ], 422));
            }

            if (! $lockedDetail->dijemput_pada) {
                $lockedDetail->update(['dijemput_pada' => now()]);
            }

            return $this->formatManifestPassenger(
                $lockedDetail->pemesanan,
                $lockedDetail->fresh(['tiketElektronik']),
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Status pickup penumpang berhasil ditandai.',
            'data' => $passenger,
        ]);
    }

    public function confirmPassengerCash(Request $request, Jadwal $jadwal, DetailPemesanan $detail): JsonResponse
    {
        $this->ensureDriverOwnsTrip($request, $jadwal);

        $detail->load('pemesanan.pembayaran');
        $booking = $detail->pemesanan;

        if (! $booking || (int) $booking->jadwal_id !== (int) $jadwal->id) {
            return response()->json([
                'success' => false,
                'message' => 'Penumpang tidak ditemukan pada manifest pengemudi.',
            ], 404);
        }

        $updatedBooking = $this->bookingService->confirmCashPaymentByDriver(
            $booking,
            $request->user(),
        );

        $updatedDetail = $updatedBooking->detailPemesanan
            ->firstWhere('id', $detail->id)
            ?? $updatedBooking->detailPemesanan()->with('tiketElektronik')->findOrFail($detail->id);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran tunai berhasil dikonfirmasi. Tiket digital sudah diterbitkan.',
            'data' => $this->formatManifestPassenger($updatedBooking, $updatedDetail),
        ]);
    }

    public function markPassengerDropoff(Request $request, Jadwal $jadwal, DetailPemesanan $detail): JsonResponse
    {
        $this->ensureDriverOwnsTrip($request, $jadwal);

        $passenger = DB::transaction(function () use ($jadwal, $detail) {
            $lockedDetail = DetailPemesanan::query()
                ->lockForUpdate()
                ->with([
                    'tiketElektronik',
                    'pemesanan.detailPemesanan',
                    'pemesanan.itemBagasi',
                    'pemesanan.pengguna',
                    'pemesanan.pembayaran',
                    'pemesanan.jadwal.rute',
                    'pemesanan.jadwal.kendaraan',
                ])
                ->whereKey($detail->id)
                ->whereHas('pemesanan', fn ($query) => $query
                    ->where('jadwal_id', $jadwal->id)
                    ->whereIn('status_pemesanan', ['terbayar', 'selesai']))
                ->first();

            if (! $lockedDetail || ! $lockedDetail->pemesanan) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Penumpang tidak ditemukan pada manifest aktif.',
                ], 404));
            }

            if ($lockedDetail->status_kehadiran !== 'hadir') {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Penumpang harus scan tiket dulu sebelum ditandai turun.',
                ], 422));
            }

            if (! $lockedDetail->diturunkan_pada) {
                $lockedDetail->update(['diturunkan_pada' => now()]);
                $lockedDetail->refresh();
            }

            $booking = $lockedDetail->pemesanan;
            $booking->load('detailPemesanan');

            // Jika semua kursi pada satu pemesanan sudah turun, pemesanan masuk riwayat selesai.
            $allPassengersDroppedOff = $booking->detailPemesanan->every(
                fn (DetailPemesanan $bookingDetail) => $bookingDetail->diturunkan_pada !== null,
            );

            if ($booking->status_pemesanan === 'terbayar' && $allPassengersDroppedOff) {
                $booking->update(['status_pemesanan' => 'selesai']);
            }

            $hasActivePaidBooking = Pemesanan::query()
                ->where('jadwal_id', $jadwal->id)
                ->where('status_pemesanan', 'terbayar')
                ->exists();

            if (! $hasActivePaidBooking && in_array($jadwal->status, ['dibuka', 'ditutup', 'berangkat'], true)) {
                Jadwal::query()
                    ->whereKey($jadwal->id)
                    ->update(['status' => 'selesai']);
            }

            return $this->formatManifestPassenger(
                $booking->fresh(['itemBagasi', 'pengguna', 'pembayaran', 'jadwal.rute', 'jadwal.kendaraan']),
                $lockedDetail->fresh(['tiketElektronik']),
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Penumpang berhasil ditandai turun.',
            'data' => $passenger,
        ]);
    }

    public function updateTripStatus(Request $request, Jadwal $jadwal): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', Rule::in(['berangkat', 'selesai'])],
        ]);

        $this->ensureDriverOwnsTrip($request, $jadwal);

        $jadwal = DB::transaction(function () use ($jadwal, $payload) {
            $lockedTrip = Jadwal::query()
                ->lockForUpdate()
                ->findOrFail($jadwal->id);

            $allowed = [
                'dibuka' => ['berangkat'],
                'ditutup' => ['berangkat'],
                'berangkat' => ['selesai'],
            ];

            $target = $payload['status'];
            if (! in_array($target, $allowed[$lockedTrip->status] ?? [], true)) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Status perjalanan tidak dapat diloncati.',
                ], 422));
            }

            $lockedTrip->update(['status' => $target]);

            if ($target === 'selesai') {
                Pemesanan::query()
                    ->where('jadwal_id', $lockedTrip->id)
                    ->where('status_pemesanan', 'terbayar')
                    ->update(['status_pemesanan' => 'selesai']);
            }

            return $lockedTrip->fresh($this->tripRelations());
        });

        return response()->json([
            'success' => true,
            'message' => 'Status perjalanan berhasil diperbarui.',
            'data' => $this->formatTrip($jadwal),
        ]);
    }

    private function activeDriverFor(Request $request): Pengemudi
    {
        $pengemudi = Pengemudi::query()
            ->where('pengguna_id', $request->user()->id)
            ->firstOrFail();

        if ($pengemudi->status !== 'aktif') {
            abort(response()->json([
                'success' => false,
                'message' => 'Akun pengemudi belum aktif.',
            ], 403));
        }

        return $pengemudi;
    }

    private function ensureDriverOwnsTrip(Request $request, Jadwal $jadwal): Pengemudi
    {
        $pengemudi = $this->activeDriverFor($request);

        if ((int) $jadwal->pengemudi_id !== (int) $pengemudi->id) {
            abort(response()->json([
                'success' => false,
                'message' => 'Perjalanan ini bukan milik pengemudi aktif.',
            ], 403));
        }

        return $pengemudi;
    }

    private function driverTripsQuery(Pengemudi $pengemudi)
    {
        return Jadwal::query()
            ->with($this->tripRelations())
            ->where('pengemudi_id', $pengemudi->id)
            ->orderBy('waktu_berangkat');
    }

    private function assignedVehicleTrip(Pengemudi $pengemudi): ?Jadwal
    {
        // Kendaraan bukan bagian permanen dari profil pengemudi.
        // Armada ditentukan lewat jadwal agar admin fleksibel memakai kendaraan perusahaan, pribadi, atau mitra.
        return $this->driverTripsQuery($pengemudi)
            ->whereIn('status', ['draft', 'dibuka', 'ditutup', 'berangkat'])
            ->orderByRaw('CASE WHEN waktu_berangkat >= ? THEN 0 ELSE 1 END', [now()->toDateTimeString()])
            ->orderBy('waktu_berangkat')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function tripRelations(): array
    {
        return [
            'rute',
            'kendaraan',
            'pemesanan' => fn ($query) => $query
                ->where(fn ($bookingQuery) => $this->scopeManifestVisibleBookings($bookingQuery))
                ->with(['pengguna', 'detailPemesanan.tiketElektronik', 'itemBagasi', 'pembayaran']),
        ];
    }

    private function scopeManifestVisibleBookings($query)
    {
        return $query->where(function ($visibleQuery) {
            $visibleQuery
                ->whereIn('status_pemesanan', ['terbayar', 'selesai'])
                ->orWhere(function ($cashQuery) {
                    $cashQuery
                        ->where('status_pemesanan', 'menunggu_pembayaran')
                        ->whereHas('pembayaran', fn ($paymentQuery) => $paymentQuery
                            ->where('metode_bayar', 'cash')
                            ->where('status_bayar', 'menunggu'));
                });
        });
    }

    private function formatTrip(Jadwal $jadwal): array
    {
        return [
            ...$this->formatTripSummary($jadwal),
            'manifest' => $this->manifestPassengers($jadwal)
                ->map(fn (array $record) => $record)
                ->values()
                ->all(),
        ];
    }

    private function formatTripSummary(Jadwal $jadwal): array
    {
        return [
            'id' => $jadwal->id,
            'code' => 'DRV-'.$jadwal->id,
            'rute' => $jadwal->rute->kota_asal.' - '.$jadwal->rute->kota_tujuan,
            'durasi_menit' => (int) $jadwal->rute->estimasi_durasi_menit,
            'durasi_label' => $jadwal->rute->durasi_label,
            'waktu_berangkat' => $jadwal->waktu_berangkat?->toIso8601String(),
            'estimasi_tiba' => $jadwal->estimasi_tiba?->toIso8601String(),
            'kendaraan' => $jadwal->kendaraan->label_armada,
            'kendaraan_nama' => $jadwal->kendaraan->merk_kendaraan,
            'nomor_plat' => $jadwal->kendaraan->nomor_plat,
            'kendaraan_tipe' => $jadwal->kendaraan->tipe_kendaraan,
            'kendaraan_warna' => $jadwal->kendaraan->warna,
            'kapasitas_kursi' => (int) $jadwal->kendaraan->kapasitas_kursi,
            'kendaraan_foto_url' => $this->publicStorageUrl($jadwal->kendaraan?->foto_kendaraan),
            'status' => $jadwal->status,
            'passenger_count' => $this->manifestPassengers($jadwal)->count(),
            'revenue' => $jadwal->pemesanan->sum(fn (Pemesanan $booking) => (float) $booking->total_harga),
        ];
    }

    private function formatAssignedVehicle(Jadwal $jadwal): array
    {
        $jadwal->loadMissing(['rute', 'kendaraan']);
        $kendaraan = $jadwal->kendaraan;

        return [
            'id_jadwal' => $jadwal->id,
            'rute' => $jadwal->rute
                ? $jadwal->rute->kota_asal.' - '.$jadwal->rute->kota_tujuan
                : '-',
            'waktu_berangkat' => $jadwal->waktu_berangkat?->toIso8601String(),
            'estimasi_tiba' => $jadwal->estimasi_tiba?->toIso8601String(),
            'nomor_plat' => $kendaraan?->nomor_plat,
            'label' => $kendaraan?->label_armada,
            'merek' => $kendaraan?->merk_kendaraan,
            'merk_kendaraan' => $kendaraan?->merk_kendaraan,
            'tipe' => $kendaraan?->tipe_kendaraan,
            'tipe_kendaraan' => $kendaraan?->tipe_kendaraan,
            'warna' => $kendaraan?->warna,
            'kapasitas' => (int) ($kendaraan?->kapasitas_kursi ?? 0),
            'kapasitas_kursi' => (int) ($kendaraan?->kapasitas_kursi ?? 0),
            'foto_url' => $this->publicStorageUrl($kendaraan?->foto_kendaraan),
            'foto_kendaraan_url' => $this->publicStorageUrl($kendaraan?->foto_kendaraan),
            'status_kendaraan' => $kendaraan?->status_kendaraan,
            'status_jadwal' => $jadwal->status,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function manifestPassengers(Jadwal $jadwal): Collection
    {
        return $jadwal->pemesanan
            ->flatMap(fn (Pemesanan $booking) => $booking->detailPemesanan->map(
                fn ($detail) => $this->formatManifestPassenger($booking, $detail),
            ))
            ->sortBy('nomor_kursi')
            ->values();
    }

    private function formatManifestPassenger(Pemesanan $booking, $detail): array
    {
        $baggageCollection = $booking->itemBagasi;
        $storedBaggageItems = $baggageCollection->filter(fn ($item) => ($item->posisi_barang ?? 'masuk_bagasi') === 'masuk_bagasi');
        $heldBaggageItems = $baggageCollection->filter(fn ($item) => ($item->posisi_barang ?? 'masuk_bagasi') === 'dipegang_sendiri');

        $baggageItems = $storedBaggageItems->sum('jumlah_item');
        $heldItems = $heldBaggageItems->sum('jumlah_item');
        $baggageWeight = $storedBaggageItems->sum(fn ($item) => (float) $item->berat_kg);
        // Manifest menampilkan nama barang; jumlah dan berat legacy tidak lagi dipakai pada UI pengemudi.
        $baggageLabel = $baggageCollection
            ->pluck('deskripsi_item')
            ->map(fn ($description) => trim((string) $description))
            ->filter()
            ->unique()
            ->implode(', ');
        $baggagePhotoUrls = $this->baggagePhotoUrls($booking);
        $ticket = $detail->tiketElektronik;
        $paymentStatus = $booking->pembayaran?->status_bayar;
        $paymentMethod = $booking->pembayaran?->metode_bayar;
        $isCashPending = $booking->status_pemesanan === 'menunggu_pembayaran'
            && $paymentMethod === 'cash'
            && $paymentStatus === 'menunggu';

        return [
            'booking_id' => $booking->id,
            'kode_booking' => $booking->kode_booking,
            'status_pemesanan' => $booking->status_pemesanan,
            'detail_id' => $detail->id,
            'nama_penumpang' => $detail->nama_penumpang,
            'no_hp_penumpang' => $detail->no_hp_penumpang,
            'whatsapp_url_penumpang' => $this->whatsappUrl($detail->no_hp_penumpang),
            'akun_pemesan' => [
                'nama' => $booking->pengguna?->nama,
                'email' => $booking->pengguna?->email,
                'no_hp' => $booking->pengguna?->no_hp,
                'whatsapp_url' => $this->whatsappUrl($booking->pengguna?->no_hp),
                'foto_url' => $this->publicStorageUrl($booking->pengguna?->foto_profil),
            ],
            'nomor_kursi' => (int) $detail->nomor_kursi,
            'status_kehadiran' => $detail->status_kehadiran,
            'pickup' => [
                'latitude' => $booking->lintang_jemput !== null ? (float) $booking->lintang_jemput : null,
                'longitude' => $booking->bujur_jemput !== null ? (float) $booking->bujur_jemput : null,
                'address' => $booking->alamat_jemput,
                'is_picked_up' => $detail->dijemput_pada !== null,
                'picked_up_at' => $detail->dijemput_pada?->toIso8601String(),
            ],
            'dropoff' => [
                'latitude' => $booking->lintang_turun !== null ? (float) $booking->lintang_turun : null,
                'longitude' => $booking->bujur_turun !== null ? (float) $booking->bujur_turun : null,
                'address' => $booking->alamat_turun,
                'is_dropped_off' => $detail->diturunkan_pada !== null,
                'dropped_off_at' => $detail->diturunkan_pada?->toIso8601String(),
            ],
            'baggage' => [
                'items' => (int) $baggageItems,
                'held_items' => (int) $heldItems,
                'weight_kg' => $baggageWeight,
                'label' => $baggageLabel !== '' ? $baggageLabel : 'Tidak ada barang bawaan tercatat',
                'foto_url' => $baggagePhotoUrls[0] ?? null,
                'foto_urls' => $baggagePhotoUrls,
                'items_detail' => $booking->itemBagasi->map(fn ($item) => [
                    'deskripsi_item' => $item->deskripsi_item,
                    'posisi_barang' => $item->posisi_barang ?? 'masuk_bagasi',
                    'kategori_ukuran' => $item->kategori_ukuran ?? 'besar',
                    'jenis_barang_besar' => $item->jenis_barang_besar,
                    'berat_kg' => $item->berat_kg !== null ? (float) $item->berat_kg : null,
                    'jumlah_item' => (int) $item->jumlah_item,
                    'biaya_item' => (float) ($item->biaya_item ?? 0),
                ])->values()->all(),
            ],
            'fare' => [
                'total' => (float) $booking->total_harga,
                'label' => 'Rp'.number_format((float) $booking->total_harga, 0, ',', '.'),
            ],
            'pembayaran' => [
                'status' => $paymentStatus,
                'metode' => $paymentMethod,
                'channel' => $booking->pembayaran?->paid_channel,
                'total' => (float) ($booking->pembayaran?->jumlah_bayar ?? $booking->total_harga),
                'label' => 'Rp'.number_format((float) ($booking->pembayaran?->jumlah_bayar ?? $booking->total_harga), 0, ',', '.'),
                'is_cash_pending' => $isCashPending,
                'can_confirm_cash' => $isCashPending,
            ],
            'ticket' => $ticket ? [
                'kode_qr' => $ticket->kode_qr,
                'token' => $ticket->token_unik_qr,
                'sudah_dipindai' => (bool) $ticket->sudah_dipindai,
                'dipindai_pada' => $ticket->dipindai_pada?->toIso8601String(),
            ] : null,
        ];
    }

    private function baggagePhotoUrls(Pemesanan $booking): array
    {
        return $booking->itemBagasi
            ->flatMap(function ($item) {
                $photos = is_array($item->foto_urls) ? $item->foto_urls : [];

                if ($item->foto_url && ! in_array($item->foto_url, $photos, true)) {
                    $photos[] = $item->foto_url;
                }

                return collect($photos)
                    ->filter(fn ($path) => is_string($path) && trim($path) !== '')
                    ->map(fn ($path) => $this->publicStorageUrl($path));
            })
            ->filter()
            ->unique()
            ->take(3)
            ->values()
            ->all();
    }

    private function whatsappUrl(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return strlen($digits) >= 10 ? 'https://wa.me/'.$digits : null;
    }

    public function uploadDriverPhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($user->foto_profil) {
            Storage::disk('public')->delete($user->foto_profil);
        }

        $path = $validated['foto']->store('profiles', 'public');

        $user->update(['foto_profil' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Foto profil pengemudi berhasil diunggah.',
            'data' => [
                'foto_profil' => $path,
                'foto_profil_url' => $this->publicStorageUrl($path),
            ],
        ]);
    }

    public function uploadAssignedVehiclePhoto(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Data kendaraan hanya dapat diubah oleh admin melalui menu Armada Kendaraan.',
        ], 403);
    }

    public function storeOperationalExpense(Request $request, Jadwal $jadwal): JsonResponse
    {
        $pengemudi = $this->activeDriverFor($request);

        if ((int) $jadwal->pengemudi_id !== (int) $pengemudi->id) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal ini bukan tugas pengemudi yang sedang login.',
            ], 403);
        }

        if (! in_array($jadwal->status, ['dibuka', 'ditutup', 'berangkat'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Biaya hanya dapat dicatat pada jadwal tugas yang sedang aktif.',
            ], 422);
        }

        $validated = $request->validate([
            'jenis_biaya' => ['required', 'string', 'max:100'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['required', 'string', 'min:10', 'max:1000'],
            'foto_bukti_nota' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], [
            'jenis_biaya.required' => 'Jenis biaya wajib diisi.',
            'nominal.required' => 'Nominal biaya wajib diisi.',
            'nominal.min' => 'Nominal biaya minimal Rp1.',
            'tanggal.required' => 'Tanggal biaya wajib diisi.',
            'keterangan.required' => 'Keterangan biaya wajib diisi.',
            'keterangan.min' => 'Keterangan biaya minimal 10 karakter.',
            'foto_bukti_nota.required' => 'Foto bukti nota wajib dilampirkan.',
            'foto_bukti_nota.image' => 'Bukti nota harus berupa gambar.',
            'foto_bukti_nota.mimes' => 'Bukti nota harus JPG, PNG, atau WEBP.',
            'foto_bukti_nota.max' => 'Ukuran bukti nota maksimal 3 MB.',
        ]);

        $buktiPath = $request->file('foto_bukti_nota')->store('driver-expenses', 'public');

        // Biaya langsung menjadi pencatatan final. Status internal disetujui
        // dipertahankan agar query laporan lama tetap dapat membaca data ini.
        $expense = BiayaOperasionalPerjalanan::query()->create([
            'jadwal_id' => $jadwal->id,
            'jenis_biaya' => $validated['jenis_biaya'],
            'nominal' => $validated['nominal'],
            'tanggal_biaya' => $validated['tanggal'],
            'keterangan' => $validated['keterangan'] ?? null,
            'bukti_url' => $buktiPath,
            'created_by' => $request->user()->id,
            'status_verifikasi' => 'disetujui',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Biaya operasional berhasil dicatat.',
            'data' => [
                'id' => $expense->id,
                'status' => 'tercatat',
                // Field lama tetap dikirim untuk kompatibilitas aplikasi lama.
                'status_verifikasi' => $expense->status_verifikasi,
                'bukti_url' => $this->publicStorageUrl($expense->bukti_url),
            ],
        ], 201);
    }

    private function publicStorageUrl(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return '/storage/'.ltrim($path, '/');
    }
}
