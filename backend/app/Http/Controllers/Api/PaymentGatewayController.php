<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    /** Menerima webhook Pakasir lalu meneruskannya untuk verifikasi pembayaran. */
    public function pakasirWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'order_id' => ['required', 'string', 'max:100'],
            'project' => ['required', 'string', 'max:100'],
            'status' => [
                'required',
                'string',
                Rule::in(['completed', 'pending', 'expired', 'failed', 'cancelled', 'canceled']),
            ],
            'payment_method' => [
                'required',
                'string',
                Rule::in(PaymentMethodCatalog::directGatewayCodes()),
            ],
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
