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

    public function exportPemesanan(Request $request)
    {
        $bookings = $this->bookingQuery($request)
            ->latest('id')
            ->get();
        $rows = [[
            'No',
            'Kode Pemesanan',
            'Tanggal Pemesanan',
            'Penumpang',
            'No HP',
            'Rute',
            'Waktu Berangkat',
            'Status Pemesanan',
            'Status Pembayaran',
            'Jumlah Kursi',
            'Total Harga',
        ]];

        foreach ($bookings as $index => $booking) {
            $rows[] = [
                (string) ($index + 1),
                $booking->kode_booking,
                $booking->created_at->format('Y-m-d H:i:s'),
                $booking->pengguna->nama,
                $booking->pengguna->no_hp,
                $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan,
                $booking->jadwal->waktu_berangkat->format('Y-m-d H:i:s'),
                $booking->status_pemesanan,
                $booking->pembayaran?->status_bayar ?? 'draft',
                (string) $booking->detail_pemesanan_count,
                (string) (int) $booking->total_harga,
            ];
        }

        return $this->downloadReport($rows, 'laporan-pemesanan', $request, [
            'title' => 'LAPORAN PEMESANAN',
            'period' => $this->periodLabel($request),
            'summary' => [
                'Total pemesanan' => $bookings->count(),
                'Total kursi' => $bookings->sum('detail_pemesanan_count'),
                'Total nilai' => (float) $bookings->sum('total_harga'),
            ],
            'money_columns' => ['Total Harga'],
        ]);
    }

    public function exportTransaksi(Request $request)
    {
        $transactions = $this->transactionQuery($request)
            ->latest('id')
            ->get();
        $rows = [[
            'No',
            'Kode Transaksi',
            'Kode Pemesanan',
            'Penumpang',
            'Rute',
            'Metode Bayar',
            'Status Bayar',
            'Nominal',
            'Channel',
            'Waktu Dibuat',
            'Waktu Dibayar',
        ]];

        foreach ($transactions as $index => $transaction) {
            $rows[] = [
                (string) ($index + 1),
                $transaction->order_id_gateway ?: '-',
                $transaction->pemesanan?->kode_booking ?: '-',
                $transaction->pemesanan?->pengguna?->nama ?: '-',
                $transaction->pemesanan
                    ? $transaction->pemesanan->jadwal->rute->kota_asal.' - '.$transaction->pemesanan->jadwal->rute->kota_tujuan
                : '-',
                strtolower($transaction->metode_bayar),
                $transaction->status_bayar,
                (string) (int) $transaction->jumlah_bayar,
                $transaction->paid_channel ?: '-',
                $transaction->created_at->format('Y-m-d H:i:s'),
                $transaction->dibayar_pada?->format('Y-m-d H:i:s') ?: '-',
            ];
        }

        return $this->downloadReport($rows, 'laporan-transaksi', $request, [
            'title' => 'LAPORAN TRANSAKSI',
            'period' => $this->periodLabel($request),
            'summary' => [
                'Total transaksi' => $transactions->count(),
                'Transaksi berhasil' => $transactions->where('status_bayar', 'berhasil')->count(),
                'Total berhasil' => (float) $transactions->where('status_bayar', 'berhasil')->sum('jumlah_bayar'),
            ],
            'money_columns' => ['Nominal'],
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

    private function downloadReport(array $rows, string $baseName, Request $request, array $meta = [])
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        $format = in_array($format, ['csv', 'xls'], true) ? $format : 'csv';
        $filename = $baseName.'-'.now()->format('Ymd-His');

        return match ($format) {
            'xls' => $this->downloadHtmlExcel($rows, "{$filename}.xls", $baseName, $request, $meta),
            default => $this->downloadCsv($rows, "{$filename}.csv"),
        };
    }

    private function downloadCsv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadHtmlExcel(array $rows, string $filename, string $baseName, Request $request, array $meta = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $baseName, $request, $meta) {
            $title = $meta['title'] ?? strtoupper(str_replace('-', ' ', $baseName));
            $period = $meta['period'] ?? $this->periodLabel($request);
            $printedBy = auth()->user()?->nama ?? 'Admin';
            $moneyColumns = $meta['money_columns'] ?? [];
            $headers = $rows[0] ?? [];

            echo '<html><head><meta charset="UTF-8">';
            echo '<style>
                body{font-family:Arial,sans-serif;color:#10233f;}
                table{border-collapse:collapse;font-size:12px;width:100%;}
                th{background:#10233f;color:#ffffff;font-weight:bold;text-align:center;}
                th,td{border:1px solid #cbd5e1;padding:8px;vertical-align:middle;white-space:normal;mso-wrap-style:square;}
                td{text-align:left;}
                .center{text-align:center;}
                .right{text-align:right;}
                .meta td{border:none;padding:3px 0;}
                .brand{font-size:18px;font-weight:bold;letter-spacing:.06em;}
                .title{font-size:15px;font-weight:bold;margin-top:4px;}
                .summary td{font-weight:bold;background:#eef2f7;}
            </style></head><body>';
            echo '<div class="brand">RUSLY TRAVEL</div>';
            echo '<div class="title">'.e($title).'</div>';
            echo '<table class="meta" style="margin:8px 0 14px;">';
            echo '<tr><td style="width:120px;">Periode</td><td>: '.e($period).'</td></tr>';
            echo '<tr><td>Dicetak oleh</td><td>: '.e($printedBy).'</td></tr>';
            echo '<tr><td>Tanggal cetak</td><td>: '.e(now()->translatedFormat('d F Y H:i')).'</td></tr>';
            echo '</table>';
            echo '<table>';
            echo '<colgroup>';
            foreach ($headers as $header) {
                $width = match ($header) {
                    'No' => 48,
                    'Rute' => 180,
                    'Kode Pemesanan', 'Kode Transaksi' => 150,
                    'Penumpang' => 140,
                    default => 120,
                };
                echo '<col style="width:'.$width.'px;">';
            }
            echo '</colgroup>';

            foreach ($rows as $rowIndex => $row) {
                echo '<tr>';

                foreach ($row as $cellIndex => $cell) {
                    $tag = $rowIndex === 0 ? 'th' : 'td';
                    $header = $headers[$cellIndex] ?? '';
                    $class = $rowIndex === 0 || in_array($header, ['No', 'Jumlah Kursi'], true)
                        ? ' class="center"'
                        : (in_array($header, $moneyColumns, true) ? ' class="right"' : '');
                    $value = $rowIndex > 0 && in_array($header, $moneyColumns, true)
                        ? $this->formatRupiah((float) $cell)
                        : (string) $cell;
                    echo "<{$tag}{$class}>".e($value)."</{$tag}>";
                }

                echo '</tr>';
            }

            if (! empty($meta['summary'])) {
                echo '<tr class="summary"><td colspan="'.max(count($headers), 1).'">';
                $parts = [];
                foreach ($meta['summary'] as $label => $value) {
                    $parts[] = $label.': '.(is_float($value) ? $this->formatRupiah($value) : number_format((float) $value, 0, ',', '.'));
                }
                echo e(implode(' | ', $parts));
                echo '</td></tr>';
            }

            echo '</table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function periodLabel(Request $request): string
    {
        $start = $request->filled('tanggal_mulai')
            ? $request->date('tanggal_mulai')?->translatedFormat('d F Y')
            : null;
        $end = $request->filled('tanggal_selesai')
            ? $request->date('tanggal_selesai')?->translatedFormat('d F Y')
            : null;

        return match (true) {
            $start && $end => "{$start} - {$end}",
            $start => "Mulai {$start}",
            $end => "Sampai {$end}",
            default => 'Semua periode',
        };
    }

    private function formatRupiah(float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }
}
