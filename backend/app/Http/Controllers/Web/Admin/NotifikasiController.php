<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Services\NotificationHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotifikasiController extends Controller
{
    public function __construct(private readonly NotificationHistoryService $notificationHistoryService) {}

    public function admin(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $adminId = $request->user()->id;

        // ID disimpan sebelum status diperbarui agar notifikasi yang baru dibuka
        // tetap terlihat sebagai kelompok baru pada render pertama halaman.
        $newNotificationIds = Notifikasi::query()
            ->where('pengguna_id', $adminId)
            ->where('sudah_dibaca', false)
            ->pluck('id')
            ->all();

        Notifikasi::query()
            ->whereIn('id', $newNotificationIds)
            ->update(['sudah_dibaca' => true]);

        $query = $this->filteredQuery($filters)
            ->where('pengguna_id', $adminId);

        return view('admin.notifikasi.index', [
            'notifikasi' => $query->latest()->paginate(15)->withQueryString(),
            'stats' => $this->stats($adminId),
            'filters' => $filters,
            'isAdminInbox' => true,
            'resetRoute' => 'admin.notifikasi.admin',
            'clearRoute' => 'admin.notifikasi.admin.history.clear',
            'pageTitle' => 'Notifikasi Admin',
            'pageDescription' => 'Pemberitahuan khusus untuk admin web.',
            'summaryDescription' => 'Ikhtisar notifikasi yang masuk ke akun admin.',
            'listDescription' => 'Notifikasi terbaru yang ditujukan ke admin web.',
            'newNotificationIds' => $newNotificationIds,
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        // Snapshot ini mempertahankan penanda baru pada render pertama, sedangkan
        // database tetap menandainya sudah dibaca untuk membersihkan badge admin.
        $newNotificationIds = Notifikasi::query()
            ->where('pengguna_id', $request->user()->id)
            ->where('sudah_dibaca', false)
            ->pluck('id')
            ->all();

        Notifikasi::query()
            ->whereIn('id', $newNotificationIds)
            ->update(['sudah_dibaca' => true]);

        $query = $this->filteredQuery($filters);

        $notifikasi = $query->latest()->paginate(15)->withQueryString();

        return view('admin.notifikasi.index', [
            'notifikasi' => $notifikasi,
            'stats' => $this->stats(),
            'filters' => $filters,
            'isAdminInbox' => false,
            'resetRoute' => 'admin.notifikasi.index',
            'clearRoute' => 'admin.notifikasi.history.clear',
            'pageTitle' => 'Log Notifikasi',
            'pageDescription' => 'Pantau notifikasi untuk penumpang, pengemudi, dan admin.',
            'summaryDescription' => 'Ikhtisar notifikasi yang tersimpan di sistem.',
            'listDescription' => 'Notifikasi terbaru dari seluruh aktivitas sistem.',
            'newNotificationIds' => $newNotificationIds,
        ]);
    }

    public function users(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $roles = ['penumpang', 'pengemudi'];

        $query = $this->filteredQuery($filters)
            ->whereHas('pengguna', fn ($penggunaQuery) => $penggunaQuery->whereIn('peran', $roles));

        return view('admin.notifikasi.index', [
            'notifikasi' => $query->latest()->paginate(15)->withQueryString(),
            'stats' => $this->statsForRoles($roles),
            'filters' => $filters,
            'isAdminInbox' => false,
            'resetRoute' => 'admin.notifikasi.user',
            'clearRoute' => 'admin.notifikasi.user.history.clear',
            'pageTitle' => 'Log Notifikasi User',
            'pageDescription' => 'Pantau notifikasi yang dikirim ke penumpang dan pengemudi.',
            'summaryDescription' => 'Ikhtisar notifikasi untuk user aplikasi mobile.',
            'listDescription' => 'Notifikasi terbaru yang diterima penumpang dan pengemudi.',
            'newNotificationIds' => [],
        ]);
    }

    public function clearAdminHistory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(['7_days', '30_days', '90_days', 'all_read'])],
        ]);

        // Riwayat admin dibersihkan per akun, jadi notifikasi penumpang dan
        // pengemudi tidak ikut terhapus dari halaman mereka.
        $deletedCount = $this->notificationHistoryService->deleteReadHistory(
            $validated['period'],
            $request->user()->id,
        );

        return redirect()
            ->route('admin.notifikasi.admin')
            ->with(
                'success',
                $deletedCount > 0
                    ? "{$deletedCount} riwayat notifikasi admin berhasil dibersihkan."
                    : 'Tidak ada riwayat notifikasi admin yang perlu dibersihkan.',
            );
    }

    public function clearHistory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(['7_days', '30_days', '90_days', 'all_read'])],
        ]);

        // Admin boleh merapikan log global, tetapi service tetap mengunci query
        // pada status sudah dibaca supaya notifikasi baru semua role tetap aman.
        $deletedCount = $this->notificationHistoryService->deleteReadHistory($validated['period']);

        return redirect()
            ->route('admin.notifikasi.index')
            ->with(
                'success',
                $deletedCount > 0
                    ? "{$deletedCount} riwayat notifikasi berhasil dibersihkan."
                    : 'Tidak ada riwayat notifikasi yang perlu dibersihkan.',
            );
    }

    public function clearUserHistory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(['7_days', '30_days', '90_days', 'all_read'])],
        ]);

        // Log user mobile dibersihkan terpisah dari inbox admin agar data penting
        // untuk admin web tidak ikut terhapus saat merapikan riwayat penumpang/pengemudi.
        $deletedCount = $this->notificationHistoryService->deleteReadHistoryForRoles(
            $validated['period'],
            ['penumpang', 'pengemudi'],
        );

        return redirect()
            ->route('admin.notifikasi.user')
            ->with(
                'success',
                $deletedCount > 0
                    ? "{$deletedCount} riwayat notifikasi user berhasil dibersihkan."
                    : 'Tidak ada riwayat notifikasi user yang perlu dibersihkan.',
            );
    }

    private function escapeLikeValue(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    /**
     * Validasi filter tetap di backend supaya input dari URL tidak langsung
     * dipakai untuk query database.
     *
     * @return array<string, string|null>
     */
    private function validatedFilters(Request $request): array
    {
        $tanggalSelesaiRules = ['nullable', 'date'];

        if ($request->filled('tanggal_mulai')) {
            $tanggalSelesaiRules[] = 'after_or_equal:tanggal_mulai';
        }

        return $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'jenis' => ['nullable', Rule::in(['pembayaran', 'perjalanan', 'pemesanan', 'sistem'])],
            'dibaca' => ['nullable', Rule::in(['0', '1'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => $tanggalSelesaiRules,
        ], [
            'tanggal_mulai.date' => 'Tanggal mulai tidak valid.',
            'tanggal_selesai.date' => 'Tanggal selesai tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ]);
    }

    /**
     * Query filter dibuat satu tempat agar halaman log global dan inbox admin
     * membaca data dengan aturan pencarian yang sama.
     *
     * @param  array<string, string|null>  $filters
     */
    private function filteredQuery(array $filters)
    {
        $query = Notifikasi::query()->with([
            'pengguna',
            'referensiBooking.pengguna',
            'referensiBooking.jadwal.rute',
            'referensiBooking.jadwal.kendaraan',
            'referensiJadwal.rute',
            'referensiJadwal.kendaraan',
            'referensiJadwal.pengemudi.pengguna',
        ]);

        if (filled($filters['search'] ?? null)) {
            $search = $this->escapeLikeValue((string) $filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('judul', 'like', "%{$search}%")
                    ->orWhere('pesan', 'like', "%{$search}%")
                    ->orWhereHas('pengguna', fn ($penggunaQuery) => $penggunaQuery->where('nama', 'like', "%{$search}%"));
            });
        }

        if (filled($filters['jenis'] ?? null)) {
            if ($filters['jenis'] === 'pemesanan') {
                $query->where('judul', 'Pemesanan baru masuk');
            } else {
                $query->where('jenis', $filters['jenis']);
            }
        }

        if (filled($filters['dibaca'] ?? null)) {
            $query->where('sudah_dibaca', $filters['dibaca'] === '1');
        }

        if (filled($filters['tanggal_mulai'] ?? null)) {
            // Filter tanggal memakai created_at karena notifikasi tersimpan saat event sistem terjadi.
            $query->whereDate('created_at', '>=', $filters['tanggal_mulai']);
        }

        if (filled($filters['tanggal_selesai'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['tanggal_selesai']);
        }

        return $query;
    }

    /**
     * Statistik bisa dihitung global atau khusus satu admin, sesuai halaman.
     *
     * @return array<string, int>
     */
    private function stats(?int $adminId = null): array
    {
        $baseQuery = Notifikasi::query()
            ->when($adminId, fn ($query) => $query->where('pengguna_id', $adminId));

        return [
            'total' => (clone $baseQuery)->count(),
            'belum_dibaca' => (clone $baseQuery)->where('sudah_dibaca', false)->count(),
            'pembayaran' => (clone $baseQuery)->where('jenis', 'pembayaran')->count(),
            'pemesanan' => (clone $baseQuery)->where('judul', 'Pemesanan baru masuk')->count(),
        ];
    }

    /**
     * Statistik ini khusus untuk melihat notifikasi user mobile, jadi admin web
     * bisa membedakan inbox admin dengan riwayat penumpang/pengemudi.
     *
     * @param  array<int, string>  $roles
     * @return array<string, int>
     */
    private function statsForRoles(array $roles): array
    {
        $baseQuery = Notifikasi::query()
            ->whereHas('pengguna', fn ($penggunaQuery) => $penggunaQuery->whereIn('peran', $roles));

        return [
            'total' => (clone $baseQuery)->count(),
            'belum_dibaca' => (clone $baseQuery)->where('sudah_dibaca', false)->count(),
            'pembayaran' => (clone $baseQuery)->where('jenis', 'pembayaran')->count(),
            'pemesanan' => (clone $baseQuery)->where('judul', 'Pemesanan baru masuk')->count(),
        ];
    }
}
