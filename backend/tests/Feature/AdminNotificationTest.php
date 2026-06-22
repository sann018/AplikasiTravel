<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_opening_admin_notification_page_marks_only_admin_notifications_as_read(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();
        $adminNotification = $this->createNotification($admin, false, now(), 'Untuk admin');
        $passengerNotification = $this->createNotification($passenger, false, now(), 'Untuk penumpang');

        $this->actingAs($admin)
            ->get(route('admin.notifikasi.index'))
            ->assertOk()
            ->assertSee('Bersihkan Notifikasi Lama')
            ->assertSee('Hapus Riwayat');

        $this->assertDatabaseHas('notifikasi', [
            'id' => $adminNotification->id,
            'sudah_dibaca' => true,
        ]);
        $this->assertDatabaseHas('notifikasi', [
            'id' => $passengerNotification->id,
            'sudah_dibaca' => false,
        ]);
    }

    public function test_admin_can_clear_old_read_history_without_deleting_unread_notifications(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();
        $oldRead = $this->createNotification($passenger, true, now()->subDays(31), 'Terbaca lama');
        $recentRead = $this->createNotification($passenger, true, now()->subDays(2), 'Terbaca baru');
        $oldUnread = $this->createNotification($passenger, false, now()->subDays(31), 'Belum dibaca');

        $this->actingAs($admin)
            ->delete(route('admin.notifikasi.history.clear'), ['period' => '30_days'])
            ->assertRedirect(route('admin.notifikasi.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('notifikasi', ['id' => $oldRead->id]);
        $this->assertDatabaseHas('notifikasi', ['id' => $recentRead->id]);
        $this->assertDatabaseHas('notifikasi', ['id' => $oldUnread->id]);
    }

    public function test_non_admin_cannot_clear_admin_notification_history(): void
    {
        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();

        $this->actingAs($passenger)
            ->delete(route('admin.notifikasi.history.clear'), ['period' => 'all_read'])
            ->assertForbidden();
    }

    public function test_admin_notification_cleanup_validates_period(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.notifikasi.index'))
            ->delete(route('admin.notifikasi.history.clear'), ['period' => 'unknown'])
            ->assertRedirect(route('admin.notifikasi.index'))
            ->assertSessionHasErrors('period');
    }

    private function createNotification(User $user, bool $isRead, \DateTimeInterface $createdAt, string $title): Notifikasi
    {
        $notification = Notifikasi::query()->create([
            'pengguna_id' => $user->id,
            'judul' => $title,
            'pesan' => 'Isi pengujian notifikasi admin.',
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
