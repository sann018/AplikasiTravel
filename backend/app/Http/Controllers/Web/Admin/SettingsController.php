<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\LogoImageService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly LogoImageService $logoImageService,
    ) {}

    public function index(Request $request): View
    {
        $groupLabels = [
            'general' => 'Umum',
            'booking' => 'Pemesanan',
            'payment' => 'Pembayaran',
            'baggage' => 'Bagasi',
        ];

        $activeTab = $request->string('tab')->toString();
        if (! array_key_exists($activeTab, $groupLabels)) {
            $activeTab = 'general';
        }

        return view('admin.pengaturan.index', [
            'settings' => $this->settingService->definitionsByGroup(),
            'groupLabels' => $groupLabels,
            'activeTab' => $activeTab,
            'adminUser' => $request->user(),
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        // Validasi backend tetap wajib karena data dari browser dapat diubah di luar form.
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'app_logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'admin_contact' => ['required', 'regex:/^(?:\+62|62|0)8[1-9][0-9]{7,10}$/'],
            'service_whatsapp' => ['required', 'regex:/^(?:\+62|62|0)8[1-9][0-9]{7,10}$/'],
            'system_email' => ['required', 'email:rfc', 'max:100'],
        ], $this->generalMessages());

        $oldLogoPath = (string) $this->settingService->get('app_logo', '');
        $newLogoPath = null;

        if ($request->hasFile('app_logo')) {
            try {
                // Logo diperkecil agar cepat dimuat di web dan HP tanpa mengubah perbandingan gambar.
                $newLogoPath = $this->logoImageService->store($request->file('app_logo'));
            } catch (Throwable $exception) {
                report($exception);
            }

            if (! is_string($newLogoPath) || $newLogoPath === '') {
                return back()->withInput()->withErrors([
                    'app_logo' => 'Logo belum berhasil diunggah. Silakan coba lagi.',
                ]);
            }

            $validated['app_logo'] = $newLogoPath;
        } else {
            unset($validated['app_logo']);
        }

        try {
            DB::transaction(function () use ($validated, $request): void {
                $this->settingService->updateMany($validated, $request->user()?->id);
            });
        } catch (Throwable $exception) {
            if ($newLogoPath) {
                Storage::disk('public')->delete($newLogoPath);
            }

            report($exception);

            return back()->withInput()->withErrors([
                'app_logo' => 'Pengaturan umum belum berhasil disimpan. Silakan coba lagi.',
            ]);
        }

        // File lama dihapus setelah database sukses agar logo tidak hilang saat proses penyimpanan gagal.
        if ($newLogoPath && str_starts_with($oldLogoPath, 'app-logos/') && $oldLogoPath !== $newLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return redirect()
            ->route('admin.settings.index', ['tab' => 'general'])
            ->with('status', 'Pengaturan umum berhasil disimpan.');
    }

    public function updateBooking(Request $request): RedirectResponse
    {
        $request->merge(['auto_cancel_booking' => $request->boolean('auto_cancel_booking')]);

        $validated = $request->validate([
            'auto_cancel_booking' => ['required', 'boolean'],
            'max_seat_per_booking' => ['required', 'integer', 'min:1'],
            'seat_lock_form_minutes' => ['required', 'integer', 'min:1'],
            'seat_lock_payment_minutes' => ['required', 'integer', 'min:1'],
        ], $this->numericMessages());

        return $this->saveGroup('booking', $validated, $request, 'Pengaturan pemesanan berhasil disimpan.');
    }

    public function updatePayment(Request $request): RedirectResponse
    {
        $this->mergeBooleans($request, [
            'payment_transfer_enabled',
            'payment_qris_enabled',
            'payment_cash_enabled',
        ]);

        $validated = $request->validate([
            'payment_transfer_enabled' => ['required', 'boolean'],
            'payment_qris_enabled' => ['required', 'boolean'],
            'payment_cash_enabled' => ['required', 'boolean'],
            'bank_account_number' => ['required_if:payment_transfer_enabled,true', 'nullable', 'digits_between:8,20'],
            'bank_account_name' => ['required_if:payment_transfer_enabled,true', 'nullable', 'string', 'max:100'],
            'bank_name' => ['required_if:payment_transfer_enabled,true', 'nullable', 'string', 'max:100'],
            'payment_cash_note' => ['required_if:payment_cash_enabled,true', 'nullable', 'string', 'max:1000'],
        ], [
            ...$this->numericMessages(),
            'bank_account_number.required_if' => 'Nomor rekening wajib diisi saat transfer bank aktif.',
            'bank_account_number.digits_between' => 'Nomor rekening harus berisi 8 sampai 20 digit.',
            'bank_account_name.required_if' => 'Nama pemilik rekening wajib diisi saat transfer bank aktif.',
            'bank_name.required_if' => 'Nama bank wajib diisi saat transfer bank aktif.',
            'payment_cash_note.required_if' => 'Petunjuk pembayaran tunai wajib diisi saat pembayaran tunai aktif.',
        ]);

        return $this->saveGroup('payment', $validated, $request, 'Pengaturan pembayaran berhasil disimpan.');
    }

    public function updateBaggage(Request $request): RedirectResponse
    {
        $this->mergeBooleans($request, [
            'baggage_weight_enabled',
            'baggage_size_enabled',
            'baggage_item_enabled',
            'baggage_photo_required',
        ]);

        $validated = $request->validate([
            'baggage_weight_enabled' => ['required', 'boolean'],
            'baggage_size_enabled' => ['required', 'boolean'],
            'baggage_item_enabled' => ['required', 'boolean'],
            'baggage_photo_required' => ['required', 'boolean'],
            'baggage_free_weight_kg' => ['required', 'numeric', 'min:0', 'lte:baggage_max_weight_per_person_kg'],
            'baggage_max_weight_per_person_kg' => ['required', 'numeric', 'min:0'],
            'baggage_extra_fee_per_kg' => ['required', 'numeric', 'min:0'],
            'baggage_free_size_cm' => ['required', 'numeric', 'min:0', 'lte:baggage_max_size_per_person_cm'],
            'baggage_max_size_per_person_cm' => ['required', 'numeric', 'min:0'],
            'baggage_extra_fee_per_cm' => ['required', 'numeric', 'min:0'],
            'baggage_free_item_count' => ['required', 'integer', 'min:0', 'lte:baggage_max_item_per_person'],
            'baggage_max_item_per_person' => ['required', 'integer', 'min:0'],
            'baggage_extra_fee_per_item' => ['required', 'numeric', 'min:0'],
            'baggage_max_total_weight_per_vehicle' => ['required', 'numeric', 'gt:0'],
        ], [
            ...$this->numericMessages(),
            'baggage_free_weight_kg.lte' => 'Batas berat gratis tidak boleh melebihi batas maksimal.',
            'baggage_free_size_cm.lte' => 'Batas ukuran gratis tidak boleh melebihi batas maksimal.',
            'baggage_free_item_count.lte' => 'Jumlah item gratis tidak boleh melebihi jumlah maksimal.',
            'baggage_max_total_weight_per_vehicle.gt' => 'Batas berat kendaraan harus lebih dari 0.',
        ]);

        return $this->saveGroup('baggage', $validated, $request, 'Pengaturan bagasi berhasil disimpan.');
    }

    public function updateNotification(Request $request): RedirectResponse
    {
        $this->mergeBooleans($request, [
            'notif_departure_reminder',
            'notif_payment_success',
            'notif_schedule_changed',
        ]);

        $validated = $request->validate([
            'notif_departure_reminder' => ['required', 'boolean'],
            'notif_departure_reminder_template' => ['required_if:notif_departure_reminder,true', 'nullable', 'string', 'max:2000'],
            'notif_payment_success' => ['required', 'boolean'],
            'notif_payment_success_template' => ['required_if:notif_payment_success,true', 'nullable', 'string', 'max:2000'],
            'notif_schedule_changed' => ['required', 'boolean'],
            'notif_schedule_changed_template' => ['required_if:notif_schedule_changed,true', 'nullable', 'string', 'max:2000'],
        ], [
            'notif_departure_reminder_template.required_if' => 'Template pengingat keberangkatan wajib diisi saat notifikasi aktif.',
            'notif_payment_success_template.required_if' => 'Template pembayaran berhasil wajib diisi saat notifikasi aktif.',
            'notif_schedule_changed_template.required_if' => 'Template perubahan jadwal wajib diisi saat notifikasi aktif.',
        ]);

        return $this->saveGroup('notification', $validated, $request, 'Pengaturan notifikasi berhasil disimpan.');
    }

    private function saveGroup(string $group, array $data, Request $request, string $message): RedirectResponse
    {
        // Transaksi mencegah sebagian nilai tersimpan jika salah satu proses gagal.
        DB::transaction(function () use ($data, $request): void {
            $this->settingService->updateMany($data, $request->user()?->id);
        });

        return redirect()->route('admin.settings.index', ['tab' => $group])->with('status', $message);
    }

    private function mergeBooleans(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            $request->merge([$key => $request->boolean($key)]);
        }
    }

    private function generalMessages(): array
    {
        return [
            'app_name.required' => 'Nama aplikasi wajib diisi.',
            'app_name.max' => 'Nama aplikasi maksimal 100 karakter.',
            'app_logo.image' => 'Logo harus berupa file gambar.',
            'app_logo.mimes' => 'Logo hanya menerima format JPG, JPEG, PNG, atau WEBP.',
            'app_logo.max' => 'Ukuran logo maksimal 2 MB.',
            'admin_contact.regex' => 'Nomor kontak admin belum sesuai format nomor HP Indonesia.',
            'service_whatsapp.regex' => 'Nomor WhatsApp belum sesuai format nomor HP Indonesia.',
            'system_email.email' => 'Format email sistem belum valid.',
        ];
    }

    private function numericMessages(): array
    {
        return [
            '*.required' => 'Kolom ini wajib diisi.',
            '*.integer' => 'Nilai harus berupa angka bulat.',
            '*.numeric' => 'Nilai harus berupa angka.',
            '*.min' => 'Nilai yang dimasukkan masih di bawah batas minimum.',
        ];
    }
}
