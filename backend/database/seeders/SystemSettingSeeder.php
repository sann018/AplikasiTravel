<?php

namespace Database\Seeders;

use App\Models\PengaturanSistem;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['kunci' => 'app_name', 'nilai' => 'TravS', 'tipe_data' => 'string', 'grup' => 'umum', 'deskripsi' => 'Nama aplikasi yang tampil di seluruh platform.'],
            ['kunci' => 'app_logo', 'nilai' => 'travs-logo.svg', 'tipe_data' => 'string', 'grup' => 'umum', 'deskripsi' => 'Path atau nama file logo aplikasi.'],
            ['kunci' => 'admin_contact', 'nilai' => '08123456789', 'tipe_data' => 'string', 'grup' => 'umum', 'deskripsi' => 'Kontak utama admin operasional.'],
            ['kunci' => 'system_email', 'nilai' => 'admin@travs.com', 'tipe_data' => 'string', 'grup' => 'umum', 'deskripsi' => 'Email sistem untuk komunikasi resmi.'],
            ['kunci' => 'service_whatsapp', 'nilai' => '08123456789', 'tipe_data' => 'string', 'grup' => 'umum', 'deskripsi' => 'WhatsApp layanan pelanggan.'],
            ['kunci' => 'seat_lock_form_minutes', 'nilai' => '30', 'tipe_data' => 'integer', 'grup' => 'pemesanan', 'deskripsi' => 'Durasi penguncian kursi saat form booking diisi.'],
            ['kunci' => 'seat_lock_payment_minutes', 'nilai' => '5', 'tipe_data' => 'integer', 'grup' => 'pemesanan', 'deskripsi' => 'Durasi penguncian kursi saat menunggu pembayaran.'],
            ['kunci' => 'max_seat_per_booking', 'nilai' => '4', 'tipe_data' => 'integer', 'grup' => 'pemesanan', 'deskripsi' => 'Jumlah maksimal kursi per transaksi.'],
            ['kunci' => 'auto_cancel_booking', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'pemesanan', 'deskripsi' => 'Otomatis membatalkan booking jika pembayaran melewati batas waktu.'],
            ['kunci' => 'payment_transfer_enabled', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'pembayaran', 'deskripsi' => 'Status metode transfer bank.'],
            ['kunci' => 'payment_qris_enabled', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'pembayaran', 'deskripsi' => 'Status metode pembayaran QRIS.'],
            ['kunci' => 'payment_cash_enabled', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'pembayaran', 'deskripsi' => 'Status metode bayar tunai.'],
            ['kunci' => 'admin_fee', 'nilai' => '5000', 'tipe_data' => 'decimal', 'grup' => 'pembayaran', 'deskripsi' => 'Biaya admin tetap per transaksi.'],
            ['kunci' => 'bank_name', 'nilai' => 'BRI', 'tipe_data' => 'string', 'grup' => 'pembayaran', 'deskripsi' => 'Nama bank rekening penerima.'],
            ['kunci' => 'bank_account_number', 'nilai' => '1234567890', 'tipe_data' => 'string', 'grup' => 'pembayaran', 'deskripsi' => 'Nomor rekening penerima.'],
            ['kunci' => 'bank_account_name', 'nilai' => 'Rusly Travel', 'tipe_data' => 'string', 'grup' => 'pembayaran', 'deskripsi' => 'Nama pemilik rekening penerima.'],
            ['kunci' => 'payment_cash_note', 'nilai' => 'Silakan bayar kepada pengemudi saat perjalanan', 'tipe_data' => 'string', 'grup' => 'pembayaran', 'deskripsi' => 'Catatan pembayaran tunai.'],
            ['kunci' => 'baggage_weight_enabled', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'bagasi', 'deskripsi' => 'Aktifkan hitung bagasi berbasis berat.'],
            ['kunci' => 'baggage_free_weight_kg', 'nilai' => '10', 'tipe_data' => 'integer', 'grup' => 'bagasi', 'deskripsi' => 'Batas gratis berat bagasi per penumpang.'],
            ['kunci' => 'baggage_max_weight_per_person_kg', 'nilai' => '20', 'tipe_data' => 'integer', 'grup' => 'bagasi', 'deskripsi' => 'Batas maksimal berat bagasi per penumpang.'],
            ['kunci' => 'baggage_extra_fee_per_kg', 'nilai' => '10000', 'tipe_data' => 'decimal', 'grup' => 'bagasi', 'deskripsi' => 'Tarif tambahan per kilogram.'],
            ['kunci' => 'baggage_size_enabled', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'bagasi', 'deskripsi' => 'Aktifkan hitung bagasi berbasis ukuran.'],
            ['kunci' => 'baggage_free_size_cm', 'nilai' => '160', 'tipe_data' => 'integer', 'grup' => 'bagasi', 'deskripsi' => 'Batas gratis total panjang dan lebar bagasi.'],
            ['kunci' => 'baggage_max_size_per_person_cm', 'nilai' => '160', 'tipe_data' => 'integer', 'grup' => 'bagasi', 'deskripsi' => 'Batas maksimal total panjang dan lebar per penumpang.'],
            ['kunci' => 'baggage_extra_fee_per_cm', 'nilai' => '500', 'tipe_data' => 'decimal', 'grup' => 'bagasi', 'deskripsi' => 'Tarif tambahan per centimeter.'],
            ['kunci' => 'baggage_item_enabled', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'bagasi', 'deskripsi' => 'Aktifkan hitung bagasi berbasis jumlah item.'],
            ['kunci' => 'baggage_free_item_count', 'nilai' => '2', 'tipe_data' => 'integer', 'grup' => 'bagasi', 'deskripsi' => 'Batas gratis jumlah item bagasi.'],
            ['kunci' => 'baggage_max_item_per_person', 'nilai' => '10', 'tipe_data' => 'integer', 'grup' => 'bagasi', 'deskripsi' => 'Batas maksimal item bagasi per penumpang.'],
            ['kunci' => 'baggage_extra_fee_per_item', 'nilai' => '3000', 'tipe_data' => 'decimal', 'grup' => 'bagasi', 'deskripsi' => 'Tarif tambahan per item bagasi.'],
            ['kunci' => 'baggage_photo_required', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'bagasi', 'deskripsi' => 'Menentukan apakah foto bagasi wajib diunggah.'],
            ['kunci' => 'baggage_max_total_weight_per_vehicle', 'nilai' => '50', 'tipe_data' => 'integer', 'grup' => 'bagasi', 'deskripsi' => 'Batas total berat bagasi dalam satu kendaraan.'],
            ['kunci' => 'notif_payment_success', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'notifikasi', 'deskripsi' => 'Status notifikasi pembayaran berhasil.'],
            ['kunci' => 'notif_departure_reminder', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'notifikasi', 'deskripsi' => 'Status notifikasi pengingat keberangkatan.'],
            ['kunci' => 'notif_schedule_changed', 'nilai' => 'true', 'tipe_data' => 'boolean', 'grup' => 'notifikasi', 'deskripsi' => 'Status notifikasi perubahan jadwal.'],
            ['kunci' => 'notif_payment_success_template', 'nilai' => 'Pembayaran Anda untuk rute {rute} telah berhasil dikonfirmasi.', 'tipe_data' => 'string', 'grup' => 'notifikasi', 'deskripsi' => 'Template notifikasi pembayaran berhasil.'],
            ['kunci' => 'notif_departure_reminder_template', 'nilai' => 'Pengingat: perjalanan {rute} akan berangkat pada {tanggal} pukul {jam}.', 'tipe_data' => 'string', 'grup' => 'notifikasi', 'deskripsi' => 'Template pengingat keberangkatan.'],
            ['kunci' => 'notif_schedule_changed_template', 'nilai' => 'Jadwal perjalanan Anda untuk rute {rute} telah berubah ke {tanggal_baru} pukul {jam_baru}. Silakan cek detail pemesanan.', 'tipe_data' => 'string', 'grup' => 'notifikasi', 'deskripsi' => 'Template perubahan jadwal.'],
        ];

        foreach ($settings as $setting) {
            PengaturanSistem::query()->updateOrCreate(
                ['kunci' => $setting['kunci']],
                [
                    ...$setting,
                    'updated_at' => now(),
                ],
            );
        }
    }
}
