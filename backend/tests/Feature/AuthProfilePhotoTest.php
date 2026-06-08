<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_photo_is_persisted_and_returned_from_me_and_login(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'nama' => 'Ihsan Penumpang',
            'email' => 'ihsan-profile@example.com',
            'no_hp' => '081299900001',
            'peran' => 'penumpang',
            'status_aktif' => true,
            'password' => 'Password123',
        ]);

        Sanctum::actingAs($user);

        $uploadResponse = $this->postJson('/api/auth/upload-photo', [
            'foto_profil' => UploadedFile::fake()->image('profil-baru.jpg', 640, 640),
        ]);

        $uploadResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.foto_profil', fn (?string $path) => str_starts_with((string) $path, 'profiles/'))
            ->assertJsonPath('data.user.foto_profil_url', fn (?string $url) => str_starts_with((string) $url, 'http://') && str_contains((string) $url, '/storage/profiles/'));

        $path = $user->fresh()->foto_profil;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $meResponse = $this->getJson('/api/auth/me');
        $meResponse
            ->assertOk()
            ->assertJsonPath('data.foto_profil', $path)
            ->assertJsonPath('data.foto_profil_url', fn (?string $url) => str_starts_with((string) $url, 'http://') && str_contains((string) $url, '/storage/profiles/'));

        $loginResponse = $this->postJson('/api/auth/login', [
            'login' => 'ihsan-profile@example.com',
            'password' => 'Password123',
            'device_name' => 'profile-photo-test',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('data.user.foto_profil', $path)
            ->assertJsonPath('data.user.foto_profil_url', fn (?string $url) => str_starts_with((string) $url, 'http://') && str_contains((string) $url, '/storage/profiles/'));
    }
}
