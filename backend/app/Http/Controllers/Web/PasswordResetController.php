<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'string',
                'min:6',
                'max:20',
                'regex:/^(?=.*[A-Za-z])(?=.*(?:[0-9]|[^A-Za-z0-9\s]))\S+$/',
                'confirmed',
            ],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum valid.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi belum sama.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
            'password.max' => 'Kata sandi maksimal 20 karakter.',
            'password.regex' => 'Kata sandi wajib mengandung huruf dan minimal satu angka atau simbol.',
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    // Tautan aktivasi pegawai sekaligus memverifikasi email internalnya.
                    ...(($user->isStafPelayanan() || $user->isPengemudi()) && $user->email_verified_at === null
                        ? ['email_verified_at' => now()]
                        : []),
                ])->setRememberToken(Str::random(60));
                $user->save();

                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa.']);
        }

        return redirect()->route('password.reset.complete');
    }

    public function complete(): View
    {
        return view('auth.password-reset-complete');
    }
}
