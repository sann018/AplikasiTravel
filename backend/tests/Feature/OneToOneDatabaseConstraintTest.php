<?php

namespace Tests\Feature;

use App\Models\DetailPemesanan;
use App\Models\Jadwal;
use App\Models\KursiJadwal;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\Pengemudi;
use App\Models\TiketElektronik;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneToOneDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_user_cannot_have_two_driver_profiles(): void
    {
        $driver = Pengemudi::query()->firstOrFail();

        $this->expectException(QueryException::class);

        Pengemudi::query()->create([
            'pengguna_id' => $driver->pengguna_id,
            'nomor_sim' => '6102233435419999',
            'status' => 'aktif',
            'status_verifikasi' => 'terverifikasi',
        ]);
    }

    public function test_user_without_driver_profile_can_receive_one(): void
    {
        $user = User::factory()->create([
            'peran' => 'pengemudi',
            'status_aktif' => true,
        ]);

        $driver = Pengemudi::query()->create([
            'pengguna_id' => $user->id,
            'nomor_sim' => '6102233435419998',
            'status' => 'aktif',
            'status_verifikasi' => 'terverifikasi',
        ]);

        $this->assertSame($user->id, $driver->pengguna_id);
        $this->assertSame(1, Pengemudi::query()->where('pengguna_id', $user->id)->count());
    }

    public function test_booking_cannot_have_two_payment_records(): void
    {
        $booking = $this->makeBookingWithoutPayment();

        Pembayaran::query()->create([
            'pemesanan_id' => $booking->id,
            'order_id_gateway' => 'ONE-PAYMENT-001',
            'metode_bayar' => 'cash',
            'jumlah_bayar' => 200000,
            'status_bayar' => 'menunggu',
        ]);

        $this->expectException(QueryException::class);

        Pembayaran::query()->create([
            'pemesanan_id' => $booking->id,
            'order_id_gateway' => 'ONE-PAYMENT-002',
            'metode_bayar' => 'cash',
            'jumlah_bayar' => 200000,
            'status_bayar' => 'menunggu',
        ]);
    }

    public function test_gateway_order_id_cannot_belong_to_two_payments(): void
    {
        $bookings = Pemesanan::query()->take(2)->get();
        $this->assertCount(2, $bookings);

        $firstPayment = $bookings[0]->pembayaran()->firstOrFail();
        $secondPayment = $bookings[1]->pembayaran()->firstOrFail();

        $this->expectException(QueryException::class);

        $secondPayment->update([
            'order_id_gateway' => $firstPayment->order_id_gateway,
        ]);
    }

    public function test_payment_amount_snapshots_use_integer_casts(): void
    {
        $payment = Pembayaran::query()->firstOrFail();
        $payment->update([
            'nominal_transaksi' => 400000,
            'biaya_admin_provider' => 4000,
            'total_dibayar_penumpang' => 404000,
            'payment_number' => str_repeat('0', 400),
        ]);

        $payment->refresh();

        $this->assertSame(400000, $payment->nominal_transaksi);
        $this->assertSame(4000, $payment->biaya_admin_provider);
        $this->assertSame(404000, $payment->total_dibayar_penumpang);
        $this->assertSame(400, strlen($payment->payment_number));
        $this->assertIsString($payment->jumlah_bayar);
    }

    public function test_schedule_seat_cannot_be_used_by_two_booking_details(): void
    {
        $booking = $this->makeBookingWithoutPayment();
        $seat = KursiJadwal::query()
            ->doesntHave('detailPemesanan')
            ->firstOrFail();

        DetailPemesanan::query()->create([
            'pemesanan_id' => $booking->id,
            'kursi_jadwal_id' => $seat->id,
            'nomor_kursi' => $seat->nomor_kursi,
            'nama_penumpang' => 'Penumpang Pertama',
            'no_hp_penumpang' => '081234567890',
            'status_kehadiran' => 'menunggu',
        ]);

        $otherBooking = $this->makeBookingWithoutPayment('TRAVS-ONE-TO-ONE-2');
        $this->expectException(QueryException::class);

        DetailPemesanan::query()->create([
            'pemesanan_id' => $otherBooking->id,
            'kursi_jadwal_id' => $seat->id,
            'nomor_kursi' => $seat->nomor_kursi,
            'nama_penumpang' => 'Penumpang Kedua',
            'no_hp_penumpang' => '081234567891',
            'status_kehadiran' => 'menunggu',
        ]);
    }

    public function test_booking_detail_cannot_have_two_electronic_tickets(): void
    {
        $booking = $this->makeBookingWithoutPayment();
        $seat = KursiJadwal::query()
            ->doesntHave('detailPemesanan')
            ->firstOrFail();
        $detail = DetailPemesanan::query()->create([
            'pemesanan_id' => $booking->id,
            'kursi_jadwal_id' => $seat->id,
            'nomor_kursi' => $seat->nomor_kursi,
            'nama_penumpang' => 'Penumpang Tiket',
            'no_hp_penumpang' => '081234567892',
            'status_kehadiran' => 'menunggu',
        ]);

        TiketElektronik::query()->create([
            'detail_pemesanan_id' => $detail->id,
            'kode_qr' => 'QR-ONE-TICKET-1',
            'token_unik_qr' => 'TOKEN-ONE-TICKET-1',
            'sudah_dipindai' => false,
            'diterbitkan_pada' => now(),
        ]);

        $this->expectException(QueryException::class);

        TiketElektronik::query()->create([
            'detail_pemesanan_id' => $detail->id,
            'kode_qr' => 'QR-ONE-TICKET-2',
            'token_unik_qr' => 'TOKEN-ONE-TICKET-2',
            'sudah_dipindai' => false,
            'diterbitkan_pada' => now(),
        ]);
    }

    public function test_same_seat_number_cannot_exist_twice_on_same_schedule(): void
    {
        $seat = KursiJadwal::query()->firstOrFail();

        $this->expectException(QueryException::class);

        KursiJadwal::query()->create([
            'jadwal_id' => $seat->jadwal_id,
            'nomor_kursi' => $seat->nomor_kursi,
            'status_kursi' => 'tersedia',
            'updated_at' => now(),
        ]);
    }

    public function test_same_seat_number_can_exist_on_different_schedules(): void
    {
        $schedules = Jadwal::query()->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $schedules);
        $seatNumber = 99;

        foreach ($schedules as $schedule) {
            KursiJadwal::query()->create([
                'jadwal_id' => $schedule->id,
                'nomor_kursi' => $seatNumber,
                'status_kursi' => 'tersedia',
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(2, KursiJadwal::query()->where('nomor_kursi', $seatNumber)->count());
    }

    private function makeBookingWithoutPayment(string $bookingCode = 'TRAVS-ONE-TO-ONE'): Pemesanan
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        $schedule = Jadwal::query()->firstOrFail();

        return Pemesanan::query()->create([
            'pengguna_id' => $user->id,
            'jadwal_id' => $schedule->id,
            'kode_booking' => $bookingCode,
            'jumlah_kursi' => 1,
            'alamat_jemput' => 'Pontianak',
            'subtotal_tiket' => 200000,
            'total_harga' => 200000,
            'status_pemesanan' => 'menunggu_pembayaran',
        ]);
    }
}
