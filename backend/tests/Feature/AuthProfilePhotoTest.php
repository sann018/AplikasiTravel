<?php

namespace Tests\Feature;

use App\Models\Pengemudi;
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

    public function test_driver_can_update_phone_and_license_number_from_profile(): void
    {
        $driver = User::factory()->create([
            'nama' => 'Bowo Pengemudi',
            'email' => 'bowo-driver@example.com',
            'no_hp' => '081299900002',
            'peran' => 'pengemudi',
            'status_aktif' => true,
        ]);
        Pengemudi::query()->create([
            'pengguna_id' => $driver->id,
            'nomor_sim' => '6102233435410001',
            'status' => 'aktif',
        ]);

        Sanctum::actingAs($driver);

        $response = $this->putJson('/api/auth/profile', [
            'no_hp' => '081299900099',
            'nomor_sim' => '6102233435990002',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.no_hp', '081299900099')
            ->assertJsonPath('data.user.nomor_sim', '6102233435990002');

        $this->assertDatabaseHas('pengguna', [
            'id' => $driver->id,
            'no_hp' => '081299900099',
        ]);
        $this->assertDatabaseHas('pengemudi', [
            'pengguna_id' => $driver->id,
            'nomor_sim' => '6102233435990002',
        ]);
    }

    public function test_driver_license_number_must_use_sixteen_digit_nik(): void
    {
        $driver = User::factory()->create([
            'peran' => 'pengemudi',
            'status_aktif' => true,
        ]);
        Pengemudi::query()->create([
            'pengguna_id' => $driver->id,
            'nomor_sim' => '6102233435410003',
            'status' => 'aktif',
        ]);

        Sanctum::actingAs($driver);

        $this->putJson('/api/auth/profile', [
            'nomor_sim' => '610223343541',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nomor_sim');
    }
}
