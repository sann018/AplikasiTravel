<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Pengemudi;
use App\Models\Pemesanan;
use App\Models\TiketElektronik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $pengemudi = $this->activeDriverFor($request);

        $jadwalAktif = $this->driverTripsQuery($pengemudi)
            ->whereIn('status', ['dibuka', 'berangkat'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
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
                    'message' => 'Tiket belum aktif karena booking belum berstatus terbayar.',
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

    /**
     * @return array<int, string>
     */
    private function tripRelations(): array
    {
        return [
            'rute',
            'kendaraan',
            'pemesanan' => fn ($query) => $query
                ->whereIn('status_pemesanan', ['terbayar', 'selesai'])
                ->with(['detailPemesanan.tiketElektronik', 'itemBagasi', 'pembayaran']),
        ];
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
            'waktu_berangkat' => $jadwal->waktu_berangkat?->toIso8601String(),
            'estimasi_tiba' => $jadwal->estimasi_tiba?->toIso8601String(),
            'kendaraan' => $jadwal->kendaraan->merk_kendaraan.' '.$jadwal->kendaraan->nomor_plat,
            'status' => $jadwal->status,
            'passenger_count' => $this->manifestPassengers($jadwal)->count(),
            'revenue' => $jadwal->pemesanan->sum(fn (Pemesanan $booking) => (float) $booking->total_harga),
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
        $baggageItems = $booking->itemBagasi->sum('jumlah_item');
        $baggageWeight = $booking->itemBagasi->sum(fn ($item) => (float) $item->berat_kg);
        $ticket = $detail->tiketElektronik;

        return [
            'booking_id' => $booking->id,
            'kode_booking' => $booking->kode_booking,
            'detail_id' => $detail->id,
            'nama_penumpang' => $detail->nama_penumpang,
            'no_hp_penumpang' => $detail->no_hp_penumpang,
            'nomor_kursi' => (int) $detail->nomor_kursi,
            'status_kehadiran' => $detail->status_kehadiran,
            'pickup' => [
                'latitude' => $booking->lintang_jemput !== null ? (float) $booking->lintang_jemput : null,
                'longitude' => $booking->bujur_jemput !== null ? (float) $booking->bujur_jemput : null,
                'address' => $booking->alamat_jemput,
            ],
            'baggage' => [
                'items' => (int) $baggageItems,
                'weight_kg' => $baggageWeight,
                'label' => $baggageItems > 0
                    ? "{$baggageItems} item / {$baggageWeight} kg"
                    : 'Tidak ada bagasi tercatat',
            ],
            'fare' => [
                'total' => (float) $booking->total_harga,
                'label' => 'Rp'.number_format((float) $booking->total_harga, 0, ',', '.'),
            ],
            'ticket' => $ticket ? [
                'kode_qr' => $ticket->kode_qr,
                'token' => $ticket->token_unik_qr,
                'sudah_dipindai' => (bool) $ticket->sudah_dipindai,
                'dipindai_pada' => $ticket->dipindai_pada?->toIso8601String(),
            ] : null,
        ];
    }
}
