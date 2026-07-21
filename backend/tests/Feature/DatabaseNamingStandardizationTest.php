<?php

namespace Tests\Feature;

use App\Models\HomeBanner;
use App\Models\PengaturanSistem;
use App\Models\SystemSetting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseNamingStandardizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_final_schema_uses_indonesian_table_and_column_names(): void
    {
        $this->assertTrue(Schema::hasTable('banner_beranda'));
        $this->assertTrue(Schema::hasTable('pengaturan_sistem'));
        $this->assertTrue(Schema::hasTable('pengaturan_sistem_legacy_backup'));
        $this->assertFalse(Schema::hasTable('home_banners'));
        $this->assertFalse(Schema::hasTable('system_settings'));

        $this->assertTrue(Schema::hasColumn('banner_beranda', 'path_gambar'));
        $this->assertFalse(Schema::hasColumn('banner_beranda', 'gambar_path'));

        foreach (['kelompok', 'kunci', 'nilai', 'tipe', 'deskripsi', 'diperbarui_oleh'] as $column) {
            $this->assertTrue(Schema::hasColumn('pengaturan_sistem', $column));
        }

        foreach (['group', 'key', 'value', 'type', 'description', 'updated_by'] as $column) {
            $this->assertFalse(Schema::hasColumn('pengaturan_sistem', $column));
        }
    }

    public function test_setting_keys_remain_unchanged_and_legacy_model_points_to_backup(): void
    {
        $expectedKeys = [
            'app_name',
            'seat_lock_form_minutes',
            'payment_cash_enabled',
            'baggage_free_weight_kg',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertDatabaseHas('pengaturan_sistem', ['kunci' => $key]);
        }

        $this->assertSame('pengaturan_sistem', (new SystemSetting)->getTable());
        $this->assertSame('pengaturan_sistem_legacy_backup', (new PengaturanSistem)->getTable());
        $this->assertSame(
            DB::table('pengaturan_sistem')->count(),
            DB::table('pengaturan_sistem')->distinct()->count('kunci'),
        );
    }

    public function test_banner_api_keeps_existing_json_contract(): void
    {
        HomeBanner::query()->create([
            'judul' => 'Banner Kontrak',
            'subjudul' => 'Kontrak mobile tetap sama.',
            'path_gambar' => 'home-banners/kontrak.webp',
            'aktif' => true,
            'urutan' => 1,
        ]);

        $this->getJson('/api/home-banners')
            ->assertOk()
            ->assertJsonPath('data.0.judul', 'Banner Kontrak')
            ->assertJsonPath('data.0.subjudul', 'Kontrak mobile tetap sama.')
            ->assertJsonPath('data.0.gambar_url', '/storage/home-banners/kontrak.webp')
            ->assertJsonMissingPath('data.0.path_gambar')
            ->assertJsonMissingPath('data.0.gambar_path');
    }

    public function test_mobile_settings_api_keeps_configuration_key_contract(): void
    {
        $this->getJson('/api/settings/mobile')
            ->assertOk()
            ->assertJsonPath('data.app_name', 'RuslyTravel')
            ->assertJsonPath('data.max_seat_per_booking', 4)
            ->assertJsonPath('data.payment_cash_enabled', true)
            ->assertJsonMissingPath('data.kunci')
            ->assertJsonMissingPath('data.nilai');
    }

    public function test_unique_key_and_foreign_keys_remain_available(): void
    {
        $settingIndexes = collect(Schema::getIndexes('pengaturan_sistem'));
        $this->assertTrue($settingIndexes->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === ['kunci'],
        ));

        $settingForeignKeys = collect(Schema::getForeignKeys('pengaturan_sistem'));
        $this->assertTrue($settingForeignKeys->contains(
            fn (array $foreignKey) => $foreignKey['columns'] === ['diperbarui_oleh']
                && $foreignKey['foreign_table'] === 'pengguna'
                && $foreignKey['foreign_columns'] === ['id']
                && $foreignKey['on_delete'] === 'set null',
        ));

        $bannerForeignKeys = collect(Schema::getForeignKeys('banner_beranda'));
        $this->assertTrue($bannerForeignKeys->contains(
            fn (array $foreignKey) => $foreignKey['columns'] === ['dibuat_oleh']
                && $foreignKey['foreign_table'] === 'pengguna'
                && $foreignKey['foreign_columns'] === ['id']
                && $foreignKey['on_delete'] === 'set null',
        ));
    }
}
