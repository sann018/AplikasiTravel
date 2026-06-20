<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengaturanSistem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PengaturanController extends Controller
{
    public function index(Request $request): View
    {
        $groupLabels = [
            'umum' => 'Umum',
            'pemesanan' => 'Pemesanan',
            'pembayaran' => 'Pembayaran',
            'bagasi' => 'Bagasi',
            'notifikasi' => 'Notifikasi',
        ];

        $settings = PengaturanSistem::query()
            ->orderByRaw("
                CASE grup
                    WHEN 'umum' THEN 1
                    WHEN 'pemesanan' THEN 2
                    WHEN 'pembayaran' THEN 3
                    WHEN 'bagasi' THEN 4
                    WHEN 'notifikasi' THEN 5
                    ELSE 99
                END
            ")
            ->orderBy('kunci')
            ->get()
            ->groupBy('grup');

        $activeTab = $request->string('tab')->toString();
        if (! array_key_exists($activeTab, $groupLabels)) {
            $activeTab = 'umum';
        }

        return view('admin.pengaturan.index', [
            'settings' => $settings,
            'groupLabels' => $groupLabels,
            'activeTab' => $activeTab,
            'adminUser' => $request->user(),
        ]);
    }

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
            'current_password.required' => 'Password lama wajib diisi untuk menyimpan perubahan akun.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password baru belum sama.',
        ]);

        if (! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password lama tidak sesuai.',
            ]);
        }

        $admin->email = $validated['email'];

        if ($request->filled('password')) {
            // Password dicasting hashed oleh model User, jadi tidak pernah disimpan plain text.
            $admin->password = $validated['password'];
        }

        $admin->save();

        return redirect()
            ->route('admin.pengaturan.index')
            ->with('status', 'Akun admin berhasil diperbarui.');
    }

    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'settings' => ['required', 'array'],
            'active_tab' => ['nullable', Rule::in(['umum', 'pemesanan', 'pembayaran', 'bagasi', 'notifikasi'])],
        ]);

        $settings = PengaturanSistem::query()->get();

        foreach ($settings as $setting) {
            if (! array_key_exists($setting->kunci, $payload['settings'])) {
                continue;
            }

            $rawValue = $payload['settings'][$setting->kunci];

            if ($rawValue === null) {
                continue;
            }

            $setting->update([
                'nilai' => $this->castToStoredValue($setting->tipe_data, $rawValue),
                'updated_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
        }

        return redirect()
            ->route('admin.pengaturan.index', ['tab' => $payload['active_tab'] ?? 'umum'])
            ->with('status', 'Pengaturan sistem berhasil diperbarui.');
    }

    private function castToStoredValue(string $type, mixed $value): string
    {
        return match ($type) {
            'integer' => (string) ((int) $value),
            'decimal' => (string) ((float) $value),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL) ? 'true' : 'false',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
            default => (string) $value,
        };
    }
}
