<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Test TDD untuk fitur forgot/reset password end-to-end.
 * Memastikan alur lupa password aman, token sekali pakai,
 * dan tidak membocorkan informasi sensitif (rules #26).
 */
class AuthPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    // =========================================================================
    // Test: Forgot password dengan email terdaftar menghasilkan token
    // =========================================================================

    public function test_forgot_password_with_valid_email_returns_reset_token(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['reset_token', 'expires_in_minutes']]);

        // Token harus tersimpan di database (hashed)
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    // =========================================================================
    // Test: Forgot password dengan email tidak terdaftar tetap sukses (tidak bocor)
    // =========================================================================

    public function test_forgot_password_with_unknown_email_does_not_leak_info(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'tidakada@example.com',
        ]);

        // Harus tetap 200 OK agar tidak membocorkan apakah email terdaftar
        $response
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // =========================================================================
    // Test: Reset password dengan token valid berhasil mengubah password
    // =========================================================================

    public function test_reset_password_with_valid_token_changes_password(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();

        // Step 1: Request forgot password
        $forgotResponse = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $token = $forgotResponse->json('data.reset_token');

        // Step 2: Reset password dengan token
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password berhasil direset. Silakan login dengan password baru.');

        // Step 3: Token harus sudah dihapus (sekali pakai)
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);

        // Step 4: Login dengan password baru harus berhasil
        $loginResponse = $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'PasswordBaru123',
        ]);

        $loginResponse->assertOk()->assertJsonPath('success', true);
    }

    // =========================================================================
    // Test: Reset password dengan token salah ditolak
    // =========================================================================

    public function test_reset_password_with_invalid_token_is_rejected(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();

        // Request forgot password dulu agar ada record
        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        // Coba reset dengan token palsu
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'token-palsu-yang-tidak-valid',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // =========================================================================
    // Test: Reset password dengan token kedaluwarsa ditolak
    // =========================================================================

    public function test_reset_password_with_expired_token_is_rejected(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $token = 'test-expired-token-123';

        // Sisipkan token yang sudah kedaluwarsa (dibuat 2 jam lalu)
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Token kedaluwarsa harus dihapus
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    // =========================================================================
    // Test: Token tidak bisa digunakan dua kali
    // =========================================================================

    public function test_reset_token_cannot_be_used_twice(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();

        // Request token
        $forgotResponse = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $token = $forgotResponse->json('data.reset_token');

        // Reset pertama — harus berhasil
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertOk();

        // Reset kedua dengan token yang sama — harus ditolak
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'PasswordLain456',
            'password_confirmation' => 'PasswordLain456',
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // Test: Validasi input forgot password
    // =========================================================================

    public function test_forgot_password_validates_email_format(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'bukan-email',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    // =========================================================================
    // Test: Validasi input reset password
    // =========================================================================

    public function test_reset_password_validates_required_fields(): void
    {
        $response = $this->postJson('/api/auth/reset-password', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $errors = $response->json('errors');
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('token', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    // =========================================================================
    // Test: Reset password membutuhkan password minimal 8 karakter
    // =========================================================================

    public function test_reset_password_requires_minimum_password_length(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();

        $forgotResponse = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $token = $forgotResponse->json('data.reset_token');

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);
    }
}
