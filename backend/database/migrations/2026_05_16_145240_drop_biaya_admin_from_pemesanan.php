<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk menghapus kolom `biaya_admin` dari tabel `pemesanan`.
 *
 * ALASAN PENGHAPUSAN:
 * Kolom `biaya_admin` sudah tidak relevan karena biaya admin sudah ditangani
 * otomatis oleh Payment Gateway (Pakasir) sesuai channel yang dipilih
 * penumpang. Selama implementasi, kolom ini selalu bernilai 0 dan menjadi
 * field "sampah" yang membingungkan saat ditampilkan di UI admin.
 *
 * Dengan menghapus kolom ini, struktur basis data lebih konsisten dengan
 * keputusan arsitektur "biaya admin sepenuhnya delegated ke Pakasir".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemesanan', function (Blueprint $table) {
            $table->dropColumn('biaya_admin');
        });
    }

    public function down(): void
    {
        Schema::table('pemesanan', function (Blueprint $table) {
            // Tambahkan kembali kolom dengan default 0 untuk rollback aman.
            // Kolom diposisikan setelah `subtotal_tiket` agar urutan kolom
            // tetap konsisten dengan migration awal.
            $table->decimal('biaya_admin', 12, 2)->default(0)->after('subtotal_tiket');
        });
    }
};
