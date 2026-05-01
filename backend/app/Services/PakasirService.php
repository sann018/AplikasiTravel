<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PakasirException extends RuntimeException
{
}

class PakasirService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.pakasir.enabled')
            && filled($this->baseUrl())
            && filled($this->project())
            && filled($this->apiKey());
    }

    public function usesRemoteSimulation(): bool
    {
        return $this->isConfigured() && (bool) config('services.pakasir.use_remote_simulation', false);
    }

    public function handlesMethod(string $paymentMethod): bool
    {
        return $paymentMethod === 'qris';
    }

    public function validateProject(string $project): bool
    {
        return $project === $this->project();
    }

    /**
     * @return array<string, mixed>
     */
    public function createTransaction(string $method, string $orderId, int|float|string $amount): array
    {
        $response = $this->post("transactioncreate/{$method}", [
            'project' => $this->project(),
            'order_id' => $orderId,
            'amount' => $this->normalizeAmount($amount),
            'api_key' => $this->apiKey(),
        ]);

        $payment = $response['payment'] ?? null;
        if (! is_array($payment)) {
            throw new PakasirException('Response Pakasir tidak mengandung data pembayaran.');
        }

        return $payment;
    }

    /**
     * @return array<string, mixed>
     */
    public function simulatePayment(string $orderId, int|float|string $amount): array
    {
        return $this->post('paymentsimulation', [
            'project' => $this->project(),
            'order_id' => $orderId,
            'amount' => $this->normalizeAmount($amount),
            'api_key' => $this->apiKey(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelTransaction(string $orderId, int|float|string $amount): array
    {
        return $this->post('transactioncancel', [
            'project' => $this->project(),
            'order_id' => $orderId,
            'amount' => $this->normalizeAmount($amount),
            'api_key' => $this->apiKey(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionDetail(string $orderId, int|float|string $amount): array
    {
        $response = $this->get('transactiondetail', [
            'project' => $this->project(),
            'order_id' => $orderId,
            'amount' => $this->normalizeAmount($amount),
            'api_key' => $this->apiKey(),
        ]);

        $transaction = $response['transaction'] ?? null;
        if (! is_array($transaction)) {
            throw new PakasirException('Response detail transaksi Pakasir tidak valid.');
        }

        return $transaction;
    }

    public function isCompletedStatus(?string $status): bool
    {
        return strtolower((string) $status) === 'completed';
    }

    public function isExpiredStatus(?string $status): bool
    {
        return in_array(strtolower((string) $status), ['expired', 'kedaluwarsa'], true);
    }

    public function isFailedStatus(?string $status): bool
    {
        return in_array(strtolower((string) $status), ['failed', 'failure', 'cancelled', 'canceled', 'gagal'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeGatewayResponse(?string $gatewayResponse): array
    {
        if (! is_string($gatewayResponse) || trim($gatewayResponse) === '') {
            return [];
        }

        $decoded = json_decode($gatewayResponse, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function encodeGatewayResponse(array $payload): string
    {
        return (string) json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    public function mergeGatewayResponse(?string $gatewayResponse, array $fragment): string
    {
        $current = $this->decodeGatewayResponse($gatewayResponse);

        return $this->encodeGatewayResponse([
            ...$current,
            ...$fragment,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = Http::acceptJson()
            ->asJson()
            ->timeout($this->timeoutSeconds())
            ->connectTimeout($this->connectTimeoutSeconds())
            ->post($this->url($path), $payload);

        return $this->decodeResponse($response->status(), $response->body());
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        $response = Http::acceptJson()
            ->timeout($this->timeoutSeconds())
            ->connectTimeout($this->connectTimeoutSeconds())
            ->get($this->url($path), $query);

        return $this->decodeResponse($response->status(), $response->body());
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(int $statusCode, string $body): array
    {
        $decoded = json_decode($body, true);
        $payload = is_array($decoded) ? $decoded : [];

        if ($statusCode >= 200 && $statusCode < 300) {
            return $payload;
        }

        $message = $payload['message'] ?? $payload['error'] ?? 'Permintaan Pakasir gagal diproses.';
        throw new PakasirException((string) $message);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl(), '/').'/'.ltrim($path, '/');
    }

    private function normalizeAmount(int|float|string $amount): int
    {
        return (int) round((float) $amount);
    }

    private function baseUrl(): string
    {
        return (string) config('services.pakasir.base_url', 'https://app.pakasir.com/api');
    }

    private function project(): string
    {
        return (string) config('services.pakasir.project');
    }

    private function apiKey(): string
    {
        return (string) config('services.pakasir.api_key');
    }

    private function timeoutSeconds(): int
    {
        return max(5, min(30, (int) config('services.pakasir.timeout', 15)));
    }

    private function connectTimeoutSeconds(): int
    {
        return max(3, min($this->timeoutSeconds(), (int) config('services.pakasir.connect_timeout', 5)));
    }
}
