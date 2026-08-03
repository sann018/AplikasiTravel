<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengaturanController extends Controller
{
    public function updateAccount(Request $request): RedirectResponse
    {
        $admin = $request->user();
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:100',
                'regex:/^[^@\s]+@gmail\.com$/i',
                Rule::unique('pengguna', 'email')->ignore($admin->id),
            ],
            'current_password' => ['required', 'string', 'max:72'],
            'password' => [
                'nullable',
                'string',
                'min:6',
                'max:72',
                'different:current_password',
                'regex:/^(?=.*[A-Za-z])(?=.*(?:[0-9]|[^A-Za-z0-9\s]))\S+$/',
                'confirmed',
            ],
        ], [
            'email.required' => 'Email admin wajib diisi.',
            'email.email' => 'Format email admin belum valid.',
            'email.regex' => 'Email admin wajib menggunakan alamat @gmail.com.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'current_password.required' => 'Kata sandi lama wajib diisi untuk menyimpan perubahan akun.',
            'password.min' => 'Kata sandi baru minimal 6 karakter.',
            'password.max' => 'Kata sandi baru maksimal 72 karakter untuk keamanan pemrosesan.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi lama.',
            'password.regex' => 'Kata sandi baru wajib mengandung huruf dan minimal satu angka atau simbol.',
            'password.confirmed' => 'Konfirmasi kata sandi baru belum sama.',
        ]);

        if (! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi lama tidak sesuai.',
            ]);
        }

        $admin->email = $validated['email'];

        if ($request->filled('password')) {
            // Password dicasting hashed oleh model User, jadi tidak pernah disimpan plain text.
            $admin->password = $validated['password'];
        }

        $admin->save();

        return redirect()
            ->route('admin.settings.index', ['tab' => 'account'])
            ->with('status', 'Akun admin berhasil diperbarui.');
    }

    public function update(Request $request, SettingService $settingService): RedirectResponse
    {
        $payload = $request->validate([
            'settings' => ['required', 'array'],
            'active_tab' => ['nullable', Rule::in(['umum', 'pemesanan', 'pembayaran', 'bagasi', 'notifikasi'])],
        ]);

        $groupMap = [
            'umum' => 'general',
            'pemesanan' => 'booking',
            'pembayaran' => 'payment',
            'bagasi' => 'baggage',
            'notifikasi' => 'notification',
        ];
        $oldTab = $payload['active_tab'] ?? 'umum';
        $group = $groupMap[$oldTab];
        $allowedKeys = array_keys($settingService->group($group));
        $values = array_intersect_key($payload['settings'], array_flip($allowedKeys));

        // Endpoint lama tetap memakai transaksi dan service baru agar sumber datanya konsisten.
        DB::transaction(fn () => $settingService->updateMany($values, $request->user()->id));

        return redirect()
            ->route('admin.settings.index', ['tab' => $group])
            ->with('status', 'Pengaturan sistem berhasil diperbarui.');
    }
}
