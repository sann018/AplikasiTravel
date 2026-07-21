<?php

namespace Tests\Feature;

use App\Models\HomeBanner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeBannerFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_banner_and_mobile_only_receives_active_banner(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['peran' => 'admin', 'status_aktif' => true]);

        $this->actingAs($admin)
            ->post(route('admin.banner.store'), [
                'judul' => 'Promo Sambas',
                'subjudul' => 'Perjalanan nyaman minggu ini.',
                'gambar' => UploadedFile::fake()->image('promo.png', 1200, 600),
                'urutan' => 1,
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'banner']))
            ->assertSessionHasNoErrors();

        $banner = HomeBanner::query()->firstOrFail();
        Storage::disk('public')->assertExists($banner->path_gambar);

        $this->getJson('/api/home-banners')
            ->assertOk()
            ->assertJsonPath('data.0.judul', 'Promo Sambas')
            ->assertJsonPath('data.0.gambar_url', '/storage/'.$banner->path_gambar);
    }

    public function test_new_banner_is_always_active_after_visibility_control_is_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['peran' => 'admin', 'status_aktif' => true]);

        $this->actingAs($admin)
            ->post(route('admin.banner.store'), [
                'judul' => 'Banner Otomatis Aktif',
                'subjudul' => 'Langsung tampil di aplikasi.',
                'gambar' => UploadedFile::fake()->image('aktif.png', 1200, 600),
                'urutan' => 1,
                // Payload buatan tidak boleh mengaktifkan kembali kontrol yang sudah dihapus dari UI.
                'aktif' => 0,
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'banner']))
            ->assertSessionHasNoErrors();

        $banner = HomeBanner::query()->firstOrFail();

        $this->assertTrue((bool) $banner->aktif);

        $this->getJson('/api/home-banners')
            ->assertOk()
            ->assertJsonPath('data.0.judul', 'Banner Otomatis Aktif');
    }

    public function test_banner_upload_rejects_non_image_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['peran' => 'admin', 'status_aktif' => true]);

        $this->actingAs($admin)
            ->post(route('admin.banner.store'), [
                'judul' => 'File salah',
                'gambar' => UploadedFile::fake()->create('promo.txt', 10, 'text/plain'),
                'urutan' => 0,
            ])
            ->assertSessionHasErrors('gambar');
    }
}
