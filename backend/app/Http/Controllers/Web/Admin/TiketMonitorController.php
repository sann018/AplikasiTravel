<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\TiketElektronik;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TiketMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status_scan' => ['nullable', Rule::in(['sudah_dipindai', 'belum_dipindai'])],
            'jadwal_id' => ['nullable', 'integer', 'exists:jadwal,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
        ]);

        $query = TiketElektronik::query()
            ->with([
                'detailPemesanan.pemesanan.pengguna',
                'detailPemesanan.pemesanan.jadwal.rute',
                'detailPemesanan.kursiJadwal',
            ]);

        if (filled($filters['search'] ?? null)) {
            $search = addcslashes((string) $filters['search'], "\\%_");
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('kode_qr', 'like', "%{$search}%")
                    ->orWhereHas('detailPemesanan.pemesanan', fn ($q) => $q
                        ->where('kode_booking', 'like', "%{$search}%")
                        ->orWhereHas('pengguna', fn ($q2) => $q2->where('nama', 'like', "%{$search}%"))
                    );
            });
        }

        if (filled($filters['status_scan'] ?? null)) {
            $query->where('sudah_dipindai', $filters['status_scan'] === 'sudah_dipindai');
        }

        if (filled($filters['jadwal_id'] ?? null)) {
            $query->whereHas('detailPemesanan.pemesanan', fn ($q) => $q
                ->where('jadwal_id', $filters['jadwal_id'])
            );
        }

        if (filled($filters['tanggal_mulai'] ?? null)) {
            $query->whereDate('diterbitkan_pada', '>=', $filters['tanggal_mulai']);
        }

        if (filled($filters['tanggal_selesai'] ?? null)) {
            $query->whereDate('diterbitkan_pada', '<=', $filters['tanggal_selesai']);
        }

        $tickets = $query->latest('diterbitkan_pada')->paginate(20)->withQueryString();

        $summary = [
            'total' => TiketElektronik::query()->count(),
            'sudah_dipindai' => TiketElektronik::query()->where('sudah_dipindai', true)->count(),
            'belum_dipindai' => TiketElektronik::query()->where('sudah_dipindai', false)->count(),
        ];

        $jadwalOptions = Jadwal::query()
            ->with('rute')
            ->whereHas('pemesanan.detailPemesanan.tiketElektronik')
            ->orderByDesc('waktu_berangkat')
            ->limit(50)
            ->get();

        return view('admin.tiket-monitor.index', [
            'tickets' => $tickets,
            'summary' => $summary,
            'filters' => $filters,
            'jadwalOptions' => $jadwalOptions,
        ]);
    }
}
