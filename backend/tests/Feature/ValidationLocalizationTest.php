<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidationLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_exists_validation_uses_a_friendly_indonesian_message(): void
    {
        $validator = Validator::make(
            ['jadwal_id' => 999999],
            ['jadwal_id' => ['required', 'integer', 'exists:jadwal,id']],
        );

        $message = $validator->errors()->first('jadwal_id');

        $this->assertSame('Jadwal yang dipilih tidak tersedia atau sudah tidak berlaku.', $message);
        $this->assertStringNotContainsString('validation.', $message);
        $this->assertStringNotContainsString('jadwal_id', $message);
    }

    public function test_common_validation_rules_use_friendly_attribute_names(): void
    {
        $validator = Validator::make(
            [
                'no_hp' => 'abc',
                'foto_profil' => 'bukan-file',
            ],
            [
                'nama' => ['required'],
                'email' => ['required', 'email'],
                'no_hp' => ['digits_between:10,12'],
                'foto_profil' => ['file'],
            ],
        );

        $messages = $validator->errors()->all();

        $this->assertContains('Nama lengkap wajib diisi.', $messages);
        $this->assertContains('Email wajib diisi.', $messages);
        $this->assertContains('Nomor HP harus terdiri dari 10 sampai 12 digit.', $messages);
        $this->assertContains('Foto profil harus berupa file.', $messages);

        foreach ($messages as $message) {
            $this->assertStringNotContainsString('validation.', $message);
        }
    }

    public function test_booking_api_never_returns_a_raw_validation_key(): void
    {
        $user = User::factory()->create(['peran' => 'penumpang']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/penumpang/bookings/estimate', [
            'jadwal_id' => 999999,
            'seat_count' => 1,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.jadwal_id.0',
                'Jadwal yang dipilih tidak tersedia atau sudah tidak berlaku.',
            );

        $this->assertStringNotContainsString('validation.', $response->getContent());
    }
}
