<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:pengguna,email'],
            'no_hp' => ['required', 'string', 'max:20', 'unique:pengguna,no_hp'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->create([
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'no_hp' => $validated['no_hp'],
            'peran' => 'penumpang',
            'status_aktif' => true,
            'password' => $validated['password'],
        ]);

        $token = $user->createToken($validated['device_name'] ?? 'travs-mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil.',
            'data' => [
                'token' => $token,
                'user' => $this->formatUser($user),
            ],
        ], 201);
    }

    /**
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()
            ->where('email', $validated['login'])
            ->orWhere('no_hp', $validated['login'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Email, nomor HP, atau kata sandi tidak sesuai.'],
            ]);
        }

        if (! $user->status_aktif) {
            throw ValidationException::withMessages([
                'login' => ['Akun Anda sedang nonaktif.'],
            ]);
        }

        $token = $user->createToken($validated['device_name'] ?? 'travs-mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token,
                'user' => $this->formatUser($user->load('pengemudiProfile')),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->formatUser($request->user()->load('pengemudiProfile')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /// Endpoint forgot password — membuat token reset dan mengembalikannya.
    /// Pada implementasi production, token dikirim via email/SMS.
    /// Untuk Tugas Akhir ini, token dikembalikan langsung agar bisa diuji
    /// tanpa memerlukan mail server eksternal.
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            // Tidak membocorkan apakah email terdaftar atau tidak (rules #26)
            return response()->json([
                'success' => true,
                'message' => 'Jika email terdaftar, token reset telah dikirim.',
            ]);
        }

        // Hapus token lama jika ada, lalu buat token baru
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar, token reset telah dikirim.',
            'data' => [
                // Token dikembalikan langsung untuk keperluan demo/testing.
                // Di production, token dikirim via email/SMS, bukan di response.
                'reset_token' => $token,
                'expires_in_minutes' => 60,
            ],
        ]);
    }

    /// Endpoint reset password — memvalidasi token dan mengubah password.
    /// Token hanya bisa digunakan sekali dan kedaluwarsa dalam 60 menit.
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'token' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'token' => ['Token reset tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        // Verifikasi token cocok (hashed di database)
        if (! Hash::check($validated['token'], $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['Token reset tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        // Cek kedaluwarsa — 60 menit
        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

            throw ValidationException::withMessages([
                'token' => ['Token reset sudah kedaluwarsa. Silakan minta token baru.'],
            ]);
        }

        // Update password pengguna
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Akun tidak ditemukan.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        // Hapus token setelah digunakan (sekali pakai)
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        // Revoke semua token aktif agar user harus login ulang
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset. Silakan login dengan password baru.',
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            'peran' => $user->peran,
            'foto_profil' => $user->foto_profil,
            'status_aktif' => $user->status_aktif,
            'nomor_sim' => $user->pengemudiProfile?->nomor_sim,
        ];
    }
}
