<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_bagasi', function (Blueprint $table) {
            if (! Schema::hasColumn('item_bagasi', 'jenis_barang_besar')) {
                $table->string('jenis_barang_besar', 50)->nullable()->after('kategori_ukuran');
            }
        });

        $now = now();
        $settings = [
            [
                'key' => 'baggage_large_vehicle_enabled',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Mengaktifkan tarif khusus untuk barang besar seperti sepeda, motor, atau beko.',
            ],
            [
                'key' => 'baggage_bicycle_fee',
                'value' => '0',
                'type' => 'float',
                'description' => 'Tarif barang besar untuk sepeda yang masuk bagasi.',
            ],
            [
                'key' => 'baggage_motorcycle_fee',
                'value' => '0',
                'type' => 'float',
                'description' => 'Tarif barang besar untuk sepeda motor yang ikut diangkut.',
            ],
            [
                'key' => 'baggage_heavy_item_fee',
                'value' => '0',
                'type' => 'float',
                'description' => 'Tarif barang besar untuk beko atau barang berat khusus.',
            ],
            [
                'key' => 'baggage_other_large_item_fee',
                'value' => '0',
                'type' => 'float',
                'description' => 'Tarif barang besar lain yang tidak termasuk sepeda, motor, atau beko.',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'group' => 'baggage',
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'description' => $setting['description'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::table('item_bagasi', function (Blueprint $table) {
            if (Schema::hasColumn('item_bagasi', 'jenis_barang_besar')) {
                $table->dropColumn('jenis_barang_besar');
            }
        });

        DB::table('system_settings')
            ->whereIn('key', [
                'baggage_large_vehicle_enabled',
                'baggage_bicycle_fee',
                'baggage_motorcycle_fee',
                'baggage_heavy_item_fee',
                'baggage_other_large_item_fee',
            ])
            ->delete();
    }
};
