<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('biaya_operasional_perjalanan')) {
            return;
        }

        // Hapus foreign key terlebih dahulu sebelum kolom verifikator dilepas.
        if (Schema::hasColumn('biaya_operasional_perjalanan', 'diverifikasi_oleh')) {
            Schema::table('biaya_operasional_perjalanan', function (Blueprint $table) {
                $table->dropConstrainedForeignId('diverifikasi_oleh');
            });
        }

        $columns = collect([
            'status_verifikasi',
            'diverifikasi_pada',
            'catatan_verifikasi',
        ])->filter(fn (string $column) => Schema::hasColumn('biaya_operasional_perjalanan', $column));

        if ($columns->isNotEmpty()) {
            Schema::table('biaya_operasional_perjalanan', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns->all());
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('biaya_operasional_perjalanan')) {
            return;
        }

        Schema::table('biaya_operasional_perjalanan', function (Blueprint $table) {
            if (! Schema::hasColumn('biaya_operasional_perjalanan', 'status_verifikasi')) {
                $table->string('status_verifikasi', 30)->default('disetujui')->after('bukti_url');
            }
            if (! Schema::hasColumn('biaya_operasional_perjalanan', 'diverifikasi_oleh')) {
                $table->foreignId('diverifikasi_oleh')
                    ->nullable()
                    ->after('status_verifikasi')
                    ->constrained('pengguna')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('biaya_operasional_perjalanan', 'diverifikasi_pada')) {
                $table->timestamp('diverifikasi_pada')->nullable()->after('diverifikasi_oleh');
            }
            if (! Schema::hasColumn('biaya_operasional_perjalanan', 'catatan_verifikasi')) {
                $table->text('catatan_verifikasi')->nullable()->after('diverifikasi_pada');
            }
        });
    }
};
