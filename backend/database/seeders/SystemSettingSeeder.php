<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'general', 'key' => 'app_name', 'value' => 'RuslyTravel', 'type' => 'string', 'description' => 'Nama aplikasi yang tampil kepada pengguna.'],
            ['group' => 'general', 'key' => 'app_logo', 'value' => 'travs-logo.svg', 'type' => 'string', 'description' => 'Path logo aplikasi yang sedang digunakan.'],
            ['group' => 'general', 'key' => 'admin_contact', 'value' => '08123456789', 'type' => 'string', 'description' => 'Nomor kontak admin operasional.'],
            ['group' => 'general', 'key' => 'service_whatsapp', 'value' => '08123456789', 'type' => 'string', 'description' => 'Nomor WhatsApp layanan pelanggan.'],
            ['group' => 'general', 'key' => 'system_email', 'value' => 'admin@ruslytravel.com', 'type' => 'string', 'description' => 'Email resmi yang digunakan sistem.'],

            ['group' => 'booking', 'key' => 'auto_cancel_booking', 'value' => 'true', 'type' => 'boolean', 'description' => 'Membatalkan pemesanan otomatis setelah batas waktu habis.'],
            ['group' => 'booking', 'key' => 'max_seat_per_booking', 'value' => '4', 'type' => 'integer', 'description' => 'Jumlah kursi maksimal dalam satu pemesanan.'],
            ['group' => 'booking', 'key' => 'seat_lock_form_minutes', 'value' => '30', 'type' => 'integer', 'description' => 'Lama kursi dikunci saat form pemesanan diisi.'],
            ['group' => 'booking', 'key' => 'seat_lock_payment_minutes', 'value' => '5', 'type' => 'integer', 'description' => 'Lama kursi dikunci saat menunggu pembayaran.'],

            ['group' => 'payment', 'key' => 'bank_account_number', 'value' => '1234567890', 'type' => 'string', 'description' => 'Nomor rekening tujuan transfer.'],
            ['group' => 'payment', 'key' => 'bank_account_name', 'value' => 'Rusly Travel', 'type' => 'string', 'description' => 'Nama pemilik rekening tujuan.'],
            ['group' => 'payment', 'key' => 'bank_name', 'value' => 'BRI', 'type' => 'string', 'description' => 'Nama bank rekening tujuan.'],
            ['group' => 'payment', 'key' => 'payment_transfer_enabled', 'value' => 'true', 'type' => 'boolean', 'description' => 'Mengaktifkan pilihan pembayaran transfer bank.'],
            ['group' => 'payment', 'key' => 'payment_qris_enabled', 'value' => 'true', 'type' => 'boolean', 'description' => 'Mengaktifkan pilihan pembayaran QRIS.'],
            ['group' => 'payment', 'key' => 'payment_cash_enabled', 'value' => 'true', 'type' => 'boolean', 'description' => 'Mengaktifkan pilihan pembayaran tunai.'],
            ['group' => 'payment', 'key' => 'payment_cash_note', 'value' => 'Pembayaran tunai dilakukan kepada pengemudi sebelum keberangkatan.', 'type' => 'text', 'description' => 'Petunjuk pembayaran tunai untuk penumpang.'],

            ['group' => 'baggage', 'key' => 'baggage_weight_enabled', 'value' => 'true', 'type' => 'boolean', 'description' => 'Mengaktifkan perhitungan bagasi berdasarkan berat.'],
            ['group' => 'baggage', 'key' => 'baggage_size_enabled', 'value' => 'true', 'type' => 'boolean', 'description' => 'Mengaktifkan perhitungan bagasi berdasarkan ukuran.'],
            ['group' => 'baggage', 'key' => 'baggage_item_enabled', 'value' => 'true', 'type' => 'boolean', 'description' => 'Mengaktifkan perhitungan bagasi berdasarkan jumlah item.'],
            ['group' => 'baggage', 'key' => 'baggage_photo_required', 'value' => 'true', 'type' => 'boolean', 'description' => 'Mewajibkan foto barang bawaan saat memesan.'],
            ['group' => 'baggage', 'key' => 'baggage_free_weight_kg', 'value' => '10', 'type' => 'float', 'description' => 'Batas berat bagasi gratis per penumpang.'],
            ['group' => 'baggage', 'key' => 'baggage_max_weight_per_person_kg', 'value' => '20', 'type' => 'float', 'description' => 'Batas berat bagasi maksimal per penumpang.'],
            ['group' => 'baggage', 'key' => 'baggage_extra_fee_per_kg', 'value' => '3000', 'type' => 'float', 'description' => 'Biaya tambahan setiap kilogram di atas batas gratis.'],
            ['group' => 'baggage', 'key' => 'baggage_free_size_cm', 'value' => '160', 'type' => 'float', 'description' => 'Batas ukuran bagasi gratis dalam sentimeter.'],
            ['group' => 'baggage', 'key' => 'baggage_max_size_per_person_cm', 'value' => '160', 'type' => 'float', 'description' => 'Batas ukuran bagasi maksimal per penumpang.'],
            ['group' => 'baggage', 'key' => 'baggage_extra_fee_per_cm', 'value' => '500', 'type' => 'float', 'description' => 'Biaya tambahan setiap sentimeter di atas batas gratis.'],
            ['group' => 'baggage', 'key' => 'baggage_free_item_count', 'value' => '2', 'type' => 'integer', 'description' => 'Jumlah item bagasi yang tidak dikenai biaya.'],
            ['group' => 'baggage', 'key' => 'baggage_max_item_per_person', 'value' => '10', 'type' => 'integer', 'description' => 'Jumlah item bagasi maksimal per penumpang.'],
            ['group' => 'baggage', 'key' => 'baggage_extra_fee_per_item', 'value' => '2500', 'type' => 'float', 'description' => 'Biaya tambahan setiap item di atas batas gratis.'],
            // Key lama ini tetap disimpan untuk kompatibilitas data lama, tetapi tidak dipakai lagi di UI dan perhitungan versi ini.
            ['group' => 'baggage', 'key' => 'baggage_small_item_fee', 'value' => '5000', 'type' => 'float', 'description' => 'Biaya item kecil yang masuk bagasi setelah kuota gratis habis.'],
            ['group' => 'baggage', 'key' => 'baggage_large_item_fee', 'value' => '10000', 'type' => 'float', 'description' => 'Biaya item besar yang masuk bagasi setelah kuota gratis habis.'],
            ['group' => 'baggage', 'key' => 'baggage_large_vehicle_enabled', 'value' => '0', 'type' => 'boolean', 'description' => 'Aktifkan tarif khusus barang besar seperti sepeda, motor, atau beko.'],
            ['group' => 'baggage', 'key' => 'baggage_bicycle_fee', 'value' => '0', 'type' => 'float', 'description' => 'Tarif khusus sepeda yang masuk bagasi.'],
            ['group' => 'baggage', 'key' => 'baggage_motorcycle_fee', 'value' => '0', 'type' => 'float', 'description' => 'Tarif khusus sepeda motor yang ikut diangkut.'],
            ['group' => 'baggage', 'key' => 'baggage_heavy_item_fee', 'value' => '0', 'type' => 'float', 'description' => 'Tarif khusus beko atau barang berat.'],
            // Field barang besar lainnya juga disimpan agar seeding lama aman, tetapi tidak ditampilkan lagi di pengaturan.
            ['group' => 'baggage', 'key' => 'baggage_other_large_item_fee', 'value' => '0', 'type' => 'float', 'description' => 'Tarif khusus barang besar lainnya.'],
            ['group' => 'baggage', 'key' => 'baggage_max_total_weight_per_vehicle', 'value' => '50', 'type' => 'float', 'description' => 'Batas total berat bagasi dalam satu kendaraan.'],

            ['group' => 'notification', 'key' => 'notif_departure_reminder', 'value' => 'true', 'type' => 'boolean', 'description' => 'Mengaktifkan pengingat keberangkatan.'],
            ['group' => 'notification', 'key' => 'notif_departure_reminder_template', 'value' => 'Perjalanan {rute} berangkat pada {tanggal} pukul {jam}.', 'type' => 'text', 'description' => 'Template pengingat keberangkatan.'],
            ['group' => 'notification', 'key' => 'notif_payment_success', 'value' => 'true', 'type' => 'boolean', 'description' => 'Mengaktifkan pemberitahuan pembayaran berhasil.'],
            ['group' => 'notification', 'key' => 'notif_payment_success_template', 'value' => 'Pembayaran perjalanan {rute} berhasil dikonfirmasi.', 'type' => 'text', 'description' => 'Template pemberitahuan pembayaran berhasil.'],
            ['group' => 'notification', 'key' => 'notif_schedule_changed', 'value' => 'true', 'type' => 'boolean', 'description' => 'Mengaktifkan pemberitahuan perubahan jadwal.'],
            ['group' => 'notification', 'key' => 'notif_schedule_changed_template', 'value' => 'Jadwal {rute} berubah menjadi {tanggal_baru} pukul {jam_baru}.', 'type' => 'text', 'description' => 'Template pemberitahuan perubahan jadwal.'],
        ];

        foreach ($settings as $setting) {
            // firstOrCreate menjaga nilai yang sudah pernah diubah admin agar tidak tertimpa saat seeder dijalankan lagi.
            SystemSetting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }

        // Biaya layanan pembayaran sepenuhnya mengikuti Pakasir, bukan RuslyTravel.
        SystemSetting::query()->where('key', 'admin_fee')->delete();

        app(SettingService::class)->clearCache();
    }
}
