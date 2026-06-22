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
    public function __construct(private readonly NotificationHistoryService $notificationHistoryService)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'jenis' => ['nullable', Rule::in(['pembayaran', 'perjalanan', 'pemesanan', 'sistem'])],
            'dibaca' => ['nullable', Rule::in(['0', '1'])],
        ]);

        // Halaman admin tetap menampilkan log semua role, tetapi status baca
        // hanya diubah untuk notifikasi milik admin yang sedang login.
        Notifikasi::query()
            ->where('pengguna_id', $request->user()->id)
            ->where('sudah_dibaca', false)
            ->update(['sudah_dibaca' => true]);

        $query = Notifikasi::query()->with('pengguna');

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

        $notifikasi = $query->latest()->paginate(15)->withQueryString();

        return view('admin.notifikasi.index', [
            'notifikasi' => $notifikasi,
            'stats' => [
                'total' => Notifikasi::query()->count(),
                'belum_dibaca' => Notifikasi::query()->where('sudah_dibaca', false)->count(),
                'pembayaran' => Notifikasi::query()->where('jenis', 'pembayaran')->count(),
                'pemesanan' => Notifikasi::query()->where('judul', 'Pemesanan baru masuk')->count(),
            ],
            'filters' => $filters,
        ]);
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

    private function escapeLikeValue(string $value): string
    {
        return addcslashes($value, "\\%_");
    }
}
