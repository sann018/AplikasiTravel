<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_laravel_reset_link_without_exposing_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Jika email terdaftar, tautan reset kata sandi telah dikirim.',
            )
            ->assertJsonMissingPath('data.reset_token');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_forgot_password_unknown_email_uses_same_generic_response(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'tidakada@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Jika email terdaftar, tautan reset kata sandi telah dikirim.',
            )
            ->assertJsonMissingPath('data.reset_token');

        Notification::assertNothingSent();
    }

    public function test_reset_password_with_valid_laravel_token_changes_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = $this->requestResetToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru.',
            );

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
        $this->assertTrue(Hash::check(
            'PasswordBaru123',
            $user->fresh()->password,
        ));
    }

    public function test_reset_password_with_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'token-palsu-yang-tidak-valid',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'errors.token.0',
                'Tautan reset tidak valid atau sudah kedaluwarsa.',
            );
    }

    public function test_reset_password_with_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = 'test-expired-token-123';

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes(61),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_reset_token_cannot_be_used_twice(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = $this->requestResetToken($user);

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ];

        $this->postJson('/api/auth/reset-password', $payload)->assertOk();
        $this->postJson('/api/auth/reset-password', $payload)
            ->assertUnprocessable();
    }

    public function test_forgot_and_reset_password_validate_input(): void
    {
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'bukan-email',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email']]);

        $this->postJson('/api/auth/reset-password', [])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => ['email', 'token', 'password'],
            ]);
    }

    public function test_reset_password_web_page_is_available_from_email_link(): void
    {
        $user = User::factory()->create();

        $this->get(route('password.reset', [
            'token' => 'contoh-token',
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertSee('Atur Ulang Kata Sandi')
            ->assertSee($user->email);
    }

    private function requestResetToken(User $user): string
    {
        $token = null;

        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->assertIsString($token);

        return $token;
    }
}
