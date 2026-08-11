<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pemesanan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetugasLoketController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $baseQuery = Pemesanan::query()
            ->where('sumber_pemesanan', 'loket')
            ->where('dibuat_oleh', $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => 'petugas_loket',
                'total_pemesanan' => (clone $baseQuery)->count(),
                'menunggu_pembayaran' => (clone $baseQuery)
                    ->where('status_pemesanan', 'menunggu_pembayaran')
                    ->count(),
                'terbayar' => (clone $baseQuery)
                    ->whereIn('status_pemesanan', ['terbayar', 'selesai'])
                    ->count(),
            ],
        ]);
    }

    public function searchPassenger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:100'],
        ], [
            'query.required' => 'Masukkan email atau nomor HP penumpang secara lengkap.',
        ]);

        $query = trim((string) $validated['query']);
        $passenger = User::query()
            ->where('peran', 'penumpang')
            ->where(function ($builder) use ($query) {
                $builder->where('email', mb_strtolower($query))
                    ->orWhere('no_hp', $query);
            })
            ->first();

        return response()->json([
            'success' => true,
            'data' => $passenger ? [
                'id' => $passenger->id,
                'nama' => $passenger->nama,
                'email' => $passenger->email,
                'no_hp' => $passenger->no_hp,
            ] : null,
            'message' => $passenger
                ? 'Akun penumpang ditemukan.'
                : 'Akun penumpang tidak ditemukan. Pemesanan tetap dapat dilanjutkan tanpa akun.',
        ]);
    }
}
