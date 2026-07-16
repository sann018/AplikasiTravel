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
                'aktif' => 1,
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'banner']))
            ->assertSessionHasNoErrors();

        $banner = HomeBanner::query()->firstOrFail();
        Storage::disk('public')->assertExists($banner->gambar_path);

        $this->getJson('/api/home-banners')
            ->assertOk()
            ->assertJsonPath('data.0.judul', 'Promo Sambas')
            ->assertJsonPath('data.0.gambar_url', '/storage/'.$banner->gambar_path);
    }

    public function test_mobile_banner_api_does_not_show_inactive_banner(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['peran' => 'admin', 'status_aktif' => true]);

        $this->actingAs($admin)
            ->post(route('admin.banner.store'), [
                'judul' => 'Banner Nonaktif',
                'subjudul' => 'Tidak tampil di aplikasi.',
                'gambar' => UploadedFile::fake()->image('nonaktif.png', 1200, 600),
                'urutan' => 1,
                'aktif' => 0,
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'banner']))
            ->assertSessionHasNoErrors();

        $banner = HomeBanner::query()->firstOrFail();

        $this->assertFalse((bool) $banner->aktif);

        // Banner nonaktif tetap tersimpan di admin, tapi tidak dikirim ke aplikasi mobile.
        $this->getJson('/api/home-banners')
            ->assertOk()
            ->assertJsonCount(0, 'data');
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
                'aktif' => 1,
            ])
            ->assertSessionHasErrors('gambar');
    }
}
