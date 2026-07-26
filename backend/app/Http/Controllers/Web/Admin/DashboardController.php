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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = today();
        // Rekap mingguan memakai periode tetap Senin-Minggu agar mudah dibandingkan antarpekan.
        $weekStartDate = $today->copy()->startOfWeek(Carbon::MONDAY);
        // Pendapatan memakai nominal transaksi RuslyTravel, tanpa fee Pakasir.
        $pendapatanRaw = Pembayaran::query()
            ->join('pemesanan', 'pemesanan.id', '=', 'pembayaran.pemesanan_id')
            ->selectRaw('DATE(pembayaran.dibayar_pada) as tanggal, SUM(COALESCE(pembayaran.nominal_transaksi, pemesanan.total_harga)) as total')
            ->where('pembayaran.status_bayar', 'berhasil')
            ->whereIn('pemesanan.status_pemesanan', ['terbayar', 'selesai'])
            ->whereDate('pembayaran.dibayar_pada', '>=', $weekStartDate)
            ->whereDate('pembayaran.dibayar_pada', '<=', $today)
            ->groupByRaw('DATE(pembayaran.dibayar_pada)')
            ->pluck('total', 'tanggal');

        $pendapatanHarian = collect(range(0, 6))->map(function (int $offset) use ($weekStartDate, $today, $pendapatanRaw) {
            $tanggal = $weekStartDate->copy()->addDays($offset);

            return [
                'date' => $tanggal,
                'label' => $tanggal->translatedFormat('D'),
                'total' => (int) ($pendapatanRaw[$tanggal->toDateString()] ?? 0),
                'is_future' => $tanggal->isAfter($today),
            ];
        });
        $pendapatanMingguIni = (int) $pendapatanHarian->sum('total');

        $stats = [
            'total_armada' => Kendaraan::query()->count(),
            'armada_aktif' => Kendaraan::query()->where('status_kendaraan', 'aktif')->count(),
            'jumlah_pengemudi' => Pengemudi::query()->count(),
            'pengemudi_aktif' => Pengemudi::query()->where('status', 'aktif')->count(),
            'rute_aktif' => Rute::query()->where('status_aktif', true)->count(),
            'jadwal_hari_ini' => Jadwal::query()->whereDate('waktu_berangkat', $today)->count(),
            'pemesanan_hari_ini' => Pemesanan::query()->whereDate('created_at', $today)->count(),
            'booking_menunggu' => Pemesanan::query()->where('status_pemesanan', 'menunggu_pembayaran')->count(),
            'pendapatan_minggu_ini' => $pendapatanMingguIni,
            // Pemesanan berhasil dihitung dari pembayaran sah pada bulan berjalan.
            'pemesanan_berhasil_bulan_ini' => Pembayaran::query()
                ->where('status_bayar', 'berhasil')
                ->whereHas('pemesanan', fn ($booking) => $booking->whereIn('status_pemesanan', ['terbayar', 'selesai']))
                ->whereYear('dibayar_pada', $today->year)
                ->whereMonth('dibayar_pada', $today->month)
                ->count(),
            'pendapatan_harian' => (int) Pembayaran::query()
                ->join('pemesanan', 'pemesanan.id', '=', 'pembayaran.pemesanan_id')
                ->where('pembayaran.status_bayar', 'berhasil')
                ->whereIn('pemesanan.status_pemesanan', ['terbayar', 'selesai'])
                ->whereDate('pembayaran.dibayar_pada', $today)
                ->sum(DB::raw('COALESCE(pembayaran.nominal_transaksi, pemesanan.total_harga)')),
            'biaya_harian' => (int) BiayaOperasionalPerjalanan::query()
                ->finalized()
                ->whereDate('tanggal_biaya', $today)
                ->sum('nominal'),
        ];

        $jadwalHariIni = Jadwal::query()
            ->with(['rute', 'kendaraan', 'pengemudi.pengguna', 'kursi'])
            ->whereDate('waktu_berangkat', $today)
            ->orderBy('waktu_berangkat')
            ->limit(5)
            ->get();

        return view('admin.dashboard.index', [
            'stats' => $stats,
            'jadwalHariIni' => $jadwalHariIni,
            'pendapatanHarian' => $pendapatanHarian,
        ]);
    }
}
