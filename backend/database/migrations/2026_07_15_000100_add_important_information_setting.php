<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'important_information'],
            [
                'group' => 'general',
                'value' => json_encode([
                    [
                        'id' => 'large-items-confirmation',
                        'title' => 'Konfirmasi barang besar',
                        'description' => 'Sepeda, sepeda motor, dan barang berukuran besar wajib dikonfirmasi kepada admin sebelum pemesanan. Barang tersebut tidak dihitung atau diterima otomatis oleh aplikasi.',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'type' => 'string',
                'description' => 'Daftar informasi penting yang ditampilkan pada beranda aplikasi.',
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'important_information')->delete();
    }
};
