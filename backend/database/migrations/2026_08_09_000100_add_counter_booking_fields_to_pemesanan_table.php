<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemesanan', function (Blueprint $table) {
            $table->dropForeign(['pengguna_id']);
        });

        Schema::table('pemesanan', function (Blueprint $table) {
            // Akun penumpang boleh kosong untuk pemesanan yang dibuat di loket.
            $table->foreignId('pengguna_id')->nullable()->change();
            $table->foreign('pengguna_id')->references('id')->on('pengguna')->nullOnDelete();

            // Kolom ini menyimpan admin pencatat dan identitas pemesan saat transaksi dibuat.
            $table->foreignId('dibuat_oleh')
                ->nullable()
                ->after('pengguna_id')
                ->constrained('pengguna')
                ->nullOnDelete();
            $table->string('sumber_pemesanan', 30)
                ->default('aplikasi_penumpang')
                ->after('dibuat_oleh')
                ->index();
            $table->string('nama_pemesan', 100)->nullable()->after('sumber_pemesanan');
            $table->string('no_hp_pemesan', 20)->nullable()->after('nama_pemesan');
            $table->string('email_pemesan', 100)->nullable()->after('no_hp_pemesan');
        });
    }

    public function down(): void
    {
        // Rollback ditolak jika ada pemesanan loket tanpa akun agar data tidak terhapus.
        if (DB::table('pemesanan')->whereNull('pengguna_id')->exists()) {
            throw new \RuntimeException(
                'Rollback tidak aman karena terdapat pemesanan tanpa akun penumpang.'
            );
        }

        Schema::table('pemesanan', function (Blueprint $table) {
            $table->dropForeign(['dibuat_oleh']);
            $table->dropForeign(['pengguna_id']);
            $table->dropIndex(['sumber_pemesanan']);
            $table->dropColumn([
                'dibuat_oleh',
                'sumber_pemesanan',
                'nama_pemesan',
                'no_hp_pemesan',
                'email_pemesan',
            ]);
        });

        Schema::table('pemesanan', function (Blueprint $table) {
            $table->foreignId('pengguna_id')->nullable(false)->change();
            $table->foreign('pengguna_id')->references('id')->on('pengguna')->cascadeOnDelete();
        });
    }
};
