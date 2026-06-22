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

    public function test_opening_notification_list_marks_returned_items_as_read(): void
    {
        $user = User::factory()->create(['peran' => 'pengemudi']);

        $notification = Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => 'Jadwal Baru',
            'pesan' => 'Ada jadwal yang perlu diperiksa.',
            'jenis' => 'perjalanan',
            'sudah_dibaca' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications?mark_as_read=1')
            ->assertOk()
            ->assertJsonPath('data.items.0.sudah_dibaca', true)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertDatabaseHas('notifikasi', [
            'id' => $notification->id,
            'sudah_dibaca' => true,
        ]);
    }

    public function test_background_list_request_does_not_mark_notifications_as_read(): void
    {
        $user = User::factory()->create(['peran' => 'penumpang']);

        $notification = Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => 'Pengingat',
            'pesan' => 'Notifikasi ini belum dibuka.',
            'jenis' => 'sistem',
            'sudah_dibaca' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->assertDatabaseHas('notifikasi', [
            'id' => $notification->id,
            'sudah_dibaca' => false,
        ]);
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

    public function test_user_can_delete_only_own_read_notifications_older_than_selected_period(): void
    {
        $user = User::factory()->create(['peran' => 'penumpang']);
        $otherUser = User::factory()->create(['peran' => 'pengemudi']);

        $oldRead = $this->createNotification($user, true, now()->subDays(31), 'Terbaca lama');
        $recentRead = $this->createNotification($user, true, now()->subDays(5), 'Terbaca baru');
        $oldUnread = $this->createNotification($user, false, now()->subDays(31), 'Belum terbaca');
        $otherOldRead = $this->createNotification($otherUser, true, now()->subDays(31), 'Milik akun lain');

        Sanctum::actingAs($user);

        $this->deleteJson('/api/notifications/history', ['period' => '30_days'])
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 1)
            ->assertJsonPath('data.unread_count', 1);

        $this->assertDatabaseMissing('notifikasi', ['id' => $oldRead->id]);
        $this->assertDatabaseHas('notifikasi', ['id' => $recentRead->id]);
        $this->assertDatabaseHas('notifikasi', ['id' => $oldUnread->id]);
        $this->assertDatabaseHas('notifikasi', ['id' => $otherOldRead->id]);
    }

    public function test_all_read_cleanup_keeps_unread_notifications_safe(): void
    {
        $user = User::factory()->create(['peran' => 'penumpang']);
        $read = $this->createNotification($user, true, now(), 'Sudah dibaca');
        $unread = $this->createNotification($user, false, now()->subDays(100), 'Belum dibaca');

        Sanctum::actingAs($user);

        $this->deleteJson('/api/notifications/history', ['period' => 'all_read'])
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 1)
            ->assertJsonPath('data.unread_count', 1);

        $this->assertDatabaseMissing('notifikasi', ['id' => $read->id]);
        $this->assertDatabaseHas('notifikasi', ['id' => $unread->id]);
    }

    public function test_cleanup_rejects_unknown_period(): void
    {
        $user = User::factory()->create(['peran' => 'penumpang']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/notifications/history', ['period' => '1_day'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_guest_cannot_access_notification_endpoints(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
        $this->postJson('/api/notifications/1/read')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
        $this->deleteJson('/api/notifications/history', ['period' => '30_days'])->assertUnauthorized();
    }

    private function createNotification(User $user, bool $isRead, \DateTimeInterface $createdAt, string $title): Notifikasi
    {
        $notification = Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => $title,
            'pesan' => 'Isi pengujian notifikasi.',
            'jenis' => 'sistem',
            'sudah_dibaca' => $isRead,
        ]);

        $notification->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $notification;
    }
}
