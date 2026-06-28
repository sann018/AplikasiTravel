<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu tabel key-value membuat pengaturan mudah ditambah tanpa membuat kolom baru setiap kali.
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'integer', 'boolean', 'float', 'text'])->default('string');
            $table->string('description', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });

        if (! Schema::hasTable('pengaturan_sistem')) {
            return;
        }

        $groupMap = [
            'umum' => 'general',
            'pemesanan' => 'booking',
            'pembayaran' => 'payment',
            'bagasi' => 'baggage',
            'notifikasi' => 'notification',
        ];
        $typeMap = [
            'decimal' => 'float',
            'json' => 'text',
        ];

        // Data lama disalin agar pembaruan struktur tidak menghapus konfigurasi admin.
        DB::table('pengaturan_sistem')
            ->orderBy('kunci')
            ->get()
            ->each(function ($legacySetting) use ($groupMap, $typeMap) {
                DB::table('system_settings')->updateOrInsert(
                    ['key' => $legacySetting->kunci],
                    [
                        'group' => $groupMap[$legacySetting->grup] ?? 'general',
                        'value' => $legacySetting->nilai,
                        'type' => $typeMap[$legacySetting->tipe_data] ?? $legacySetting->tipe_data,
                        'description' => $legacySetting->deskripsi,
                        'updated_by' => $legacySetting->updated_by,
                        'created_at' => now(),
                        'updated_at' => $legacySetting->updated_at ?? now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
