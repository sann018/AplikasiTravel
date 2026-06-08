<?php

use App\Http\Controllers\Api\AppBootstrapController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\PassengerController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'message' => 'Laravel API connected successfully.',
        'app' => config('app.name'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::middleware('throttle:travs-read')->group(function () {
    Route::get('/mobile/bootstrap', [AppBootstrapController::class, 'index']);
    Route::get('/guest/jadwal', [GuestController::class, 'schedules']);
    Route::get('/guest/jadwal/{jadwal}', [BookingController::class, 'scheduleDetail']);
});
Route::post('/payments/pakasir/webhook', [PaymentGatewayController::class, 'pakasirWebhook']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:travs-auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:travs-auth');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:travs-auth');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:travs-auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->middleware('throttle:travs-read');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:travs-action');
        Route::put('/profile', [AuthController::class, 'updateProfile'])->middleware('throttle:travs-action');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:travs-action');
        Route::post('/upload-photo', [AuthController::class, 'uploadPhoto'])->middleware('throttle:travs-upload');
    });
});

Route::middleware(['auth:sanctum', 'role:penumpang'])->group(function () {
    Route::middleware('throttle:travs-read')->group(function () {
        Route::get('/penumpang/dashboard', [PassengerController::class, 'dashboard']);
        Route::get('/penumpang/bookings', [BookingController::class, 'index']);
        Route::get('/penumpang/bookings/{booking}', [BookingController::class, 'show']);
    });

    Route::post('/penumpang/bookings/lock-form', [BookingController::class, 'lockForm'])->middleware('throttle:travs-action');
    Route::post('/penumpang/bookings/estimate', [BookingController::class, 'estimate'])->middleware('throttle:travs-action');
    Route::post('/penumpang/bookings/baggage-photo', [BookingController::class, 'uploadBaggagePhoto'])->middleware('throttle:travs-upload');
    Route::post('/penumpang/bookings', [BookingController::class, 'store'])->middleware('throttle:travs-action');
    Route::post('/penumpang/bookings/{booking}/simulate-payment', [BookingController::class, 'simulatePayment'])->middleware('throttle:travs-action');
    Route::post('/penumpang/bookings/{booking}/refresh-payment-status', [BookingController::class, 'refreshPaymentStatus'])->middleware('throttle:travs-action');
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware('throttle:travs-read')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    });

    Route::post('/notifications/{notifikasi}/read', [NotificationController::class, 'markAsRead'])->middleware('throttle:travs-action');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('throttle:travs-action');
});

Route::middleware(['auth:sanctum', 'role:pengemudi'])->group(function () {
    Route::middleware('throttle:travs-read')->group(function () {
        Route::get('/pengemudi/dashboard', [DriverController::class, 'dashboard']);
        Route::get('/pengemudi/trips', [DriverController::class, 'trips']);
        Route::get('/pengemudi/trips/{jadwal}/manifest', [DriverController::class, 'manifest']);
    });

    Route::post('/pengemudi/trips/{jadwal}/passengers/{detail}/pickup', [DriverController::class, 'markPassengerPickup'])->middleware('throttle:travs-action');
    Route::post('/pengemudi/trips/{jadwal}/status', [DriverController::class, 'updateTripStatus'])->middleware('throttle:travs-action');
    Route::post('/pengemudi/tickets/scan', [DriverController::class, 'scanTicket'])->middleware('throttle:travs-action');
    Route::post('/pengemudi/upload-photo', [DriverController::class, 'uploadDriverPhoto'])->middleware('throttle:travs-upload');
});
