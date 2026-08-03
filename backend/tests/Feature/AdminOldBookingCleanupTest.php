<?php

namespace Tests\Feature;

use App\Models\ItemBagasi;
use App\Models\Notifikasi;
use App\Models\Pemesanan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminOldBookingCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_cleanup_form_requires_safe_past_date_range_and_uses_browser_confirmation(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $bookingCount = Pemesanan::query()->count();

        $this
            ->actingAs($admin)
            ->get(route('admin.pemesanan.index'))
            ->assertOk()
            ->assertSee('Hapus Riwayat Pemesanan Lama')
            ->assertSee('name="hapus_tanggal_mulai"', false)
            ->assertSee('name="hapus_tanggal_selesai"', false)
            ->assertDontSee('name="konfirmasi_hapus"', false)
            ->assertSee('Apakah Anda yakin ingin menghapus data pemesanan tanggal', false)
            ->assertSee('class="row g-3 align-items-end"', false)
            ->assertDontSee('border-danger', false)
            ->assertDontSee('alert-warning', false);

        $this
            ->actingAs($admin)
            ->delete(route('admin.pemesanan.old.destroy'), [
                'hapus_tanggal_mulai' => '2025-02-01',
                'hapus_tanggal_selesai' => '2025-01-01',
            ])
            ->assertSessionHasErrors('hapus_tanggal_selesai');

        $this->assertSame($bookingCount, Pemesanan::query()->count());
    }

    public function test_admin_deletes_only_terminal_bookings_in_range_with_related_data_and_files(): void
    {
        Storage::fake('public');
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $target = Pemesanan::query()
            ->with(['detailPemesanan.tiketElektronik', 'pembayaran'])
            ->where('kode_booking', 'TRAVS-DEMO-003')
            ->firstOrFail();
        $activeInRange = Pemesanan::query()
            ->where('kode_booking', 'TRAVS-DEMO-002')
            ->firstOrFail();
        $terminalOutsideRange = Pemesanan::query()
            ->where('kode_booking', 'TRAVS-DEMO-001')
            ->firstOrFail();

        $target->timestamps = false;
        $target->forceFill(['created_at' => '2025-01-15 10:00:00'])->saveQuietly();
        $activeInRange->timestamps = false;
        $activeInRange->forceFill(['created_at' => '2025-01-20 10:00:00'])->saveQuietly();
        $terminalOutsideRange->timestamps = false;
        $terminalOutsideRange
            ->forceFill([
                'created_at' => '2024-12-20 10:00:00',
                'status_pemesanan' => 'selesai',
            ])
            ->saveQuietly();

        $detailIds = $target->detailPemesanan->pluck('id')->all();
        $ticketIds = $target->detailPemesanan
            ->pluck('tiketElektronik.id')
            ->filter()
            ->all();
        $seatIds = $target->detailPemesanan->pluck('kursi_jadwal_id')->all();
        $paymentId = $target->pembayaran?->id;

        $firstPhoto = 'baggage/riwayat-lama-1.jpg';
        $secondPhoto = 'baggage/riwayat-lama-2.jpg';
        Storage::disk('public')->put($firstPhoto, 'foto-pertama');
        Storage::disk('public')->put($secondPhoto, 'foto-kedua');

        $baggage = ItemBagasi::query()->create([
            'pemesanan_id' => $target->id,
            'deskripsi_item' => 'Bagasi riwayat lama',
            'jumlah_item' => 1,
            'foto_url' => $firstPhoto,
            'foto_urls' => [$firstPhoto, $secondPhoto],
            'biaya_item' => 0,
        ]);

        $bookingNotification = Notifikasi::query()->create([
            'pengguna_id' => $target->pengguna_id,
            'judul' => 'Pembayaran berhasil dikonfirmasi',
            'pesan' => 'Notifikasi riwayat pemesanan lama.',
            'jenis' => 'pembayaran',
            'referensi_id' => $target->id,
        ]);
        $scheduleNotification = Notifikasi::query()->create([
            'pengguna_id' => $target->pengguna_id,
            'judul' => 'Tugas perjalanan baru',
            'pesan' => 'Referensi ini adalah jadwal dan tidak boleh ikut terhapus.',
            'jenis' => 'perjalanan',
            'referensi_id' => $target->id,
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.pemesanan.old.destroy'), [
                'hapus_tanggal_mulai' => '2025-01-01',
                'hapus_tanggal_selesai' => '2025-01-31',
            ])
            ->assertRedirect(route('admin.pemesanan.index'))
            ->assertSessionHas('status', '1 riwayat pemesanan lama berhasil dihapus.');

        $this->assertDatabaseMissing('pemesanan', ['id' => $target->id]);
        $this->assertDatabaseHas('pemesanan', ['id' => $activeInRange->id]);
        $this->assertDatabaseHas('pemesanan', ['id' => $terminalOutsideRange->id]);
        $this->assertDatabaseMissing('pembayaran', ['id' => $paymentId]);
        $this->assertDatabaseMissing('item_bagasi', ['id' => $baggage->id]);
        $this->assertDatabaseMissing('notifikasi', ['id' => $bookingNotification->id]);
        $this->assertDatabaseHas('notifikasi', ['id' => $scheduleNotification->id]);

        foreach ($detailIds as $detailId) {
            $this->assertDatabaseMissing('detail_pemesanan', ['id' => $detailId]);
        }
        foreach ($ticketIds as $ticketId) {
            $this->assertDatabaseMissing('tiket_elektronik', ['id' => $ticketId]);
        }
        foreach ($seatIds as $seatId) {
            $this->assertDatabaseHas('kursi_jadwal', [
                'id' => $seatId,
                'status_kursi' => 'tersedia',
                'dikunci_oleh' => null,
            ]);
        }

        Storage::disk('public')->assertMissing($firstPhoto);
        Storage::disk('public')->assertMissing($secondPhoto);
    }
}
