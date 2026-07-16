<?php

namespace Database\Seeders;

use App\Models\BiayaOperasionalPerjalanan;
use App\Models\DetailPemesanan;
use App\Models\ItemBagasi;
use App\Models\Jadwal;
use App\Models\Kendaraan;
use App\Models\KursiJadwal;
use App\Models\Notifikasi;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\Pengemudi;
use App\Models\Rute;
use App\Models\TiketElektronik;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TravSDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@travs.com'],
            [
                'nama' => 'Admin Rusly Travel',
                'no_hp' => '081234567890',
                'peran' => 'admin',
                'status_aktif' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('admin12345'),
            ],
        );

        $penumpang = User::query()->updateOrCreate(
            ['email' => 'penumpang@travs.com'],
            [
                'nama' => 'Ihsan Penumpang',
                'no_hp' => '081234567891',
                'peran' => 'penumpang',
                'status_aktif' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('penumpang123'),
            ],
        );

        $driverUser = User::query()->updateOrCreate(
            ['email' => 'pengemudi@travs.com'],
            [
                'nama' => 'Rusli Driver',
                'no_hp' => '081234567892',
                'peran' => 'pengemudi',
                'status_aktif' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('driver12345'),
            ],
        );

        $driverUser2 = User::query()->updateOrCreate(
            ['email' => 'pengemudi2@travs.com'],
            [
                'nama' => 'Bima Driver',
                'no_hp' => '081234567894',
                'peran' => 'pengemudi',
                'status_aktif' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('driver12345'),
            ],
        );

        $penumpang2 = User::query()->updateOrCreate(
            ['email' => 'fitri@travs.com'],
            [
                'nama' => 'Fitri Penumpang',
                'no_hp' => '081234567895',
                'peran' => 'penumpang',
                'status_aktif' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('penumpang123'),
            ],
        );

        $driverUser3 = User::query()->updateOrCreate(
            ['email' => 'pengemudi3@travs.com'],
            [
                'nama' => 'Rizal Driver',
                'no_hp' => '081234567896',
                'peran' => 'pengemudi',
                'status_aktif' => false,
                'email_verified_at' => now(),
                'password' => Hash::make('driver12345'),
            ],
        );

        $pengemudi = Pengemudi::query()->updateOrCreate(
            ['pengguna_id' => $driverUser->id],
            [
                'nomor_sim' => '6171010101010001',
                'status' => 'aktif',
                'status_verifikasi' => 'terverifikasi',
                'catatan_verifikasi' => 'Dokumen lengkap dan siap dijadwalkan.',
                'verified_at' => now()->subDays(5),
                'verified_by' => $admin->id,
            ],
        );

        $pengemudi2 = Pengemudi::query()->updateOrCreate(
            ['pengguna_id' => $driverUser2->id],
            [
                'nomor_sim' => '6171010101010002',
                'status' => 'aktif',
                'status_verifikasi' => 'terverifikasi',
                'catatan_verifikasi' => 'SIM dan kontak sudah tervalidasi.',
                'verified_at' => now()->subDays(2),
                'verified_by' => $admin->id,
            ],
        );

        $pengemudi3 = Pengemudi::query()->updateOrCreate(
            ['pengguna_id' => $driverUser3->id],
            [
                'nomor_sim' => '6171010101010003',
                'status' => 'nonaktif',
                'status_verifikasi' => 'menunggu',
                'catatan_verifikasi' => 'Menunggu unggahan foto SIM yang lebih jelas.',
                'verified_at' => null,
                'verified_by' => null,
            ],
        );

        $rutePontianakSintang = Rute::query()->updateOrCreate(
            ['kota_asal' => 'Pontianak', 'kota_tujuan' => 'Sintang'],
            [
                'deskripsi_jalur' => 'Pontianak - Sanggau - Sintang',
                'jarak_km' => 395,
                'estimasi_durasi_menit' => 480,
                'harga_tiket' => 225000,
                'status_aktif' => true,
            ],
        );

        $rutePontianakSanggau = Rute::query()->updateOrCreate(
            ['kota_asal' => 'Pontianak', 'kota_tujuan' => 'Sanggau'],
            [
                'deskripsi_jalur' => 'Pontianak - Tayan - Sanggau',
                'jarak_km' => 267,
                'estimasi_durasi_menit' => 330,
                'harga_tiket' => 175000,
                'status_aktif' => true,
            ],
        );

        $rutePontianakKetapang = Rute::query()->updateOrCreate(
            ['kota_asal' => 'Pontianak', 'kota_tujuan' => 'Ketapang'],
            [
                'deskripsi_jalur' => 'Pontianak - Nanga Tayap - Ketapang',
                'jarak_km' => 380,
                'estimasi_durasi_menit' => 510,
                'harga_tiket' => 240000,
                'status_aktif' => true,
            ],
        );

        $kendaraan1 = Kendaraan::query()->updateOrCreate(
            ['nomor_plat' => 'KB 1234 TS'],
            [
                'merk_kendaraan' => 'Toyota Hiace Premio',
                'warna' => 'Putih',
                'tahun_kendaraan' => 2023,
                'tipe_kendaraan' => 'Travel Executive',
                'kapasitas_kursi' => 6,
                'fasilitas' => 'AC, charger USB, reclining seat',
                'status_kendaraan' => 'aktif',
            ],
        );

        $kendaraan2 = Kendaraan::query()->updateOrCreate(
            ['nomor_plat' => 'KB 5678 TS'],
            [
                'merk_kendaraan' => 'Daihatsu Luxio',
                'warna' => 'Biru Malam',
                'tahun_kendaraan' => 2022,
                'tipe_kendaraan' => 'Travel Regular',
                'kapasitas_kursi' => 6,
                'fasilitas' => 'AC, bagasi luas',
                'status_kendaraan' => 'aktif',
            ],
        );

        $kendaraan3 = Kendaraan::query()->updateOrCreate(
            ['nomor_plat' => 'KB 9012 TS'],
            [
                'merk_kendaraan' => 'Isuzu ELF NLR',
                'warna' => 'Silver',
                'tahun_kendaraan' => 2021,
                'tipe_kendaraan' => 'Travel Shuttle',
                'kapasitas_kursi' => 8,
                'fasilitas' => 'AC, headrest, bagasi atas',
                'status_kendaraan' => 'perawatan',
            ],
        );

        $jadwal1 = Jadwal::query()->updateOrCreate(
            [
                'rute_id' => $rutePontianakSintang->id,
                'kendaraan_id' => $kendaraan1->id,
                'pengemudi_id' => $pengemudi->id,
                'waktu_berangkat' => now()->addDay()->setTime(7, 30),
            ],
            [
                'estimasi_tiba' => now()->addDay()->setTime(15, 30),
                'status' => 'dibuka',
            ],
        );

        $jadwalHariIni = Jadwal::query()->updateOrCreate(
            [
                'rute_id' => $rutePontianakSanggau->id,
                'kendaraan_id' => $kendaraan2->id,
                'pengemudi_id' => $pengemudi2->id,
                'waktu_berangkat' => now()->setTime(9, 30),
            ],
            [
                'estimasi_tiba' => now()->setTime(14, 45),
                'status' => 'dibuka',
            ],
        );

        $jadwal2 = Jadwal::query()->updateOrCreate(
            [
                'rute_id' => $rutePontianakSanggau->id,
                'kendaraan_id' => $kendaraan2->id,
                'pengemudi_id' => $pengemudi2->id,
                'waktu_berangkat' => now()->addDays(2)->setTime(8, 0),
            ],
            [
                'estimasi_tiba' => now()->addDays(2)->setTime(13, 30),
                'status' => 'dibuka',
            ],
        );

        $jadwalSelesai = Jadwal::query()->updateOrCreate(
            [
                'rute_id' => $rutePontianakKetapang->id,
                'kendaraan_id' => $kendaraan1->id,
                'pengemudi_id' => $pengemudi->id,
                'waktu_berangkat' => now()->subDay()->setTime(6, 0),
            ],
            [
                'estimasi_tiba' => now()->subDay()->setTime(15, 0),
                'status' => 'selesai',
            ],
        );

        $this->seedKursi($jadwal1, $kendaraan1->kapasitas_kursi);
        $this->seedKursi($jadwalHariIni, $kendaraan2->kapasitas_kursi);
        $this->seedKursi($jadwal2, $kendaraan2->kapasitas_kursi);
        $this->seedKursi($jadwalSelesai, $kendaraan1->kapasitas_kursi);

        $seat1 = KursiJadwal::query()->where('jadwal_id', $jadwal1->id)->where('nomor_kursi', 1)->firstOrFail();
        $seat2 = KursiJadwal::query()->where('jadwal_id', $jadwal1->id)->where('nomor_kursi', 2)->firstOrFail();
        $seatPending = KursiJadwal::query()->where('jadwal_id', $jadwal2->id)->where('nomor_kursi', 1)->firstOrFail();
        $seatFinished = KursiJadwal::query()->where('jadwal_id', $jadwalSelesai->id)->where('nomor_kursi', 1)->firstOrFail();

        $booking = Pemesanan::query()->updateOrCreate(
            ['kode_booking' => 'TRAVS-DEMO-001'],
            [
                'pengguna_id' => $penumpang->id,
                'jadwal_id' => $jadwal1->id,
                'jumlah_kursi' => 2,
                'batas_kunci_kursi' => now()->addMinutes(30),
                'batas_pembayaran' => now()->addMinutes(5),
                'lintang_jemput' => -0.0225,
                'bujur_jemput' => 109.3425,
                'alamat_jemput' => 'Jalan Ampera Raya, Pontianak',
                'subtotal_tiket' => 450000,
                'biaya_bagasi' => 10000,
                'diskon' => 0,
                'total_harga' => 465000,
                'status_pemesanan' => 'terbayar',
            ],
        );

        $detail1 = DetailPemesanan::query()->updateOrCreate(
            ['kursi_jadwal_id' => $seat1->id],
            [
                'pemesanan_id' => $booking->id,
                'nomor_kursi' => 1,
                'nama_penumpang' => 'Ihsan Al Husna',
                'no_hp_penumpang' => '081234567891',
                'status_kehadiran' => 'menunggu',
            ],
        );

        $detail2 = DetailPemesanan::query()->updateOrCreate(
            ['kursi_jadwal_id' => $seat2->id],
            [
                'pemesanan_id' => $booking->id,
                'nomor_kursi' => 2,
                'nama_penumpang' => 'Teman TravS',
                'no_hp_penumpang' => '081234567893',
                'status_kehadiran' => 'menunggu',
            ],
        );

        Pembayaran::query()->updateOrCreate(
            ['pemesanan_id' => $booking->id],
            [
                'order_id_gateway' => 'PKS-DEMO-001',
                'gateway_reference' => 'TRX-TRAVS-DEMO-001',
                'metode_bayar' => 'qris',
                'paid_channel' => 'PakKasir Demo',
                'jumlah_bayar' => 465000,
                'status_bayar' => 'berhasil',
                'gateway_response' => 'Pembayaran demo berhasil.',
                'dibayar_pada' => now(),
            ],
        );

        TiketElektronik::query()->updateOrCreate(
            ['detail_pemesanan_id' => $detail1->id],
            [
                'kode_qr' => 'TRAVS-QR-'.$detail1->id,
                'token_unik_qr' => Str::uuid()->toString(),
                'diterbitkan_pada' => now(),
            ],
        );

        TiketElektronik::query()->updateOrCreate(
            ['detail_pemesanan_id' => $detail2->id],
            [
                'kode_qr' => 'TRAVS-QR-'.$detail2->id,
                'token_unik_qr' => Str::uuid()->toString(),
                'diterbitkan_pada' => now(),
            ],
        );

        $seat1->update(['status_kursi' => 'dipesan', 'updated_at' => now()]);
        $seat2->update(['status_kursi' => 'dipesan', 'updated_at' => now()]);

        $pendingBooking = Pemesanan::query()->updateOrCreate(
            ['kode_booking' => 'TRAVS-DEMO-002'],
            [
                'pengguna_id' => $penumpang->id,
                'jadwal_id' => $jadwal2->id,
                'jumlah_kursi' => 1,
                'batas_kunci_kursi' => now()->addMinutes(15),
                'batas_pembayaran' => now()->addMinutes(10),
                'lintang_jemput' => -0.0260,
                'bujur_jemput' => 109.3271,
                'alamat_jemput' => 'Terminal Batu Layang, Pontianak',
                'subtotal_tiket' => 175000,
                'biaya_bagasi' => 15000,
                'diskon' => 0,
                'total_harga' => 195000,
                'status_pemesanan' => 'menunggu_pembayaran',
            ],
        );

        DetailPemesanan::query()->updateOrCreate(
            ['kursi_jadwal_id' => $seatPending->id],
            [
                'pemesanan_id' => $pendingBooking->id,
                'nomor_kursi' => 1,
                'nama_penumpang' => 'Ihsan Penumpang',
                'no_hp_penumpang' => '081234567891',
                'status_kehadiran' => 'menunggu',
            ],
        );

        ItemBagasi::query()->updateOrCreate(
            ['pemesanan_id' => $pendingBooking->id, 'deskripsi_item' => 'Koper kabin hitam'],
            [
                'berat_kg' => 12,
                'ukuran_cm' => 110,
                'jumlah_item' => 1,
                'foto_url' => null,
                'biaya_item' => 15000,
            ],
        );

        Pembayaran::query()->updateOrCreate(
            ['pemesanan_id' => $pendingBooking->id],
            [
                'order_id_gateway' => 'DRAFT-DEMO-002',
                'gateway_reference' => 'TRX-TRAVS-DEMO-002',
                'metode_bayar' => 'transfer',
                'paid_channel' => 'Transfer Bank',
                'jumlah_bayar' => 195000,
                'status_bayar' => 'menunggu',
                'gateway_response' => 'Menunggu transfer dari penumpang.',
                'expired_at' => now()->addMinutes(10),
            ],
        );

        $finishedBooking = Pemesanan::query()->updateOrCreate(
            ['kode_booking' => 'TRAVS-DEMO-003'],
            [
                'pengguna_id' => $penumpang2->id,
                'jadwal_id' => $jadwalSelesai->id,
                'jumlah_kursi' => 1,
                'batas_kunci_kursi' => now()->subDays(2),
                'batas_pembayaran' => now()->subDays(2),
                'lintang_jemput' => -0.0201,
                'bujur_jemput' => 109.3389,
                'alamat_jemput' => 'Jalan Ahmad Yani, Pontianak',
                'subtotal_tiket' => 240000,
                'biaya_bagasi' => 0,
                'diskon' => 10000,
                'total_harga' => 235000,
                'status_pemesanan' => 'selesai',
            ],
        );

        $finishedDetail = DetailPemesanan::query()->updateOrCreate(
            ['kursi_jadwal_id' => $seatFinished->id],
            [
                'pemesanan_id' => $finishedBooking->id,
                'nomor_kursi' => 1,
                'nama_penumpang' => 'Fitri Penumpang',
                'no_hp_penumpang' => '081234567895',
                'status_kehadiran' => 'hadir',
            ],
        );

        Pembayaran::query()->updateOrCreate(
            ['pemesanan_id' => $finishedBooking->id],
            [
                'order_id_gateway' => 'CASH-DEMO-003',
                'gateway_reference' => 'TRX-TRAVS-DEMO-003',
                'metode_bayar' => 'cash',
                'paid_channel' => 'Bayar Tunai',
                'jumlah_bayar' => 235000,
                'status_bayar' => 'berhasil',
                'gateway_response' => 'Pembayaran tunai diverifikasi admin.',
                'dibayar_pada' => now()->subDay()->setTime(5, 45),
                'webhook_verified_at' => now()->subDay()->setTime(5, 45),
            ],
        );

        TiketElektronik::query()->updateOrCreate(
            ['detail_pemesanan_id' => $finishedDetail->id],
            [
                'kode_qr' => 'TRAVS-QR-'.$finishedDetail->id,
                'token_unik_qr' => Str::uuid()->toString(),
                'sudah_dipindai' => true,
                'dipindai_pada' => now()->subDay()->setTime(5, 55),
                'diterbitkan_pada' => now()->subDay()->setTime(5, 40),
            ],
        );

        $seatPending->update([
            'status_kursi' => 'terkunci',
            'dikunci_oleh' => $penumpang->id,
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes(10),
            'updated_at' => now(),
        ]);

        $seatFinished->update([
            'status_kursi' => 'dipesan',
            'updated_at' => now(),
        ]);

        BiayaOperasionalPerjalanan::query()->updateOrCreate(
            ['jadwal_id' => $jadwalHariIni->id, 'jenis_biaya' => 'BBM Pagi'],
            [
                'nominal' => 250000,
                'keterangan' => 'Pengisian solar sebelum keberangkatan jadwal pagi.',
                'tanggal_biaya' => now()->setTime(6, 45),
                'created_by' => $admin->id,
            ],
        );

        BiayaOperasionalPerjalanan::query()->updateOrCreate(
            ['jadwal_id' => $jadwalHariIni->id, 'jenis_biaya' => 'Tol dan Parkir'],
            [
                'nominal' => 85000,
                'keterangan' => 'Tol akses keluar kota dan parkir penjemputan.',
                'tanggal_biaya' => now()->setTime(8, 10),
                'created_by' => $admin->id,
            ],
        );

        BiayaOperasionalPerjalanan::query()->updateOrCreate(
            ['jadwal_id' => $jadwalSelesai->id, 'jenis_biaya' => 'Makan Pengemudi'],
            [
                'nominal' => 45000,
                'keterangan' => 'Konsumsi driver saat perjalanan jarak jauh.',
                'tanggal_biaya' => now()->subDay()->setTime(12, 30),
                'created_by' => $admin->id,
            ],
        );

        Notifikasi::query()->updateOrCreate(
            ['pengguna_id' => $penumpang->id, 'judul' => 'Pembayaran berhasil dikonfirmasi'],
            [
                'pesan' => 'Pembayaran untuk pemesanan TRAVS-DEMO-001 sudah berhasil dan tiket elektronik telah diterbitkan.',
                'jenis' => 'pembayaran',
                'referensi_id' => $booking->id,
                'sudah_dibaca' => false,
            ],
        );

        Notifikasi::query()->updateOrCreate(
            ['pengguna_id' => $penumpang->id, 'judul' => 'Pengingat keberangkatan Pontianak - Sanggau'],
            [
                'pesan' => 'Perjalanan Anda dijadwalkan berangkat dua hari lagi. Mohon selesaikan pembayaran sebelum batas waktu berakhir.',
                'jenis' => 'perjalanan',
                'referensi_id' => $pendingBooking->id,
                'sudah_dibaca' => false,
            ],
        );

        Notifikasi::query()->updateOrCreate(
            ['pengguna_id' => $driverUser2->id, 'judul' => 'Jadwal hari ini siap berangkat'],
            [
                'pesan' => 'Anda memiliki jadwal keberangkatan hari ini untuk rute Pontianak - Sanggau pukul 09:30.',
                'jenis' => 'perjalanan',
                'referensi_id' => $jadwalHariIni->id,
                'sudah_dibaca' => true,
            ],
        );

        Notifikasi::query()->updateOrCreate(
            ['pengguna_id' => $admin->id, 'judul' => 'Rekap biaya operasional diperbarui'],
            [
                'pesan' => 'Tiga item biaya operasional baru telah dicatat dan siap ditinjau pada modul rekap biaya.',
                'jenis' => 'sistem',
                'referensi_id' => $jadwalHariIni->id,
                'sudah_dibaca' => false,
            ],
        );

        $admin->forceFill(['remember_token' => Str::random(10)])->save();
        $penumpang->forceFill(['remember_token' => Str::random(10)])->save();
        $penumpang2->forceFill(['remember_token' => Str::random(10)])->save();
        $driverUser->forceFill(['remember_token' => Str::random(10)])->save();
        $driverUser2->forceFill(['remember_token' => Str::random(10)])->save();
        $driverUser3->forceFill(['remember_token' => Str::random(10)])->save();
    }

    private function seedKursi(Jadwal $jadwal, int $kapasitas): void
    {
        for ($nomor = 1; $nomor <= $kapasitas; $nomor++) {
            KursiJadwal::query()->updateOrCreate(
                ['jadwal_id' => $jadwal->id, 'nomor_kursi' => $nomor],
                ['status_kursi' => 'tersedia', 'updated_at' => now()],
            );
        }
    }
}
