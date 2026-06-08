<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration untuk menghapus setting pembayaran yang redundan/tidak digunakan.
 *
 * ALASAN PENGHAPUSAN:
 * 1. admin_fee — Biaya admin sudah ditangani otomatis oleh Payment Gateway
 *    (Pakasir) sesuai channel yang dipilih penumpang:
 *      - VA BRI/BNI/CIMB/dll: Rp 3.500
 *      - VA Artha Graha/Sampoerna: Rp 2.000
 *      - QRIS ≤ Rp 105.000: 0.7% + Rp 300
 *      - QRIS > Rp 105.000: 1%
 *    Setting manual di sistem akan menyebabkan double charge dan tidak akurat.
 *
 * 2. bank_name, bank_account_number, bank_account_name — Tidak digunakan karena
 *    Virtual Account di-generate otomatis oleh Pakasir per transaksi, bukan
 *    rekening tetap milik travel. Penumpang transfer ke nomor VA unik,
 *    bukan rekening pribadi.
 *
 * 3. payment_cash_note — Instruksi pembayaran tunai sudah hardcoded di UI
 *    penumpang dan tidak perlu di-customize via setting.
 *
 * DAMPAK: BookingCalculator sudah set $adminFee = 0.0 secara hardcoded sehingga
 * penghapusan setting ini tidak mempengaruhi kalkulasi total harga pemesanan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pengaturan_sistem')
            ->whereIn('kunci', [
                'admin_fee',
                'bank_name',
                'bank_account_number',
                'bank_account_name',
                'payment_cash_note',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Rollback: kembalikan setting dengan nilai default semula.
        // Catatan: nilai admin_fee dikembalikan untuk konsistensi rollback,
        // walaupun BookingCalculator tetap mengabaikan nilai ini.
        $defaults = [
            ['kunci' => 'admin_fee', 'nilai' => '5000', 'tipe_data' => 'decimal', 'grup' => 'pembayaran', 'deskripsi' => 'Biaya admin tetap per transaksi.'],
            ['kunci' => 'bank_name', 'nilai' => 'BRI', 'tipe_data' => 'string', 'grup' => 'pembayaran', 'deskripsi' => 'Nama bank rekening penerima.'],
            ['kunci' => 'bank_account_number', 'nilai' => '1234567890', 'tipe_data' => 'string', 'grup' => 'pembayaran', 'deskripsi' => 'Nomor rekening penerima.'],
            ['kunci' => 'bank_account_name', 'nilai' => 'Rusly Travel', 'tipe_data' => 'string', 'grup' => 'pembayaran', 'deskripsi' => 'Nama pemilik rekening penerima.'],
            ['kunci' => 'payment_cash_note', 'nilai' => 'Silakan bayar kepada pengemudi saat perjalanan', 'tipe_data' => 'string', 'grup' => 'pembayaran', 'deskripsi' => 'Catatan pembayaran tunai.'],
        ];

        foreach ($defaults as $setting) {
            DB::table('pengaturan_sistem')->updateOrInsert(
                ['kunci' => $setting['kunci']],
                [
                    ...$setting,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
};
