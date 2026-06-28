<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_bagasi', function (Blueprint $table) {
            if (! Schema::hasColumn('item_bagasi', 'foto_urls')) {
                $table->json('foto_urls')->nullable()->after('foto_url');
            }
        });

        Schema::table('biaya_operasional_perjalanan', function (Blueprint $table) {
            if (! Schema::hasColumn('biaya_operasional_perjalanan', 'status_verifikasi')) {
                $table->string('status_verifikasi', 30)->default('disetujui')->after('bukti_url');
            }

            if (! Schema::hasColumn('biaya_operasional_perjalanan', 'diverifikasi_oleh')) {
                $table->foreignId('diverifikasi_oleh')->nullable()->after('status_verifikasi')->constrained('pengguna')->nullOnDelete();
            }

            if (! Schema::hasColumn('biaya_operasional_perjalanan', 'diverifikasi_pada')) {
                $table->timestamp('diverifikasi_pada')->nullable()->after('diverifikasi_oleh');
            }

            if (! Schema::hasColumn('biaya_operasional_perjalanan', 'catatan_verifikasi')) {
                $table->text('catatan_verifikasi')->nullable()->after('diverifikasi_pada');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biaya_operasional_perjalanan', function (Blueprint $table) {
            if (Schema::hasColumn('biaya_operasional_perjalanan', 'diverifikasi_oleh')) {
                $table->dropConstrainedForeignId('diverifikasi_oleh');
            }

            foreach (['catatan_verifikasi', 'diverifikasi_pada', 'status_verifikasi'] as $column) {
                if (Schema::hasColumn('biaya_operasional_perjalanan', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('item_bagasi', function (Blueprint $table) {
            if (Schema::hasColumn('item_bagasi', 'foto_urls')) {
                $table->dropColumn('foto_urls');
            }
        });
    }
};
