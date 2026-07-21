<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_profile_requires_gmail_and_an_eleven_to_twelve_digit_phone(): void
    {
        $user = User::factory()->create([
            'email' => 'penumpang@gmail.com',
            'no_hp' => '081234567890',
            'peran' => 'penumpang',
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'email' => 'penumpang@example.com',
            'no_hp' => '08123abc',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'no_hp']);

        $this->putJson('/api/auth/profile', [
            'email' => 'PENUMPANG.BARU@GMAIL.COM',
            'no_hp' => '08123456789',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'penumpang.baru@gmail.com')
            ->assertJsonPath('data.user.no_hp', '08123456789');
    }

    public function test_mobile_password_change_uses_complex_six_to_eight_character_password(): void
    {
        $user = User::factory()->create([
            'peran' => 'pengemudi',
            'password' => Hash::make('Lama12!'),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'Lama12!',
            'password' => 'abcdef',
            'password_confirmation' => 'abcdef',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'Lama12!',
            'password' => 'Lama12!',
            'password_confirmation' => 'Lama12!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'Lama12!',
            'password' => 'Baru34!',
            'password_confirmation' => 'Baru34!',
        ])->assertOk();

        $this->assertTrue(Hash::check('Baru34!', $user->fresh()->password));
    }
}
