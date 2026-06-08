<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum valid.',
            'email.unique' => 'Email sudah terdaftar. Silakan masuk atau gunakan email lain.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
            'no_hp.unique' => 'Nomor HP sudah terdaftar. Silakan masuk atau gunakan nomor lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi belum sama.',
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
                'user' => $this->formatUser($user, $request),
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
                'user' => $this->formatUser($user->load('pengemudiProfile'), $request),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->formatUser($request->user()->load('pengemudiProfile'), $request),
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

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $photoField = $this->profilePhotoField($request);

        $validated = $request->validate([
            'nama' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:100', 'unique:pengguna,email,'.$user->id],
            'no_hp' => ['sometimes', 'string', 'max:20', 'unique:pengguna,no_hp,'.$user->id],
            'foto_profil' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'foto' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'email.email' => 'Format email belum valid.',
            'email.unique' => 'Email sudah dipakai akun lain.',
            'no_hp.unique' => 'Nomor HP sudah dipakai akun lain.',
            'foto_profil.image' => 'File foto profil harus berupa gambar.',
            'foto_profil.mimes' => 'Format foto profil harus JPG, JPEG, PNG, atau WEBP.',
            'foto_profil.max' => 'Ukuran foto profil maksimal 5 MB.',
            'foto.image' => 'File foto profil harus berupa gambar.',
            'foto.mimes' => 'Format foto profil harus JPG, JPEG, PNG, atau WEBP.',
            'foto.max' => 'Ukuran foto profil maksimal 5 MB.',
        ]);

        unset($validated['foto_profil'], $validated['foto']);

        $oldPhotoPath = $user->foto_profil;
        $newPhotoPath = null;

        Log::info('Profile update photo debug', [
            'user_id' => $user->id,
            'has_foto_profil' => $request->hasFile('foto_profil'),
            'has_foto_legacy' => $request->hasFile('foto'),
        ]);

        if ($photoField !== null) {
            try {
                $newPhotoPath = $this->storeProfilePhoto($request, $photoField);
                $validated['foto_profil'] = $newPhotoPath;
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                Log::warning('Profile photo upload failed', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal upload foto profil.',
                ], 500);
            }
        }

        DB::transaction(function () use ($user, $validated) {
            $user->update($validated);
        });

        if ($newPhotoPath !== null) {
            $this->deleteOldProfilePhoto($oldPhotoPath, $newPhotoPath);
        }

        $freshUser = $user->fresh()->load('pengemudiProfile');
        $responseUser = $this->formatUser($freshUser, $request);

        Log::info('Profile update response debug', [
            'user_id' => $freshUser->id,
            'foto_profil' => $responseUser['foto_profil'],
            'foto_profil_url' => $responseUser['foto_profil_url'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => array_merge($responseUser, [
                'user' => $responseUser,
            ]),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak sesuai.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah.',
        ]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $photoField = $this->profilePhotoField($request);

        if ($photoField === null) {
            throw ValidationException::withMessages([
                'foto_profil' => ['Foto profil wajib dilampirkan.'],
            ]);
        }

        $request->validate([
            $photoField => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            $photoField.'.image' => 'File foto profil harus berupa gambar.',
            $photoField.'.mimes' => 'Format foto profil harus JPG, JPEG, PNG, atau WEBP.',
            $photoField.'.max' => 'Ukuran foto profil maksimal 5 MB.',
        ]);

        $oldPhotoPath = $user->foto_profil;

        Log::info('Profile photo upload debug', [
            'user_id' => $user->id,
            'field' => $photoField,
            'has_file' => $request->hasFile($photoField),
        ]);

        try {
            $path = $this->storeProfilePhoto($request, $photoField);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Profile photo storage failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal upload foto profil.',
            ], 500);
        }

        DB::transaction(function () use ($user, $path) {
            $user->update(['foto_profil' => $path]);
        });

        $this->deleteOldProfilePhoto($oldPhotoPath, $path);
        $freshUser = $user->fresh()->load('pengemudiProfile');
        $responseUser = $this->formatUser($freshUser, $request);

        Log::info('Profile photo saved debug', [
            'user_id' => $freshUser->id,
            'foto_profil' => $responseUser['foto_profil'],
            'foto_profil_url' => $responseUser['foto_profil_url'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diunggah.',
            'data' => [
                'foto_profil' => $path,
                'foto_profil_url' => $this->publicStorageUrl($path, $request),
                'user' => $responseUser,
            ],
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

    private function formatUser(User $user, ?Request $request = null): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            'peran' => $user->peran,
            'foto_profil' => $user->foto_profil,
            'foto_profil_url' => $this->publicStorageUrl($user->foto_profil, $request),
            'status_aktif' => $user->status_aktif,
            'nomor_sim' => $user->pengemudiProfile?->nomor_sim,
        ];
    }

    private function profilePhotoField(Request $request): ?string
    {
        if ($request->hasFile('foto_profil')) {
            return 'foto_profil';
        }

        if ($request->hasFile('foto')) {
            return 'foto';
        }

        return null;
    }

    private function storeProfilePhoto(Request $request, string $field): string
    {
        $file = $request->file($field);
        if (! $file || ! $file->isValid()) {
            throw new \RuntimeException('File foto profil tidak valid.');
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                $field => ['Tipe file foto profil tidak valid.'],
            ]);
        }

        $path = $file->store('profiles', 'public');
        if (! $path) {
            throw new \RuntimeException('Storage gagal menyimpan foto profil.');
        }

        return $path;
    }

    private function deleteOldProfilePhoto(?string $oldPath, string $newPath): void
    {
        if (! is_string($oldPath) || trim($oldPath) === '' || $oldPath === $newPath) {
            return;
        }

        Storage::disk('public')->delete($oldPath);
    }

    private function publicStorageUrl(?string $path, ?Request $request = null): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $storagePath = '/storage/'.ltrim($path, '/');
        $baseUrl = $request?->getSchemeAndHttpHost() ?: (string) config('app.url', '');

        if (app()->environment(['local', 'testing']) && str_contains($baseUrl, 'ngrok-free.dev')) {
            $baseUrl = 'http://127.0.0.1:8000';
        }

        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/').$storagePath;
        }

        if ($request !== null) {
            return rtrim($request->getSchemeAndHttpHost(), '/').$storagePath;
        }

        return $storagePath;
    }
}
