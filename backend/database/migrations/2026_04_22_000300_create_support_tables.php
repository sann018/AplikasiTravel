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
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->constrained('pengguna')->cascadeOnDelete();
            $table->string('judul');
            $table->text('pesan');
            $table->enum('jenis', ['pembayaran', 'perjalanan', 'sistem'])->default('sistem');
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->boolean('sudah_dibaca')->default(false);
            $table->timestamps();
        });

        Schema::create('biaya_operasional_perjalanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_id')->constrained('jadwal')->cascadeOnDelete();
            $table->string('jenis_biaya');
            $table->decimal('nominal', 12, 2);
            $table->text('keterangan')->nullable();
            $table->dateTime('tanggal_biaya');
            $table->foreignId('created_by')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pengaturan_sistem', function (Blueprint $table) {
            $table->string('kunci', 100)->primary();
            $table->text('nilai')->nullable();
            $table->enum('tipe_data', ['integer', 'decimal', 'string', 'boolean', 'json'])->default('string');
            $table->enum('grup', ['umum', 'pemesanan', 'pembayaran', 'bagasi', 'notifikasi']);
            $table->string('deskripsi', 255)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('pengguna')->nullOnDelete();

            $table->index('grup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_sistem');
        Schema::dropIfExists('biaya_operasional_perjalanan');
        Schema::dropIfExists('notifikasi');
    }
};
