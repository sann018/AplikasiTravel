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
        Schema::create('rute', function (Blueprint $table) {
            $table->id();
            $table->string('kota_asal');
            $table->string('kota_tujuan');
            $table->string('deskripsi_jalur')->nullable();
            $table->decimal('jarak_km', 8, 2)->nullable();
            $table->unsignedInteger('estimasi_durasi_menit')->nullable();
            $table->decimal('harga_tiket', 12, 2);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('kendaraan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_plat', 20)->unique();
            $table->string('merk_kendaraan');
            $table->string('warna', 30);
            $table->unsignedInteger('tahun_kendaraan')->nullable();
            $table->string('tipe_kendaraan')->nullable();
            $table->unsignedInteger('kapasitas_kursi');
            $table->text('fasilitas')->nullable();
            $table->unsignedInteger('total_kilometer')->default(0);
            $table->unsignedInteger('km_servis_berikutnya')->nullable();
            $table->date('tgl_terakhir_servis')->nullable();
            $table->date('tgl_pajak_tahunan')->nullable();
            $table->enum('status_kendaraan', ['aktif', 'perawatan', 'nonaktif'])->default('aktif');
            $table->timestamps();
        });

        Schema::create('pengemudi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->constrained('pengguna')->cascadeOnDelete();
            $table->string('nomor_sim', 50)->unique();
            $table->string('foto_sim')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
        });

        Schema::create('jadwal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kendaraan_id')->constrained('kendaraan')->restrictOnDelete();
            $table->foreignId('rute_id')->constrained('rute')->restrictOnDelete();
            $table->foreignId('pengemudi_id')->constrained('pengemudi')->restrictOnDelete();
            $table->dateTime('waktu_berangkat');
            $table->dateTime('estimasi_tiba')->nullable();
            $table->enum('status', ['draft', 'dibuka', 'ditutup', 'berangkat', 'selesai', 'dibatalkan'])->default('draft');
            $table->timestamps();
        });

        Schema::create('kursi_jadwal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_id')->constrained('jadwal')->cascadeOnDelete();
            $table->unsignedInteger('nomor_kursi');
            $table->enum('status_kursi', ['tersedia', 'terkunci', 'dipesan', 'nonaktif'])->default('tersedia');
            $table->foreignId('dikunci_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('lock_expires_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['jadwal_id', 'nomor_kursi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kursi_jadwal');
        Schema::dropIfExists('jadwal');
        Schema::dropIfExists('pengemudi');
        Schema::dropIfExists('kendaraan');
        Schema::dropIfExists('rute');
    }
};
