<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_authenticated_user_can_list_own_notifications(): void
    {
        $user = User::factory()->create(['peran' => 'penumpang']);

        Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => 'Pembayaran Berhasil',
            'pesan' => 'Tiket QR sudah tersedia.',
            'jenis' => 'pembayaran',
            'referensi_id' => 1,
            'sudah_dibaca' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.judul', 'Pembayaran Berhasil');
    }

    public function test_user_cannot_see_other_users_notifications(): void
    {
        $userA = User::query()->where('peran', 'penumpang')->firstOrFail();
        $userB = User::factory()->create(['peran' => 'penumpang']);

        Notifikasi::query()->create([
            'pengguna_id' => $userA->id,
            'judul' => 'Notif A',
            'pesan' => 'Milik user A.',
            'jenis' => 'sistem',
            'sudah_dibaca' => false,
        ]);

        Sanctum::actingAs($userB);

        $response = $this->getJson('/api/notifications');

        $response
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_since_id_filter_returns_only_newer_notifications(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();

        $old = Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => 'Lama',
            'pesan' => 'Notif lama.',
            'jenis' => 'sistem',
            'sudah_dibaca' => true,
        ]);

        $new = Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => 'Baru',
            'pesan' => 'Notif baru.',
            'jenis' => 'pembayaran',
            'sudah_dibaca' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/notifications?since_id={$old->id}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $new->id);
    }

    public function test_unread_count_endpoint_returns_correct_count(): void
    {
        $user = User::factory()->create(['peran' => 'penumpang']);

        Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => 'Unread',
            'pesan' => 'Belum dibaca.',
            'jenis' => 'perjalanan',
            'sudah_dibaca' => false,
        ]);

        Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => 'Read',
            'pesan' => 'Sudah dibaca.',
            'jenis' => 'perjalanan',
            'sudah_dibaca' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications/unread-count');

        $response
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_mark_single_notification_as_read(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();

        $notif = Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => 'Test',
            'pesan' => 'Akan ditandai dibaca.',
            'jenis' => 'sistem',
            'sudah_dibaca' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/notifications/{$notif->id}/read");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sudah_dibaca', true);

        $this->assertDatabaseHas('notifikasi', [
            'id' => $notif->id,
            'sudah_dibaca' => true,
        ]);
    }

    public function test_cannot_mark_other_users_notification_as_read(): void
    {
        $userA = User::query()->where('peran', 'penumpang')->firstOrFail();
        $userB = User::factory()->create(['peran' => 'penumpang']);

        $notif = Notifikasi::query()->create([
            'pengguna_id' => $userA->id,
            'judul' => 'Milik A',
            'pesan' => 'Bukan milik B.',
            'jenis' => 'sistem',
            'sudah_dibaca' => false,
        ]);

        Sanctum::actingAs($userB);

        $response = $this->postJson("/api/notifications/{$notif->id}/read");

        $response->assertNotFound();

        $this->assertDatabaseHas('notifikasi', [
            'id' => $notif->id,
            'sudah_dibaca' => false,
        ]);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create(['peran' => 'penumpang']);

        Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => 'A',
            'pesan' => 'Notif A.',
            'jenis' => 'pembayaran',
            'sudah_dibaca' => false,
        ]);

        Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => 'B',
            'pesan' => 'Notif B.',
            'jenis' => 'perjalanan',
            'sudah_dibaca' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/notifications/read-all');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.affected', 2);

        $this->assertDatabaseMissing('notifikasi', [
            'pengguna_id' => $user->id,
            'sudah_dibaca' => false,
        ]);
    }

    public function test_guest_cannot_access_notification_endpoints(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
        $this->postJson('/api/notifications/1/read')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
    }
}
