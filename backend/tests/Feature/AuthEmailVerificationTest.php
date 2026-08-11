<?php

namespace Tests\Feature;

use App\Mail\AccountVerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_email_verification_before_login_token_is_issued(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'nama' => 'Penumpang Baru',
            'email' => 'penumpang.baru@polnep.ac.id',
            'no_hp' => '081234567899',
            'password' => 'PasswordPenumpang12!',
            'password_confirmation' => 'PasswordPenumpang12!',
            'device_name' => 'pengujian',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requires_email_verification', true)
            ->assertJsonMissingPath('data.token');

        $user = User::query()
            ->where('email', 'penumpang.baru@polnep.ac.id')
            ->firstOrFail();
        $verificationCode = $this->verificationCodeSentTo($user);

        $this->assertNotNull($user->otp_code);
        $this->assertTrue(Hash::check($verificationCode, $user->otp_code));
        $this->assertNull($user->email_verified_at);
        $this->assertEqualsWithDelta(
            now()->addMinutes(15)->timestamp,
            $user->otp_expires_at->timestamp,
            5,
        );

        $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'PasswordPenumpang12!',
        ])
            ->assertForbidden()
            ->assertJsonPath('data.requires_email_verification', true);

        $verifyResponse = $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => $verificationCode,
            'device_name' => 'pengujian',
        ]);

        $verifyResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $verifiedUser = $user->fresh();
        $this->assertNotNull($verifiedUser->email_verified_at);
        $this->assertNull($verifiedUser->otp_code);
        $this->assertNull($verifiedUser->otp_expires_at);
    }

    public function test_expired_or_incorrect_verification_code_is_rejected(): void
    {
        $user = User::factory()->unverified()->create([
            'otp_code' => Hash::make('123456'),
            'otp_expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '123456',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.code.0',
                'Kode verifikasi tidak valid atau sudah kedaluwarsa.',
            );

        $user->forceFill([
            'otp_expires_at' => now()->addMinutes(15),
        ])->save();

        $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '654321',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.code.0',
                'Kode verifikasi tidak valid atau sudah kedaluwarsa.',
            );
    }

    public function test_resend_verification_uses_generic_response_and_replaces_code(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create([
            'otp_code' => Hash::make('111111'),
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/auth/resend-verification-code', [
            'email' => $user->email,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Jika email terdaftar dan belum diverifikasi, kode verifikasi telah dikirim.',
            );

        $newCode = $this->verificationCodeSentTo($user);
        $this->assertNotSame('111111', $newCode);
        $this->assertTrue(Hash::check($newCode, $user->fresh()->otp_code));

        $this->postJson('/api/auth/resend-verification-code', [
            'email' => 'tidakada@example.com',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Jika email terdaftar dan belum diverifikasi, kode verifikasi telah dikirim.',
            );
    }

    public function test_registration_can_retry_an_existing_unverified_passenger_without_duplicate_data(): void
    {
        Mail::fake();

        $payload = [
            'nama' => 'Penumpang Pending',
            'email' => 'pending@gmail.com',
            'no_hp' => '081234567877',
            'password' => 'Pass12!',
            'password_confirmation' => 'Pass12!',
        ];

        $this->postJson('/api/auth/register', $payload)->assertCreated();

        $this->postJson('/api/auth/register', [
            ...$payload,
            'nama' => 'Penumpang Diperbarui',
            'password' => 'Pass34!',
            'password_confirmation' => 'Pass34!',
        ])->assertCreated();

        $this->assertSame(
            1,
            User::query()->where('email', 'pending@gmail.com')->count(),
        );

        $user = User::query()->where('email', 'pending@gmail.com')->firstOrFail();
        $this->assertSame('Penumpang Diperbarui', $user->nama);
        $this->assertTrue(Hash::check('Pass34!', $user->password));
        $this->assertNull($user->email_verified_at);
        Mail::assertSent(AccountVerificationCodeMail::class, 2);
    }

    public function test_registration_rejects_invalid_email_phone_outside_limit_and_weak_password(): void
    {
        $this->postJson('/api/auth/register', [
            'nama' => 'Penumpang Tidak Valid 123',
            'email' => 'penumpang@',
            'no_hp' => '08123abc',
            'password' => 'abcdef',
            'password_confirmation' => 'abcdef',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nama', 'email', 'no_hp', 'password']);

        $tooLongPassword = str_repeat('A', 20).'1';
        $this->postJson('/api/auth/register', [
            'nama' => 'Penumpang Valid',
            'email' => 'penumpang.valid@yahoo.com',
            'no_hp' => '081234567890',
            'password' => $tooLongPassword,
            'password_confirmation' => $tooLongPassword,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/auth/register', [
            'nama' => str_repeat('A', 51),
            'email' => 'nama.panjang@proton.me',
            'no_hp' => '081234567891',
            'password' => 'PassValid12!',
            'password_confirmation' => 'PassValid12!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nama');
    }

    public function test_registration_accepts_common_valid_email_domains(): void
    {
        Mail::fake();

        $emails = [
            'penumpang.proton@proton.me',
            'penumpang.yahoo@yahoo.com',
            'penumpang.outlook@outlook.com',
            'penumpang.kampus@polnep.ac.id',
        ];

        foreach ($emails as $index => $email) {
            $response = $this->postJson('/api/auth/register', [
                'nama' => 'Penumpang Uji',
                'email' => $email,
                'no_hp' => '08123456'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'password' => 'PassValid12!',
                'password_confirmation' => 'PassValid12!',
            ]);

            $response
                ->assertCreated()
                ->assertJsonPath('data.email', $email)
                ->assertJsonPath('data.expires_in_minutes', 15);

            Mail::assertSent(
                AccountVerificationCodeMail::class,
                fn (AccountVerificationCodeMail $mail): bool => $mail->hasTo($email),
            );
        }
    }

    private function verificationCodeSentTo(User $user): string
    {
        $code = null;

        Mail::assertSent(
            AccountVerificationCodeMail::class,
            function (AccountVerificationCodeMail $mail) use ($user, &$code): bool {
                if (! $mail->hasTo($user->email)) {
                    return false;
                }

                $code = $mail->token;

                return true;
            },
        );

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        return $code;
    }
}
