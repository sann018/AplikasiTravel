<?php

namespace Tests\Feature;

use App\Models\Jadwal;
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
            ->assertSee('Aksi Lainnya')
            ->assertSee('Bersihkan Notifikasi Lama')
            ->assertSee('Tampilkan hanya belum dibaca')
            ->assertSee('href="'.route('admin.notifikasi.index', ['dibaca' => '0']).'"', false)
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

    public function test_admin_inbox_only_shows_notifications_for_current_admin(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();
        Notifikasi::query()->delete();
        $this->createNotification($admin, false, now(), 'Notifikasi khusus admin');
        $this->createNotification($passenger, false, now(), 'Notifikasi penumpang');

        $firstVisit = $this->actingAs($admin)
            ->get(route('admin.notifikasi.admin'))
            ->assertOk()
            ->assertSee('Notifikasi Admin')
            ->assertSee('Notifikasi Baru')
            ->assertSee('travs-notification-simple-row is-new', false)
            ->assertSee('Notifikasi khusus admin')
            ->assertDontSee('Notifikasi penumpang');

        $this->assertStringNotContainsString(' · ', $firstVisit->getContent());

        $this->actingAs($admin)
            ->get(route('admin.notifikasi.admin'))
            ->assertOk()
            ->assertSee('Notifikasi Lama')
            ->assertSee('travs-notification-simple-row is-old', false);
    }

    public function test_user_notification_log_only_shows_passenger_and_driver_notifications(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();
        $driver = User::query()->where('peran', 'pengemudi')->firstOrFail();
        Notifikasi::query()->delete();
        $this->createNotification($admin, false, now(), 'Pesan rahasia admin');
        $this->createNotification($passenger, false, now(), 'Notifikasi penumpang');
        $this->createNotification($driver, true, now()->subMinute(), 'Notifikasi pengemudi');

        $this->actingAs($admin)
            ->get(route('admin.notifikasi.user'))
            ->assertOk()
            ->assertSee('Log Notifikasi User')
            ->assertSee('Notifikasi Baru')
            ->assertSee('Notifikasi Lama')
            ->assertSee('travs-notification-group is-new', false)
            ->assertSee('travs-notification-group is-old', false)
            ->assertSee('Notifikasi penumpang')
            ->assertSee('Notifikasi pengemudi')
            ->assertDontSee('Pesan rahasia admin');
    }

    public function test_user_notification_log_is_hidden_from_sidebar_without_removing_its_route(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Log Notifikasi User');

        // Route lama tetap tersedia untuk kompatibilitas bookmark dan kebutuhan audit.
        $this->actingAs($admin)
            ->get(route('admin.notifikasi.user'))
            ->assertOk()
            ->assertSee('Log Notifikasi User');
    }

    public function test_admin_header_shows_notification_link_and_unread_badge(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        Notifikasi::query()->delete();
        $this->createNotification($admin, false, now(), 'Pemberitahuan baru');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-notification-link', false)
            ->assertSee('href="'.route('admin.notifikasi.admin').'"', false)
            ->assertSee('aria-label="Buka notifikasi, 1 belum dibaca"', false)
            ->assertSee('travs-topbar-notification-badge', false);
    }

    public function test_schedule_notification_uses_schedule_detail_even_when_reference_id_matches_booking(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $schedule = Jadwal::query()->whereHas('pemesanan')->firstOrFail();
        Notifikasi::query()->delete();
        Notifikasi::query()->create([
            'pengguna_id' => $admin->id,
            'judul' => 'Jadwal mendekati keberangkatan',
            'pesan' => 'Jadwal akan segera berangkat.',
            'jenis' => 'perjalanan',
            'referensi_id' => $schedule->id,
            'sudah_dibaca' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.notifikasi.admin'))
            ->assertOk()
            ->assertSee('Notifikasi Lama')
            ->assertSee('Buka Detail Jadwal')
            ->assertSee('href="'.route('admin.jadwal.show', $schedule).'"', false)
            ->assertDontSee('href="'.route('admin.pemesanan.show', $schedule->id).'"', false);
    }

    public function test_admin_inbox_cleanup_only_deletes_admin_read_notifications(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();
        $adminOldRead = $this->createNotification($admin, true, now()->subDays(31), 'Admin terbaca lama');
        $passengerOldRead = $this->createNotification($passenger, true, now()->subDays(31), 'Penumpang terbaca lama');

        $this->actingAs($admin)
            ->delete(route('admin.notifikasi.admin.history.clear'), ['period' => '30_days'])
            ->assertRedirect(route('admin.notifikasi.admin'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('notifikasi', ['id' => $adminOldRead->id]);
        $this->assertDatabaseHas('notifikasi', ['id' => $passengerOldRead->id]);
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

    public function test_admin_can_clear_user_notification_history_without_deleting_admin_inbox(): void
    {
        $admin = User::query()->where('peran', 'admin')->firstOrFail();
        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();
        $adminOldRead = $this->createNotification($admin, true, now()->subDays(31), 'Admin tetap aman');
        $passengerOldRead = $this->createNotification($passenger, true, now()->subDays(31), 'User terbaca lama');

        $this->actingAs($admin)
            ->delete(route('admin.notifikasi.user.history.clear'), ['period' => '30_days'])
            ->assertRedirect(route('admin.notifikasi.user'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notifikasi', ['id' => $adminOldRead->id]);
        $this->assertDatabaseMissing('notifikasi', ['id' => $passengerOldRead->id]);
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
