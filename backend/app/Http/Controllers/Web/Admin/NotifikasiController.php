<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotifikasiController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'jenis' => ['nullable', Rule::in(['pembayaran', 'perjalanan', 'pemesanan', 'sistem'])],
            'dibaca' => ['nullable', Rule::in(['0', '1'])],
        ]);

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
            $query->where('jenis', $filters['jenis']);
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
                'pemesanan' => Notifikasi::query()->where('jenis', 'pemesanan')->count(),
            ],
            'filters' => $filters,
        ]);
    }

    private function escapeLikeValue(string $value): string
    {
        return addcslashes($value, "\\%_");
    }
}
