<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AccountVerificationCodeMail;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'no_hp' => trim((string) $request->input('no_hp')),
        ]);

        // Pendaftaran yang gagal mengirim OTP boleh dilanjutkan tanpa membuat akun ganda.
        $pendingUser = User::query()
            ->where('email', $request->input('email'))
            ->where('peran', 'penumpang')
            ->whereNull('email_verified_at')
            ->first();

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:100',
                Rule::unique('pengguna', 'email')->ignore($pendingUser?->id),
            ],
            'no_hp' => [
                'required',
                'string',
                'max:20',
                Rule::unique('pengguna', 'no_hp')->ignore($pendingUser?->id),
            ],
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

        $verificationCode = $this->generateVerificationCode();

        $userData = [
            'nama' => trim($validated['nama']),
            'email' => $validated['email'],
            'no_hp' => trim($validated['no_hp']),
            'peran' => 'penumpang',
            'status_aktif' => true,
            'password' => $validated['password'],
            // Kode asli hanya dikirim melalui email; database menyimpan hash.
            'otp_code' => Hash::make($verificationCode),
            'otp_expires_at' => now()->addMinutes(15),
        ];

        if ($pendingUser) {
            $pendingUser->forceFill($userData)->save();
            $user = $pendingUser->fresh();
        } else {
            $user = User::query()->create($userData);
        }

        try {
            Mail::to($user->email)->send(new AccountVerificationCodeMail($verificationCode));
        } catch (Throwable $exception) {
            Log::error('Kode verifikasi akun gagal dikirim.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran tersimpan, tetapi kode verifikasi belum dapat dikirim karena layanan email sedang bermasalah. Silakan coba kirim ulang kode.',
                'data' => [
                    'email' => $user->email,
                    'requires_email_verification' => true,
                ],
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Kode verifikasi telah dikirim ke email Anda.',
            'data' => [
                'email' => $user->email,
                'expires_in_minutes' => 15,
                'requires_email_verification' => true,
                'user' => $this->formatUser($user, $request),
            ],
        ], 201);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum valid.',
            'code.required' => 'Kode verifikasi wajib diisi.',
            'code.digits' => 'Kode verifikasi harus terdiri dari 6 angka.',
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::query()
            ->where('email', $email)
            ->where('peran', 'penumpang')
            ->first();

        if (! $user || $user->email_verified_at) {
            throw ValidationException::withMessages([
                'code' => ['Kode verifikasi tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        $isExpired = ! $user->otp_expires_at || $user->otp_expires_at->isPast();
        $isInvalid = ! $user->otp_code || ! Hash::check($validated['code'], $user->otp_code);

        if ($isExpired || $isInvalid) {
            throw ValidationException::withMessages([
                'code' => ['Kode verifikasi tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        // Token login baru diterbitkan setelah email benar-benar terverifikasi.
        $user->forceFill([
            'email_verified_at' => now(),
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        $token = $user->createToken($validated['device_name'] ?? 'travs-mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Email berhasil diverifikasi.',
            'data' => [
                'token' => $token,
                'user' => $this->formatUser($user, $request),
            ],
        ]);
    }

    public function resendVerificationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum valid.',
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::query()
            ->where('email', $email)
            ->where('peran', 'penumpang')
            ->whereNull('email_verified_at')
            ->first();

        if ($user) {
            $verificationCode = $this->generateVerificationCode();
            $user->forceFill([
                'otp_code' => Hash::make($verificationCode),
                'otp_expires_at' => now()->addMinutes(15),
            ])->save();

            try {
                Mail::to($user->email)->send(new AccountVerificationCodeMail($verificationCode));
            } catch (Throwable $exception) {
                Log::error('Pengiriman ulang kode verifikasi gagal.', [
                    'user_id' => $user->id,
                    'exception' => $exception->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Kode verifikasi belum dapat dikirim. Silakan coba lagi beberapa saat.',
                ], 503);
            }
        }

        // Respons dibuat umum agar status kepemilikan akun tidak dapat ditebak.
        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar dan belum diverifikasi, kode verifikasi telah dikirim.',
        ]);
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

        if ($user->isPenumpang() && ! $user->email_verified_at && $user->otp_code) {
            return response()->json([
                'success' => false,
                'message' => 'Email belum diverifikasi. Silakan masukkan kode verifikasi yang telah dikirim.',
                'data' => [
                    'requires_email_verification' => true,
                    'email' => $user->email,
                ],
            ], 403);
        }

        // Aplikasi mobile hanya digunakan penumpang dan pengemudi.
        // Akun admin tetap masuk melalui web admin agar pemisahan akses terjaga.
        if (! in_array($user->peran, ['penumpang', 'pengemudi'], true)) {
            throw ValidationException::withMessages([
                'login' => ['Akun admin hanya dapat digunakan melalui web admin.'],
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
        $user = $request->user()->load('pengemudiProfile');
        $photoField = $this->profilePhotoField($request);
        $driverProfile = $user->pengemudiProfile;
        $blockedVehicleFields = [
            'foto_kendaraan',
            'nomor_polisi',
            'nopol',
            'merek_kendaraan',
            'merk_kendaraan',
            'tipe_kendaraan',
            'kapasitas_kursi',
            'warna',
            'status_kendaraan',
        ];

        foreach ($blockedVehicleFields as $field) {
            if ($request->exists($field) || $request->hasFile($field)) {
                throw ValidationException::withMessages([
                    $field => ['Data kendaraan hanya dapat diubah oleh admin melalui menu Armada Kendaraan.'],
                ]);
            }
        }

        $validated = $request->validate([
            'nama' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:100', 'unique:pengguna,email,'.$user->id],
            'no_hp' => ['sometimes', 'string', 'max:20', 'unique:pengguna,no_hp,'.$user->id],
            'nomor_sim' => [
                'sometimes',
                'required',
                'string',
                'regex:/^[0-9]{16}$/',
                'unique:pengemudi,nomor_sim,'.($driverProfile?->id ?? 'NULL'),
            ],
            'foto_profil' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'foto' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'email.email' => 'Format email belum valid.',
            'email.unique' => 'Email sudah dipakai akun lain.',
            'no_hp.unique' => 'Nomor HP sudah dipakai akun lain.',
            'nomor_sim.required' => 'Nomor SIM wajib diisi untuk akun pengemudi.',
            'nomor_sim.regex' => 'Nomor SIM harus menggunakan NIK KTP yang terdiri dari 16 digit angka.',
            'nomor_sim.unique' => 'Nomor SIM sudah dipakai pengemudi lain.',
            'foto_profil.image' => 'File foto profil harus berupa gambar.',
            'foto_profil.mimes' => 'Format foto profil harus JPG, JPEG, PNG, atau WEBP.',
            'foto_profil.max' => 'Ukuran foto profil maksimal 5 MB.',
            'foto.image' => 'File foto profil harus berupa gambar.',
            'foto.mimes' => 'Format foto profil harus JPG, JPEG, PNG, atau WEBP.',
            'foto.max' => 'Ukuran foto profil maksimal 5 MB.',
        ]);

        $driverData = [];
        if (array_key_exists('nomor_sim', $validated)) {
            if ($user->peran !== 'pengemudi' || ! $driverProfile) {
                throw ValidationException::withMessages([
                    'nomor_sim' => ['Nomor SIM hanya dapat diubah oleh akun pengemudi.'],
                ]);
            }

            $driverData['nomor_sim'] = $validated['nomor_sim'];
        }

        unset($validated['foto_profil'], $validated['foto'], $validated['nomor_sim']);

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

        DB::transaction(function () use ($user, $validated, $driverProfile, $driverData) {
            $user->update($validated);

            // Nomor SIM disimpan di profil pengemudi, bukan di tabel akun umum.
            if ($driverData !== [] && $driverProfile) {
                $driverProfile->update($driverData);
            }
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
                'current_password' => ['Kata sandi saat ini tidak sesuai.'],
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

    /// Mengirim tautan reset melalui password broker resmi Laravel.
    /// Respons tetap umum agar orang lain tidak dapat menebak email terdaftar.
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100'],
        ]);

        $email = Str::lower(trim($validated['email']));

        try {
            Password::sendResetLink(['email' => $email]);
        } catch (Throwable $exception) {
            Log::error('Tautan reset kata sandi gagal dikirim.', [
                'exception' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar, tautan reset kata sandi telah dikirim.',
        ]);
    }

    /// Endpoint API dipertahankan untuk kompatibilitas, tetapi pemeriksaan token
    /// sepenuhnya memakai password broker Laravel yang sama dengan halaman web.
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'token' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => Str::lower(trim($validated['email'])),
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));
                $user->save();

                // Semua sesi lama dicabut setelah kata sandi diganti.
                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['Tautan reset tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru.',
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
            'email_verified' => $user->email_verified_at !== null,
            'nomor_sim' => $user->pengemudiProfile?->nomor_sim,
        ];
    }

    private function generateVerificationCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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
