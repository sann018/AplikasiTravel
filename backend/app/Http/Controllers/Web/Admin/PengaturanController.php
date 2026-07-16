<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengaturanController extends Controller
{
    public function updateAccount(Request $request): RedirectResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:100',
                Rule::unique('pengguna', 'email')->ignore($admin->id),
            ],
            'current_password' => ['required', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'Email admin wajib diisi.',
            'email.email' => 'Format email admin belum valid.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'current_password.required' => 'Kata sandi lama wajib diisi untuk menyimpan perubahan akun.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
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
