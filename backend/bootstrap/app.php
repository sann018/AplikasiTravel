<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->expectsJson()) {
                return null;
            }

            return route('admin.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Standarisasi format error response untuk semua request API.
        // Tidak menampilkan stack trace atau detail teknis ke pengguna (rules #26).
        // Selalu memakai format konsisten: success, message, errors (rules #28).
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof TooManyRequestsHttpException && ! $request->expectsJson()) {
                return redirect()
                    ->back()
                    ->with('error', 'Terlalu banyak aksi dalam waktu singkat. Tunggu sebentar, lalu coba lagi.');
            }

            if (! $request->expectsJson()) {
                return null;
            }

            // Validasi error — 422 dengan detail per field
            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data yang dikirim tidak valid.',
                    'errors' => $e->errors(),
                ], 422);
            }

            // Auth error — 401 tanpa detail teknis
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi tidak valid atau sudah kedaluwarsa. Silakan login kembali.',
                ], 401);
            }

            // Model not found — 404 dengan pesan ramah
            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data yang dicari tidak ditemukan.',
                ], 404);
            }

            // Rate limit — 429
            if ($e instanceof TooManyRequestsHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak permintaan. Tunggu beberapa saat sebelum mencoba lagi.',
                ], 429);
            }

            // HTTP exception lainnya — gunakan status code yang benar (rules #27)
            if ($e instanceof HttpException) {
                $fallbackMessage = match ($e->getStatusCode()) {
                    403 => 'Anda tidak memiliki akses untuk operasi ini.',
                    405 => 'Metode request tidak diizinkan.',
                    408 => 'Waktu permintaan habis. Silakan coba lagi.',
                    default => 'Permintaan tidak dapat diproses.',
                };

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: $fallbackMessage,
                ], $e->getStatusCode());
            }

            // Error tidak terduga — 500 tanpa menampilkan detail internal
            // Detail error dicatat di log server untuk debugging (rules #25)
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server. Silakan coba lagi atau hubungi admin.',
            ], 500);
        });
    })->create();
