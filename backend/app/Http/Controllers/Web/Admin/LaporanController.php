<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class LaporanController extends Controller
{
    public function pemesanan(Request $request): View
    {
        $query = $this->bookingQuery($request);

        $bookings = $query->latest()->paginate(20)->withQueryString();

        return view('admin.laporan.pemesanan', [
            'bookings' => $bookings,
            'summary' => [
                'total_booking' => (clone $query)->count(),
                'total_kursi' => (clone $query)->sum('jumlah_kursi'),
                'total_nilai' => (float) (clone $query)->sum('total_harga'),
            ],
            'filters' => $request->only(['status', 'tanggal_mulai', 'tanggal_selesai']),
        ]);
    }

    public function transaksi(Request $request): View
    {
        $query = $this->transactionQuery($request);

        $transactions = $query->latest()->paginate(20)->withQueryString();

        return view('admin.laporan.transaksi', [
            'transactions' => $transactions,
            'summary' => [
                'total_transaksi' => (clone $query)->count(),
                'berhasil' => (clone $query)->where('status_bayar', 'berhasil')->count(),
                'nilai_berhasil' => (float) (clone $query)
                    ->where('status_bayar', 'berhasil')
                    ->sum('jumlah_bayar'),
            ],
            'filters' => $request->only(['status', 'metode_bayar', 'tanggal_mulai', 'tanggal_selesai']),
        ]);
    }

    public function exportPemesanan(Request $request): StreamedResponse
    {
        $rows = $this->bookingQuery($request)
            ->latest('id')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Kode Booking',
                'Tanggal Booking',
                'Penumpang',
                'No HP',
                'Rute',
                'Waktu Berangkat',
                'Status Booking',
                'Status Pembayaran',
                'Jumlah Kursi',
                'Total Harga',
            ], ';');

            foreach ($rows as $booking) {
                fputcsv($handle, [
                    $booking->kode_booking,
                    $booking->created_at->format('Y-m-d H:i:s'),
                    $booking->pengguna->nama,
                    $booking->pengguna->no_hp,
                    $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan,
                    $booking->jadwal->waktu_berangkat->format('Y-m-d H:i:s'),
                    $booking->status_pemesanan,
                    $booking->pembayaran?->status_bayar ?? 'draft',
                    $booking->detail_pemesanan_count,
                    (string) $booking->total_harga,
                ], ';');
            }

            fclose($handle);
        }, 'laporan-pemesanan-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportTransaksi(Request $request): StreamedResponse
    {
        $rows = $this->transactionQuery($request)
            ->latest('id')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Order Gateway',
                'Kode Booking',
                'Penumpang',
                'Rute',
                'Metode Bayar',
                'Status Bayar',
                'Nominal',
                'Channel',
                'Waktu Dibuat',
                'Waktu Dibayar',
            ], ';');

            foreach ($rows as $transaction) {
                fputcsv($handle, [
                    $transaction->order_id_gateway ?: '-',
                    $transaction->pemesanan?->kode_booking ?: '-',
                    $transaction->pemesanan?->pengguna?->nama ?: '-',
                    $transaction->pemesanan
                        ? $transaction->pemesanan->jadwal->rute->kota_asal.' - '.$transaction->pemesanan->jadwal->rute->kota_tujuan
                        : '-',
                    strtolower($transaction->metode_bayar),
                    $transaction->status_bayar,
                    (string) $transaction->jumlah_bayar,
                    $transaction->paid_channel ?: '-',
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->dibayar_pada?->format('Y-m-d H:i:s') ?: '-',
                ], ';');
            }

            fclose($handle);
        }, 'laporan-transaksi-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function bookingQuery(Request $request)
    {
        $query = Pemesanan::query()
            ->with(['pengguna', 'jadwal.rute', 'pembayaran'])
            ->withCount('detailPemesanan');

        if ($request->filled('status')) {
            $query->where('status_pemesanan', $request->string('status'));
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('created_at', '>=', $request->date('tanggal_mulai'));
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('created_at', '<=', $request->date('tanggal_selesai'));
        }

        return $query;
    }

    private function transactionQuery(Request $request)
    {
        $query = Pembayaran::query()->with(['pemesanan.pengguna', 'pemesanan.jadwal.rute']);

        if ($request->filled('status')) {
            $query->where('status_bayar', $request->string('status'));
        }

        if ($request->filled('metode_bayar')) {
            $query->where('metode_bayar', strtolower((string) $request->string('metode_bayar')));
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('created_at', '>=', $request->date('tanggal_mulai'));
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('created_at', '<=', $request->date('tanggal_selesai'));
        }

        return $query;
    }
}
