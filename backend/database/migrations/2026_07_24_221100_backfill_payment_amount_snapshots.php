<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pembayaran')
            ->join('pemesanan', 'pemesanan.id', '=', 'pembayaran.pemesanan_id')
            ->select([
                'pembayaran.id',
                'pembayaran.metode_bayar',
                'pembayaran.status_bayar',
                'pembayaran.jumlah_bayar',
                'pembayaran.gateway_response',
                'pemesanan.total_harga',
            ])
            ->orderBy('pembayaran.id')
            ->chunk(100, function ($payments): void {
                foreach ($payments as $payment) {
                    $metadata = json_decode((string) $payment->gateway_response, true);
                    $metadata = is_array($metadata) ? $metadata : [];
                    $create = data_get($metadata, 'create_payment', []);
                    $detail = data_get($metadata, 'transaction_detail', []);
                    $isCash = $payment->metode_bayar === 'cash';

                    $nominal = $isCash
                        ? $this->integerMoney($payment->total_harga)
                        : $this->integerMoney(data_get($create, 'amount'))
                            ?? $this->integerMoney(data_get($detail, 'amount'))
                            ?? $this->integerMoney($payment->total_harga);
                    $fee = $isCash
                        ? 0
                        : $this->integerMoney(data_get($create, 'fee'), true)
                            ?? $this->integerMoney(data_get($detail, 'fee'), true);
                    $total = $isCash
                        ? $nominal
                        : $this->integerMoney(data_get($detail, 'total_payment'))
                            ?? $this->integerMoney(data_get($create, 'total_payment'))
                            ?? ($payment->status_bayar === 'berhasil'
                                ? $this->integerMoney($payment->jumlah_bayar)
                                : null);
                    $paymentNumber = $isCash
                        ? null
                        : trim((string) (
                            data_get($create, 'payment_number')
                            ?? data_get($detail, 'payment_number')
                            ?? ''
                        ));

                    DB::table('pembayaran')
                        ->where('id', $payment->id)
                        ->update([
                            'nominal_transaksi' => $nominal,
                            'biaya_admin_provider' => $fee,
                            'total_dibayar_penumpang' => $total,
                            'payment_number' => $paymentNumber !== '' ? $paymentNumber : null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Snapshot hasil backfill tetap dipertahankan saat rollback data.
    }

    private function integerMoney(mixed $value, bool $allowZero = false): ?int
    {
        $stringValue = is_int($value) ? (string) $value : trim((string) $value);
        if (! preg_match('/^\d+$/', $stringValue)) {
            return null;
        }

        $amount = (int) $stringValue;

        return ($allowZero ? $amount >= 0 : $amount >= 1) ? $amount : null;
    }
};
