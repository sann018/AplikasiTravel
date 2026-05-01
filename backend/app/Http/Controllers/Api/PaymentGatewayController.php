<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    public function pakasirWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'order_id' => ['required', 'string', 'max:100'],
            'project' => ['required', 'string', 'max:100'],
            'status' => ['required', 'string', 'max:50'],
            'payment_method' => ['required', 'string', 'max:50'],
            'completed_at' => ['nullable', 'date'],
        ]);

        $booking = $this->bookingService->handlePakasirWebhook($validated);

        return response()->json([
            'success' => true,
            'message' => $booking
                ? 'Webhook Pakasir berhasil diproses.'
                : 'Webhook Pakasir diterima, tetapi transaksi lokal tidak ditemukan.',
        ]);
    }
}
