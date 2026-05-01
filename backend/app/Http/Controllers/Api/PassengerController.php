<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pemesanan;
use App\Models\PengaturanSistem;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PassengerController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->bookingService->releaseExpiredResources();

        $user = $request->user();

        $bookings = Pemesanan::query()
            ->with(['jadwal.rute', 'detailPemesanan.tiketElektronik', 'pembayaran'])
            ->where('pengguna_id', $user->id)
            ->latest()
            ->limit(3)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_booking' => Pemesanan::query()->where('pengguna_id', $user->id)->count(),
                    'booking_aktif' => Pemesanan::query()
                        ->where('pengguna_id', $user->id)
                        ->whereIn('status_pemesanan', ['menunggu_pembayaran', 'terbayar'])
                        ->count(),
                    'tiket_aktif' => Pemesanan::query()
                        ->where('pengguna_id', $user->id)
                        ->where('status_pemesanan', 'terbayar')
                        ->count(),
                    'max_seat_per_booking' => PengaturanSistem::nilai('max_seat_per_booking', 4),
                ],
                'recent_bookings' => $bookings->map(fn (Pemesanan $booking) => [
                    'id' => $booking->id,
                    'kode_booking' => $booking->kode_booking,
                    'status' => $booking->status_pemesanan,
                    'payment_status' => $booking->pembayaran?->status_bayar,
                    'total_harga' => (float) $booking->total_harga,
                    'rute' => $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan,
                    'waktu_berangkat' => $booking->jadwal->waktu_berangkat?->toIso8601String(),
                    'jumlah_tiket' => $booking->detailPemesanan->count(),
                ])->all(),
            ],
        ]);
    }
}
