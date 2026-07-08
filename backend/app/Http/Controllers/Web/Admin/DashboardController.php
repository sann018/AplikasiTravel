<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BiayaOperasionalPerjalanan;
use App\Models\Jadwal;
use App\Models\Kendaraan;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\Pengemudi;
use App\Models\Rute;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = today();
        $chartStartDate = $today->copy()->subDays(6);
        $pendapatanRaw = Pembayaran::query()
            ->selectRaw('DATE(dibayar_pada) as tanggal, SUM(jumlah_bayar) as total')
            ->where('status_bayar', 'berhasil')
            ->whereDate('dibayar_pada', '>=', $chartStartDate)
            ->groupByRaw('DATE(dibayar_pada)')
            ->pluck('total', 'tanggal');

        $pendapatanHarian = collect(range(0, 6))->map(function (int $offset) use ($chartStartDate, $pendapatanRaw) {
            $tanggal = $chartStartDate->copy()->addDays($offset);

            return [
                'date' => $tanggal,
                'label' => $tanggal->translatedFormat('D'),
                'total' => (float) ($pendapatanRaw[$tanggal->toDateString()] ?? 0),
            ];
        });

        $stats = [
            'total_armada' => Kendaraan::query()->count(),
            'jumlah_pengemudi' => Pengemudi::query()->count(),
            'pengemudi_aktif' => Pengemudi::query()->where('status', 'aktif')->count(),
            'rute_aktif' => Rute::query()->where('status_aktif', true)->count(),
            'jadwal_hari_ini' => Jadwal::query()->whereDate('waktu_berangkat', $today)->count(),
            'pemesanan_hari_ini' => Pemesanan::query()->whereDate('created_at', $today)->count(),
            'booking_menunggu' => Pemesanan::query()->where('status_pemesanan', 'menunggu_pembayaran')->count(),
            'pendapatan_harian' => (float) Pembayaran::query()
                ->where('status_bayar', 'berhasil')
                ->whereDate('dibayar_pada', $today)
                ->sum('jumlah_bayar'),
            'biaya_harian' => (float) BiayaOperasionalPerjalanan::query()
                ->whereDate('tanggal_biaya', $today)
                ->sum('nominal'),
        ];

        $jadwalHariIni = Jadwal::query()
            ->with(['rute', 'kendaraan', 'pengemudi.pengguna', 'kursi'])
            ->whereDate('waktu_berangkat', $today)
            ->orderBy('waktu_berangkat')
            ->limit(5)
            ->get();

        $pemesananTerbaru = Pemesanan::query()
            ->with(['pengguna', 'jadwal.rute'])
            ->latest()
            ->limit(5)
            ->get();

        $biayaOperasionalTerbaru = BiayaOperasionalPerjalanan::query()
            ->with(['jadwal.rute'])
            ->latest('tanggal_biaya')
            ->limit(5)
            ->get();

        return view('admin.dashboard.index', [
            'stats' => $stats,
            'jadwalHariIni' => $jadwalHariIni,
            'pemesananTerbaru' => $pemesananTerbaru,
            'pendapatanHarian' => $pendapatanHarian,
            'biayaOperasionalTerbaru' => $biayaOperasionalTerbaru,
        ]);
    }
}
