<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pembayaran')
            ->join('pemesanan', 'pemesanan.id', '=', 'pembayaran.pemesanan_id')
            ->where('pembayaran.metode_bayar', 'cash')
            ->select([
                'pembayaran.id',
                'pembayaran.nominal_transaksi',
                'pemesanan.total_harga',
            ])
            ->orderBy('pembayaran.id')
            ->chunk(100, function ($payments): void {
                foreach ($payments as $payment) {
                    // Isi snapshot tunai lama dari total booking karena tidak memakai provider.
                    $nominal = $this->integerMoney($payment->nominal_transaksi)
                        ?? $this->integerMoney($payment->total_harga);

                    if ($nominal === null) {
                        continue;
                    }

                    DB::table('pembayaran')
                        ->where('id', $payment->id)
                        ->update([
                            'nominal_transaksi' => $nominal,
                            'biaya_admin_provider' => 0,
                            'total_dibayar_penumpang' => $nominal,
                            'payment_number' => null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Backfill snapshot historis tidak dihapus saat rollback.
    }

    private function integerMoney(mixed $value): ?int
    {
        $stringValue = is_int($value) ? (string) $value : trim((string) $value);

        if (! preg_match('/^\d+(?:\.0+)?$/', $stringValue)) {
            return null;
        }

        $amount = (int) $stringValue;

        return $amount >= 1 ? $amount : null;
    }
};
