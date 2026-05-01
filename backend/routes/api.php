<?php

use App\Http\Controllers\Api\AppBootstrapController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\PassengerController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'message' => 'Laravel API connected successfully.',
        'app' => config('app.name'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/mobile/bootstrap', [AppBootstrapController::class, 'index']);
Route::get('/guest/jadwal', [GuestController::class, 'schedules']);
Route::get('/guest/jadwal/{jadwal}', [BookingController::class, 'scheduleDetail']);
Route::post('/payments/pakasir/webhook', [PaymentGatewayController::class, 'pakasirWebhook']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'role:penumpang'])->group(function () {
    Route::get('/penumpang/dashboard', [PassengerController::class, 'dashboard']);
    Route::get('/penumpang/bookings', [BookingController::class, 'index']);
    Route::get('/penumpang/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/penumpang/bookings/lock-form', [BookingController::class, 'lockForm']);
    Route::post('/penumpang/bookings/estimate', [BookingController::class, 'estimate']);
    Route::post('/penumpang/bookings/baggage-photo', [BookingController::class, 'uploadBaggagePhoto']);
    Route::post('/penumpang/bookings', [BookingController::class, 'store']);
    Route::post('/penumpang/bookings/{booking}/simulate-payment', [BookingController::class, 'simulatePayment']);
    Route::post('/penumpang/bookings/{booking}/refresh-payment-status', [BookingController::class, 'refreshPaymentStatus']);
});

Route::middleware(['auth:sanctum', 'role:pengemudi'])->group(function () {
    Route::get('/pengemudi/dashboard', [DriverController::class, 'dashboard']);
    Route::get('/pengemudi/trips', [DriverController::class, 'trips']);
    Route::get('/pengemudi/trips/{jadwal}/manifest', [DriverController::class, 'manifest']);
    Route::post('/pengemudi/trips/{jadwal}/status', [DriverController::class, 'updateTripStatus']);
    Route::post('/pengemudi/tickets/scan', [DriverController::class, 'scanTicket']);
});
