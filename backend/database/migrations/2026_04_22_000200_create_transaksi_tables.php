<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pemesanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->constrained('pengguna')->cascadeOnDelete();
            $table->foreignId('jadwal_id')->constrained('jadwal')->restrictOnDelete();
            $table->string('kode_booking')->unique();
            $table->unsignedInteger('jumlah_kursi');
            $table->dateTime('batas_kunci_kursi')->nullable();
            $table->dateTime('batas_pembayaran')->nullable();
            $table->decimal('lintang_jemput', 10, 7)->nullable();
            $table->decimal('bujur_jemput', 10, 7)->nullable();
            $table->text('alamat_jemput')->nullable();
            $table->decimal('subtotal_tiket', 12, 2)->default(0);
            $table->decimal('biaya_bagasi', 12, 2)->default(0);
            $table->decimal('biaya_admin', 12, 2)->default(0);
            $table->decimal('diskon', 12, 2)->default(0);
            $table->decimal('total_harga', 12, 2)->default(0);
            $table->enum('status_pemesanan', ['draft', 'menunggu_pembayaran', 'terbayar', 'dibatalkan', 'selesai'])->default('draft');
            $table->timestamps();
        });

        Schema::create('detail_pemesanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemesanan_id')->constrained('pemesanan')->cascadeOnDelete();
            $table->foreignId('kursi_jadwal_id')->constrained('kursi_jadwal')->restrictOnDelete();
            $table->unsignedInteger('nomor_kursi');
            $table->string('nama_penumpang');
            $table->string('no_hp_penumpang', 20);
            $table->enum('status_kehadiran', ['menunggu', 'hadir', 'tidak_hadir'])->default('menunggu');
            $table->timestamps();

            $table->unique('kursi_jadwal_id');
        });

        Schema::create('item_bagasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemesanan_id')->constrained('pemesanan')->cascadeOnDelete();
            $table->string('deskripsi_item');
            $table->decimal('berat_kg', 5, 2)->nullable();
            $table->unsignedInteger('ukuran_cm')->nullable();
            $table->unsignedInteger('jumlah_item')->default(1);
            $table->string('foto_url')->nullable();
            $table->decimal('biaya_item', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemesanan_id')->constrained('pemesanan')->cascadeOnDelete();
            $table->string('order_id_gateway')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('metode_bayar', 50);
            $table->string('paid_channel')->nullable();
            $table->decimal('jumlah_bayar', 12, 2)->default(0);
            $table->enum('status_bayar', ['menunggu', 'berhasil', 'gagal', 'kedaluwarsa'])->default('menunggu');
            $table->text('gateway_response')->nullable();
            $table->json('raw_webhook_payload')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->dateTime('webhook_verified_at')->nullable();
            $table->dateTime('dibayar_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('tiket_elektronik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_pemesanan_id')->constrained('detail_pemesanan')->cascadeOnDelete();
            $table->string('kode_qr');
            $table->string('token_unik_qr', 100)->unique();
            $table->boolean('sudah_dipindai')->default(false);
            $table->dateTime('dipindai_pada')->nullable();
            $table->dateTime('diterbitkan_pada')->nullable();
            $table->timestamps();

            $table->unique('detail_pemesanan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiket_elektronik');
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('item_bagasi');
        Schema::dropIfExists('detail_pemesanan');
        Schema::dropIfExists('pemesanan');
    }
};
