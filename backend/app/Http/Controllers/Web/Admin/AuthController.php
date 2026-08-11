<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'max:20'],
        ], [
            'password.min' => 'Kata sandi minimal 6 karakter.',
            'password.max' => 'Kata sandi maksimal 20 karakter.',
        ]);

        $credentials = filter_var($validated['login'], FILTER_VALIDATE_EMAIL)
            ? ['email' => $validated['login'], 'password' => $validated['password']]
            : ['no_hp' => $validated['login'], 'password' => $validated['password']];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'login' => 'Login admin tidak valid.',
            ]);
        }

        $request->session()->regenerate();

        if (! $request->user()->isAdmin()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'Akun ini bukan akun admin.',
            ]);
        }

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
