<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pengemudi')) {
            return;
        }

        // Foreign key harus dilepas sebelum kolom Admin verifikator dihapus.
        if (Schema::hasColumn('pengemudi', 'verified_by')) {
            Schema::table('pengemudi', function (Blueprint $table) {
                $table->dropConstrainedForeignId('verified_by');
            });
        }

        $columns = collect([
            'status_verifikasi',
            'catatan_verifikasi',
            'verified_at',
        ])->filter(fn (string $column) => Schema::hasColumn('pengemudi', $column));

        if ($columns->isNotEmpty()) {
            Schema::table('pengemudi', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns->all());
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pengemudi')) {
            return;
        }

        Schema::table('pengemudi', function (Blueprint $table) {
            if (! Schema::hasColumn('pengemudi', 'status_verifikasi')) {
                $table->enum('status_verifikasi', ['menunggu', 'terverifikasi', 'ditolak'])
                    ->default('menunggu')
                    ->after('status');
            }
            if (! Schema::hasColumn('pengemudi', 'catatan_verifikasi')) {
                $table->text('catatan_verifikasi')->nullable()->after('status_verifikasi');
            }
            if (! Schema::hasColumn('pengemudi', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('catatan_verifikasi');
            }
            if (! Schema::hasColumn('pengemudi', 'verified_by')) {
                $table->foreignId('verified_by')
                    ->nullable()
                    ->after('verified_at')
                    ->constrained('pengguna')
                    ->nullOnDelete();
            }
        });
    }
};
