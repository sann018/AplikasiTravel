<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use App\Services\LogoImageService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'account' => 'Akun Admin',
            'general' => 'Umum',
            'booking' => 'Pemesanan',
            'payment' => 'Pembayaran',
            'banner' => 'Banner Beranda',
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
            'banners' => HomeBanner::query()->orderBy('urutan')->latest('id')->get(),
            'importantInformation' => $this->settingService->importantInformation(),
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
        ]);

        $validated = $request->validate([
            'payment_transfer_enabled' => ['required', 'boolean'],
            'payment_qris_enabled' => ['required', 'boolean'],
            'payment_cash_note' => ['nullable', 'string', 'max:1000'],
        ], [
            ...$this->numericMessages(),
            'payment_cash_note.max' => 'Catatan pembayaran tunai maksimal 1.000 karakter.',
        ]);

        // Setting tunai dan rekening lama dipertahankan tanpa ditimpa agar API dan transaksi lama tetap kompatibel.
        return $this->saveGroup('payment', $validated, $request, 'Pengaturan pembayaran berhasil disimpan.');
    }

    public function updateBaggage(Request $request): RedirectResponse
    {
        // Endpoint lama dipertahankan agar bookmark/form versi lama tidak error,
        // tetapi tarif bagasi sudah tidak digunakan pada versi sistem saat ini.
        return redirect()
            ->route('admin.settings.index', ['tab' => 'general'])
            ->with('status', 'Pengaturan tarif bagasi sudah tidak digunakan. Barang bawaan biasa dicatat tanpa biaya.');
    }

    public function storeImportantInformation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
        ], [
            'title.required' => 'Judul informasi penting wajib diisi.',
            'title.max' => 'Judul informasi penting maksimal 100 karakter.',
            'description.required' => 'Isi informasi penting wajib diisi.',
            'description.max' => 'Isi informasi penting maksimal 1.000 karakter.',
        ]);

        $items = $this->settingService->importantInformation();
        $items[] = [
            'id' => (string) Str::uuid(),
            'title' => trim($validated['title']),
            'description' => trim($validated['description']),
        ];

        $this->settingService->updateMany([
            'important_information' => json_encode($items, JSON_UNESCAPED_UNICODE),
        ], $request->user()?->id);

        return redirect()
            ->route('admin.settings.index', ['tab' => 'general'])
            ->with('status', 'Informasi penting berhasil ditambahkan.');
    }

    public function destroyImportantInformation(Request $request, string $informationId): RedirectResponse
    {
        $items = collect($this->settingService->importantInformation())
            ->reject(fn (array $item) => hash_equals($item['id'], $informationId))
            ->values()
            ->all();

        $this->settingService->updateMany([
            'important_information' => json_encode($items, JSON_UNESCAPED_UNICODE),
        ], $request->user()?->id);

        return redirect()
            ->route('admin.settings.index', ['tab' => 'general'])
            ->with('status', 'Informasi penting berhasil dihapus.');
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
