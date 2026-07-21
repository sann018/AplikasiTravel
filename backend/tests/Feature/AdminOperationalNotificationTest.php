<?php

namespace Tests\Feature;

use App\Models\ItemBagasi;
use App\Models\Jadwal;
use App\Models\Notifikasi;
use App\Models\Pemesanan;
use App\Models\User;
use App\Services\AdminOperationalNotificationService;
use App\Services\DepartureReminderService;
use App\Services\SettingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Pemesanan $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::query()->where('peran', 'admin')->firstOrFail();
        $passenger = User::query()->where('peran', 'penumpang')->firstOrFail();
        $schedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();

        $this->booking = Pemesanan::query()->create([
            'pengguna_id' => $passenger->id,
            'jadwal_id' => $schedule->id,
            'kode_booking' => 'TRAVS-ADMIN-NOTIF',
            'jumlah_kursi' => 1,
            'batas_kunci_kursi' => now()->addMinutes(30),
            'batas_pembayaran' => now()->addMinutes(5),
            'alamat_jemput' => 'Pontianak',
            'subtotal_tiket' => 200000,
            'total_harga' => 200000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);

        $this->booking->pembayaran()->create([
            'order_id_gateway' => 'ADMIN-NOTIF-CASH',
            'metode_bayar' => 'cash',
            'jumlah_bayar' => 200000,
            'status_bayar' => 'menunggu',
            'expired_at' => now()->addMinutes(5),
        ]);
    }

    public function test_new_cash_booking_notifies_admin_about_booking_and_cash_confirmation(): void
    {
        app(AdminOperationalNotificationService::class)->bookingCreated($this->booking);

        $this->assertAdminNotificationExists('Pemesanan baru masuk');
        $this->assertAdminNotificationExists('Pembayaran tunai menunggu konfirmasi');
    }

    public function test_payment_success_failed_and_expired_notify_admin(): void
    {
        $service = app(AdminOperationalNotificationService::class);

        $service->paymentStatusChanged($this->booking, 'berhasil');
        $service->paymentStatusChanged($this->booking, 'gagal');
        $service->paymentStatusChanged($this->booking, 'kedaluwarsa');

        $this->assertAdminNotificationExists('Pembayaran berhasil');
        $this->assertAdminNotificationExists('Pembayaran gagal');
        $this->assertAdminNotificationExists('Pembayaran kedaluwarsa');
    }

    public function test_schedule_near_departure_notifies_admin_once(): void
    {
        $schedule = $this->booking->jadwal;
        $schedule->update(['waktu_berangkat' => now()->addMinutes(45)]);
        $service = app(AdminOperationalNotificationService::class);

        $service->scheduleNearDeparture($schedule);
        Notifikasi::query()
            ->where('pengguna_id', $this->admin->id)
            ->where('judul', 'Jadwal mendekati keberangkatan')
            ->update(['sudah_dibaca' => true]);
        $service->scheduleNearDeparture($schedule);

        $this->assertSame(1, Notifikasi::query()
            ->where('pengguna_id', $this->admin->id)
            ->where('judul', 'Jadwal mendekati keberangkatan')
            ->count());
        $this->assertDatabaseHas('notifikasi', [
            'pengguna_id' => $this->admin->id,
            'judul' => 'Jadwal mendekati keberangkatan',
            'sudah_dibaca' => true,
        ]);
    }

    public function test_admin_departure_reminder_stays_enabled_when_passenger_reminder_is_disabled(): void
    {
        Notifikasi::query()->delete();
        $schedule = $this->booking->jadwal;
        $schedule->update([
            'status' => 'dibuka',
            'waktu_berangkat' => now()->addMinutes(45),
        ]);
        app(SettingService::class)->updateMany([
            'notif_departure_reminder' => false,
        ], $this->admin->id);

        app(DepartureReminderService::class)->sendUpcomingReminders();

        $this->assertDatabaseHas('notifikasi', [
            'pengguna_id' => $this->admin->id,
            'judul' => 'Jadwal mendekati keberangkatan',
            'referensi_id' => $schedule->id,
        ]);
        $this->assertDatabaseMissing('notifikasi', [
            'pengguna_id' => $this->booking->pengguna_id,
            'judul' => 'Pengingat keberangkatan',
        ]);
    }

    public function test_departed_schedule_notifies_admin_once(): void
    {
        $schedule = $this->booking->jadwal;
        $service = app(AdminOperationalNotificationService::class);

        $service->scheduleDeparted($schedule);
        $service->scheduleDeparted($schedule);

        $this->assertSame(1, Notifikasi::query()
            ->where('pengguna_id', $this->admin->id)
            ->where('judul', 'Armada telah berangkat')
            ->where('referensi_id', $schedule->id)
            ->count());
    }

    public function test_almost_full_seats_notify_admin(): void
    {
        $schedule = $this->booking->jadwal;
        $schedule->kursi()->update(['status_kursi' => 'dipesan']);
        $schedule->kursi()->firstOrFail()->update(['status_kursi' => 'tersedia']);

        app(AdminOperationalNotificationService::class)->capacityAlmostFull($schedule);

        $this->assertAdminNotificationExists('Kapasitas perjalanan hampir penuh');
    }

    public function test_almost_full_baggage_capacity_notifies_admin(): void
    {
        app(SettingService::class)->updateMany([
            'baggage_max_total_weight_per_vehicle' => 10,
        ], $this->admin->id);

        ItemBagasi::query()->create([
            'pemesanan_id' => $this->booking->id,
            'deskripsi_item' => 'Bagasi uji',
            'metode_hitung' => 'berat',
            'berat_kg' => 9,
            'jumlah_item' => 1,
            'biaya_item' => 0,
        ]);

        app(AdminOperationalNotificationService::class)->capacityAlmostFull($this->booking->jadwal);

        $this->assertAdminNotificationExists('Kapasitas perjalanan hampir penuh');
    }

    private function assertAdminNotificationExists(string $title): void
    {
        $this->assertDatabaseHas('notifikasi', [
            'pengguna_id' => $this->admin->id,
            'judul' => $title,
            'sudah_dibaca' => false,
        ]);
    }
}
