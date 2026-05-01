<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    public function schedules(Request $request): JsonResponse
    {
        $this->bookingService->releaseExpiredResources();

        $validated = $request->validate([
            'asal' => ['nullable', 'string', 'max:100'],
            'tujuan' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date'],
        ]);

        $query = Jadwal::query()
            ->with(['rute', 'kendaraan', 'pengemudi.pengguna', 'kursi'])
            ->where('status', 'dibuka')
            ->orderBy('waktu_berangkat');

        if (! empty($validated['asal'])) {
            $query->whereHas('rute', fn ($builder) => $builder->where('kota_asal', 'like', '%'.$validated['asal'].'%'));
        }

        if (! empty($validated['tujuan'])) {
            $query->whereHas('rute', fn ($builder) => $builder->where('kota_tujuan', 'like', '%'.$validated['tujuan'].'%'));
        }

        if (! empty($validated['tanggal'])) {
            $query->whereDate('waktu_berangkat', $validated['tanggal']);
        }

        $jadwal = $query->get()->map(function (Jadwal $item) {
            $kursiTersedia = $item->kursi->where('status_kursi', 'tersedia')->count();

            return [
                'id' => $item->id,
                'rute' => [
                    'asal' => $item->rute->kota_asal,
                    'tujuan' => $item->rute->kota_tujuan,
                    'jalur' => $item->rute->deskripsi_jalur,
                ],
                'waktu_berangkat' => $item->waktu_berangkat?->toIso8601String(),
                'estimasi_tiba' => $item->estimasi_tiba?->toIso8601String(),
                'harga_tiket' => (float) $item->rute->harga_tiket,
                'sisa_kursi' => $kursiTersedia,
                'kapasitas' => $item->kendaraan->kapasitas_kursi,
                'estimasi_durasi_menit' => $item->rute->estimasi_durasi_menit,
                'kendaraan' => [
                    'merk' => $item->kendaraan->merk_kendaraan,
                    'plat' => $item->kendaraan->nomor_plat,
                    'warna' => $item->kendaraan->warna,
                    'tipe' => $item->kendaraan->tipe_kendaraan,
                ],
                'pengemudi' => [
                    'nama' => $item->pengemudi->pengguna->nama,
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $jadwal,
        ]);
    }
}
