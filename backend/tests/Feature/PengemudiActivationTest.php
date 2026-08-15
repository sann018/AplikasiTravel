<?php

namespace Tests\Feature;

use App\Models\Pengemudi;
use App\Models\User;
use App\Notifications\EmployeeAccountActivation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PengemudiActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_driver_and_sends_activation_link(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['peran' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.pengemudi.store'), [
                'nama' => 'Pengemudi Aktivasi',
                'email' => 'pengemudi.aktivasi@ruslytravel.com',
                'no_hp' => '081234567891',
                'nomor_sim' => '6102233435410011',
                'status' => 'aktif',
            ])
            ->assertRedirect(route('admin.pengemudi.index'));

        $user = User::query()->where('email', 'pengemudi.aktivasi@ruslytravel.com')->firstOrFail();
        $this->assertSame('pengemudi', $user->peran);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, EmployeeAccountActivation::class);
    }

    public function test_driver_cannot_login_before_finishing_activation(): void
    {
        $user = User::factory()->unverified()->create([
            'peran' => 'pengemudi',
            'password' => Hash::make('Password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'Password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.login.0', 'Akun Pengemudi belum diaktifkan. Buka tautan aktivasi yang dikirim ke email akun.');

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_activation_link_sets_driver_password_and_verifies_email(): void
    {
        $user = User::factory()->unverified()->create(['peran' => 'pengemudi']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PasswordBaru12!',
            'password_confirmation' => 'PasswordBaru12!',
        ])->assertRedirect(route('password.reset.complete'));

        $updated = $user->fresh();
        $this->assertNotNull($updated->email_verified_at);
        $this->assertTrue(Hash::check('PasswordBaru12!', $updated->password));
    }

    public function test_email_change_revokes_driver_session_and_sends_new_activation_link(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['peran' => 'admin']);
        $user = User::factory()->create([
            'nama' => 'Pengemudi Lama',
            'peran' => 'pengemudi',
        ]);
        $driver = Pengemudi::query()->create([
            'pengguna_id' => $user->id,
            'nomor_sim' => '6102233435410012',
            'status' => 'aktif',
        ]);
        $user->createToken('driver-test', ['mobile-driver']);

        $this->actingAs($admin)
            ->put(route('admin.pengemudi.update', $driver), [
                'nama' => $user->nama,
                'email' => 'pengemudi.baru@ruslytravel.com',
                'no_hp' => $user->no_hp,
                'nomor_sim' => $driver->nomor_sim,
                'status' => 'aktif',
            ])
            ->assertRedirect(route('admin.pengemudi.index'));

        $updated = $user->fresh();
        $this->assertNull($updated->email_verified_at);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        Notification::assertSentTo($updated, EmployeeAccountActivation::class);
    }

    public function test_disabling_driver_revokes_existing_mobile_session(): void
    {
        $admin = User::factory()->create(['peran' => 'admin']);
        $user = User::factory()->create([
            'nama' => 'Pengemudi Nonaktif',
            'peran' => 'pengemudi',
        ]);
        $driver = Pengemudi::query()->create([
            'pengguna_id' => $user->id,
            'nomor_sim' => '6102233435410013',
            'status' => 'aktif',
        ]);
        $user->createToken('driver-test', ['mobile-driver']);

        $this->actingAs($admin)
            ->put(route('admin.pengemudi.update', $driver), [
                'nama' => $user->nama,
                'email' => $user->email,
                'no_hp' => $user->no_hp,
                'nomor_sim' => $driver->nomor_sim,
                'status' => 'nonaktif',
            ])
            ->assertRedirect(route('admin.pengemudi.index'));

        $this->assertFalse($user->fresh()->status_aktif);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_driver_cannot_change_managed_email_from_mobile(): void
    {
        $user = User::factory()->create(['peran' => 'pengemudi']);
        Sanctum::actingAs($user, ['mobile-driver']);

        $this->putJson('/api/auth/profile', [
            'email' => 'ubah.sendiri@ruslytravel.com',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email Pengemudi hanya dapat diubah oleh Admin melalui web agar aktivasi akun tetap terkontrol.');
    }
}
