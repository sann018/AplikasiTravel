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

    public function test_mobile_profile_accepts_valid_email_domains_and_requires_an_eleven_to_twelve_digit_phone(): void
    {
        $user = User::factory()->create([
            'email' => 'penumpang@gmail.com',
            'no_hp' => '081234567890',
            'peran' => 'penumpang',
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'nama' => 'Penumpang123',
            'email' => 'penumpang@',
            'no_hp' => '08123abc',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nama', 'email', 'no_hp']);

        $this->putJson('/api/auth/profile', [
            'nama' => 'Penumpang Baru',
            'email' => 'PENUMPANG.BARU@YAHOO.COM',
            'no_hp' => '08123456789',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.nama', 'Penumpang Baru')
            ->assertJsonPath('data.user.email', 'penumpang.baru@yahoo.com')
            ->assertJsonPath('data.user.no_hp', '08123456789');

        $this->putJson('/api/auth/profile', [
            'email' => 'mahasiswa@polnep.ac.id',
            'no_hp' => '08123456789',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'mahasiswa@polnep.ac.id');
    }

    public function test_mobile_password_change_accepts_six_to_twenty_characters_and_rejects_more(): void
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

        $tooLongPassword = str_repeat('A', 20).'1';
        $this->postJson('/api/auth/change-password', [
            'current_password' => 'Lama12!',
            'password' => $tooLongPassword,
            'password_confirmation' => $tooLongPassword,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'Lama12!',
            'password' => 'BaruPassword2026!',
            'password_confirmation' => 'BaruPassword2026!',
        ])->assertOk();

        $this->assertTrue(Hash::check('BaruPassword2026!', $user->fresh()->password));
    }
}
