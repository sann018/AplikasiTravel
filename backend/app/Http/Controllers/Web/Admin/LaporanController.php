<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Support\PaymentMethodCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    public function okupansi(Request $request): View
    {
        $jadwals = $this->occupancyQuery($request)
            ->latest('waktu_berangkat')
            ->paginate(20)
            ->withQueryString();

        // Hitung ringkasan kursi dari status kursi jadwal yang tersimpan.
        $jadwals->getCollection()->transform(fn (Jadwal $jadwal) => $this->appendOccupancy($jadwal));

        return view('admin.laporan.okupansi', [
            'jadwals' => $jadwals,
            'filters' => $request->only(['status', 'source', 'tanggal_mulai', 'tanggal_selesai']),
        ]);
    }

    public function exportOkupansi(Request $request): StreamedResponse
    {
        $jadwals = $this->occupancyQuery($request)
            ->latest('waktu_berangkat')
            ->get()
            ->map(fn (Jadwal $jadwal) => $this->appendOccupancy($jadwal));

        $rows = [[
            'No', 'Tanggal', 'Jam', 'Rute', 'Kendaraan', 'Pengemudi',
            'Status Jadwal', 'Kursi Terisi', 'Total Kursi', 'Kursi Tersedia',
            'Kursi Terkunci', 'Persentase Okupansi',
        ]];

        foreach ($jadwals as $index => $jadwal) {
            $rows[] = [
                (string) ($index + 1),
                $jadwal->waktu_berangkat?->format('d/m/Y') ?: '-',
                $jadwal->waktu_berangkat?->format('H:i') ?: '-',
                trim(($jadwal->rute?->kota_asal ?: '-').' - '.($jadwal->rute?->kota_tujuan ?: '-')),
                $jadwal->kendaraan?->nomor_plat ?: '-',
                $jadwal->pengemudi?->pengguna?->nama ?: '-',
                ucwords(str_replace('_', ' ', (string) $jadwal->status)),
                (string) $jadwal->kursi_terisi,
                (string) $jadwal->total_kursi,
                (string) $jadwal->kursi_tersedia,
                (string) $jadwal->kursi_terkunci,
                (string) $jadwal->persen_okupansi,
            ];
        }

        return $this->downloadReport($rows, 'laporan-okupansi', $request, [
            'title' => 'LAPORAN OKUPANSI KURSI',
            'period' => $this->periodLabel($request),
            'summary' => [
                'Total jadwal' => $jadwals->count(),
                'Total kursi terisi' => $jadwals->sum('kursi_terisi'),
                'Total kursi' => $jadwals->sum('total_kursi'),
            ],
        ]);
    }

    public function pemesanan(Request $request): View
    {
        $query = $this->bookingQuery($request);
        $summary = [
            'total_booking' => (clone $query)->count(),
            'terbayar' => (clone $query)->where('status_pemesanan', 'terbayar')->count(),
            'dibatalkan' => (clone $query)->where('status_pemesanan', 'dibatalkan')->count(),
            'selesai' => (clone $query)->where('status_pemesanan', 'selesai')->count(),
            'total_kursi' => (clone $query)->sum('jumlah_kursi'),
            'total_nilai' => (int) (clone $query)->sum('total_harga'),
        ];
        $bookings = (clone $query)->latest()->paginate(20)->withQueryString();

        return view('admin.laporan.pemesanan', [
            'bookings' => $bookings,
            'summary' => $summary,
            'filters' => $request->only(['status', 'tanggal_mulai', 'tanggal_selesai']),
            'activePeriodLabel' => $this->periodLabel($request),
        ]);
    }

    public function transaksi(Request $request): View
    {
        $query = $this->transactionQuery($request);
        $transactions = $query->latest()->paginate(20)->withQueryString();
        $successfulTransactions = (clone $query)
            ->where('status_bayar', 'berhasil')
            ->whereHas('pemesanan', fn ($booking) => $booking->whereIn('status_pemesanan', ['terbayar', 'selesai']));
        $successfulPayments = (clone $successfulTransactions)->get();
        $today = today();

        // Ringkasan periode selalu memakai transaksi sah dan nominal milik RuslyTravel.
        $periodRevenueQuery = Pembayaran::query()
            ->join('pemesanan', 'pemesanan.id', '=', 'pembayaran.pemesanan_id')
            ->where('pembayaran.status_bayar', 'berhasil')
            ->whereIn('pemesanan.status_pemesanan', ['terbayar', 'selesai']);

        return view('admin.laporan.transaksi', [
            'transactions' => $transactions,
            'summary' => [
                'total_transaksi' => (clone $query)->count(),
                'berhasil' => $successfulPayments->count(),
                // Fee Pakasir ditampilkan terpisah dan tidak masuk pendapatan.
                'nilai_berhasil' => (int) $successfulPayments
                    ->sum(fn ($payment) => $payment->nominal_transaksi ?? (int) $payment->pemesanan?->total_harga),
                'biaya_admin_provider' => (int) $successfulPayments->sum('biaya_admin_provider'),
            ],
            'periodSummary' => [
                'pendapatan_tahun_ini' => (int) (clone $periodRevenueQuery)
                    ->whereYear('pembayaran.dibayar_pada', $today->year)
                    ->sum(DB::raw('COALESCE(pembayaran.nominal_transaksi, pemesanan.total_harga)')),
                'pendapatan_bulan_ini' => (int) (clone $periodRevenueQuery)
                    ->whereYear('pembayaran.dibayar_pada', $today->year)
                    ->whereMonth('pembayaran.dibayar_pada', $today->month)
                    ->sum(DB::raw('COALESCE(pembayaran.nominal_transaksi, pemesanan.total_harga)')),
                'tahun_label' => (string) $today->year,
                'bulan_label' => $today->translatedFormat('F Y'),
            ],
            'filters' => $request->only(['status', 'metode_bayar', 'tanggal_mulai', 'tanggal_selesai']),
            'activePeriodLabel' => $this->periodLabel($request),
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
            'Sumber Pemesanan',
            'Admin Pencatat',
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
                $booking->namaKontakPemesan(),
                $booking->nomorKontakPemesan() ?: '-',
                $booking->isPemesananLoket() ? 'Loket' : 'Aplikasi Penumpang',
                $booking->dibuatOleh?->nama ?: '-',
                $booking->jadwal->rute->kota_asal.' - '.$booking->jadwal->rute->kota_tujuan,
                $booking->jadwal->waktu_berangkat->format('Y-m-d H:i:s'),
                ucwords(str_replace('_', ' ', $booking->status_pemesanan)),
                $booking->pembayaran
                    ? ucwords(str_replace('_', ' ', $booking->pembayaran->status_bayar))
                    : 'Belum Tersedia',
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
                'Total nilai' => (int) $bookings->sum('total_harga'),
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
            'Sumber Pemesanan',
            'Admin Pencatat',
            'Rute',
            'Metode Bayar',
            'Status Bayar',
            'Pendapatan RuslyTravel',
            'Biaya Admin Pakasir',
            'Dibayar Penumpang',
            'Channel',
            'Waktu Dibuat',
            'Waktu Dibayar',
        ]];

        foreach ($transactions as $index => $transaction) {
            $rows[] = [
                (string) ($index + 1),
                $transaction->order_id_gateway ?: '-',
                $transaction->pemesanan?->kode_booking ?: '-',
                $transaction->pemesanan?->namaKontakPemesan() ?: '-',
                $transaction->pemesanan?->isPemesananLoket() ? 'Loket' : 'Aplikasi Penumpang',
                $transaction->pemesanan?->dibuatOleh?->nama ?: '-',
                $transaction->pemesanan
                    ? $transaction->pemesanan->jadwal->rute->kota_asal.' - '.$transaction->pemesanan->jadwal->rute->kota_tujuan
                : '-',
                PaymentMethodCatalog::label(strtolower($transaction->metode_bayar)),
                ucwords(str_replace('_', ' ', $transaction->status_bayar)),
                (string) ($transaction->nominal_transaksi ?? (int) $transaction->pemesanan?->total_harga),
                (string) ($transaction->biaya_admin_provider ?? 0),
                $transaction->total_dibayar_penumpang === null
                    ? ''
                    : (string) $transaction->total_dibayar_penumpang,
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
                'Pendapatan RuslyTravel' => (int) $transactions
                    ->where('status_bayar', 'berhasil')
                    ->filter(fn ($payment) => in_array($payment->pemesanan?->status_pemesanan, ['terbayar', 'selesai'], true))
                    ->sum(fn ($payment) => $payment->nominal_transaksi ?? (int) $payment->pemesanan?->total_harga),
                'Biaya admin Pakasir' => (int) $transactions->where('status_bayar', 'berhasil')->sum('biaya_admin_provider'),
            ],
            'money_columns' => ['Pendapatan RuslyTravel', 'Biaya Admin Pakasir', 'Dibayar Penumpang'],
        ]);
    }

    private function bookingQuery(Request $request)
    {
        $tanggalSelesaiRules = ['nullable', 'date'];
        if ($request->filled('tanggal_mulai')) {
            $tanggalSelesaiRules[] = 'after_or_equal:tanggal_mulai';
        }

        // Validasi dipakai bersama oleh tampilan dan export laporan pemesanan.
        $request->validate([
            'status' => ['nullable', Rule::in(['menunggu_pembayaran', 'terbayar', 'dibatalkan', 'selesai'])],
            'source' => ['nullable', Rule::in(['aplikasi_penumpang', 'loket'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => $tanggalSelesaiRules,
        ], $this->dateFilterMessages());

        $query = Pemesanan::query()
            ->with(['pengguna', 'dibuatOleh', 'jadwal.rute', 'pembayaran'])
            ->withCount('detailPemesanan');

        if ($request->filled('status')) {
            $query->where('status_pemesanan', $request->string('status'));
        }

        if ($request->filled('source')) {
            $query->where('sumber_pemesanan', $request->string('source'));
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('created_at', '>=', $request->date('tanggal_mulai'));
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('created_at', '<=', $request->date('tanggal_selesai'));
        }

        return $query;
    }

    private function occupancyQuery(Request $request)
    {
        $tanggalSelesaiRules = ['nullable', 'date'];
        if ($request->filled('tanggal_mulai')) {
            $tanggalSelesaiRules[] = 'after_or_equal:tanggal_mulai';
        }

        // Filter laporan okupansi mengikuti jadwal, bukan mengubah data kursi.
        $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'dibuka', 'ditutup', 'berangkat', 'selesai', 'dibatalkan'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => $tanggalSelesaiRules,
        ], $this->dateFilterMessages());

        $query = Jadwal::query()->with(['rute', 'kendaraan', 'pengemudi.pengguna', 'kursi']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('waktu_berangkat', '>=', $request->date('tanggal_mulai'));
        }
        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('waktu_berangkat', '<=', $request->date('tanggal_selesai'));
        }

        return $query;
    }

    private function appendOccupancy(Jadwal $jadwal): Jadwal
    {
        $kursi = $jadwal->kursi;
        $total = $kursi->count() ?: (int) ($jadwal->kendaraan?->kapasitas_kursi ?? 0);
        $terisi = $kursi->where('status_kursi', 'dipesan')->count();
        $terkunci = $kursi->where('status_kursi', 'terkunci')->count();
        $tersedia = $kursi->where('status_kursi', 'tersedia')->count();

        $jadwal->setAttribute('total_kursi', $total);
        $jadwal->setAttribute('kursi_terisi', $terisi);
        $jadwal->setAttribute('kursi_terkunci', $terkunci);
        $jadwal->setAttribute('kursi_tersedia', $tersedia);
        $jadwal->setAttribute('persen_okupansi', $total > 0 ? round(($terisi / $total) * 100, 1) : 0);

        return $jadwal;
    }

    private function transactionQuery(Request $request)
    {
        $tanggalSelesaiRules = ['nullable', 'date'];
        if ($request->filled('tanggal_mulai')) {
            $tanggalSelesaiRules[] = 'after_or_equal:tanggal_mulai';
        }

        // Metode dan status dibatasi agar hasil laporan tidak memakai filter asing.
        $request->validate([
            'status' => ['nullable', Rule::in(['menunggu', 'berhasil', 'gagal', 'kedaluwarsa'])],
            'metode_bayar' => ['nullable', Rule::in(array_keys(PaymentMethodCatalog::adminOptions()))],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => $tanggalSelesaiRules,
        ], $this->dateFilterMessages());

        $query = Pembayaran::query()->with([
            'pemesanan.pengguna',
            'pemesanan.dibuatOleh',
            'pemesanan.jadwal.rute',
        ]);

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

    private function dateFilterMessages(): array
    {
        return [
            'tanggal_mulai.date' => 'Tanggal mulai tidak valid.',
            'tanggal_selesai.date' => 'Tanggal selesai tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ];
    }

    private function downloadReport(array $rows, string $baseName, Request $request, array $meta = [])
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        $format = in_array($format, ['csv', 'xls'], true) ? $format : 'csv';
        $filename = $baseName.'-'.now()->format('Ymd-His');

        return match ($format) {
            'xls' => $this->downloadExcelXml($rows, "{$filename}.xls", $baseName, $request, $meta),
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

    private function downloadExcelXml(array $rows, string $filename, string $baseName, Request $request, array $meta = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $baseName, $request, $meta) {
            $title = $meta['title'] ?? strtoupper(str_replace('-', ' ', $baseName));
            $period = $meta['period'] ?? $this->periodLabel($request);
            $printedBy = auth()->user()?->nama ?? 'Admin';
            $moneyColumns = $meta['money_columns'] ?? [];
            $headers = $rows[0] ?? [];
            $columnCount = max(count($headers), 1);
            $lastDataRow = 6 + max(count($rows) - 1, 0);
            $worksheetName = mb_substr(ucwords(str_replace('-', ' ', $baseName)), 0, 31);

            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
            echo ' xmlns:o="urn:schemas-microsoft-com:office:office"';
            echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
            echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles>';
            echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Arial" ss:Size="10"/></Style>';
            echo '<Style ss:ID="Title"><Font ss:Bold="1" ss:Size="16" ss:Color="#10233F"/></Style>';
            echo '<Style ss:ID="MetaLabel"><Font ss:Bold="1" ss:Color="#10233F"/></Style>';
            echo '<Style ss:ID="Header"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#10233F" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
            echo '<Style ss:ID="Center"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>';
            echo '<Style ss:ID="Data"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>';
            echo '<Style ss:ID="Money"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><NumberFormat ss:Format="&quot;Rp&quot;#,##0"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>';
            echo '<Style ss:ID="Summary"><Font ss:Bold="1"/><Interior ss:Color="#EEF2F7" ss:Pattern="Solid"/></Style>';
            echo '</Styles>';
            echo '<Worksheet ss:Name="'.$this->excelXmlEscape($worksheetName).'"><Table>';

            foreach ($headers as $header) {
                $width = match ($header) {
                    'No' => 48,
                    'Rute' => 180,
                    'Kode Pemesanan', 'Kode Transaksi' => 150,
                    'Penumpang' => 140,
                    default => 120,
                };
                echo '<Column ss:AutoFitWidth="0" ss:Width="'.$width.'"/>';
            }

            echo '<Row><Cell ss:StyleID="Title" ss:MergeAcross="'.($columnCount - 1).'"><Data ss:Type="String">RUSLY TRAVEL - '.$this->excelXmlEscape($title).'</Data></Cell></Row>';
            echo '<Row><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Periode</Data></Cell><Cell ss:MergeAcross="'.max($columnCount - 2, 0).'"><Data ss:Type="String">'.$this->excelXmlEscape($period).'</Data></Cell></Row>';
            echo '<Row><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Dicetak oleh</Data></Cell><Cell ss:MergeAcross="'.max($columnCount - 2, 0).'"><Data ss:Type="String">'.$this->excelXmlEscape($printedBy).'</Data></Cell></Row>';
            echo '<Row><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Tanggal cetak</Data></Cell><Cell ss:MergeAcross="'.max($columnCount - 2, 0).'"><Data ss:Type="String">'.$this->excelXmlEscape(now()->translatedFormat('d F Y H:i')).'</Data></Cell></Row>';
            echo '<Row/>';
            echo '<Row>';
            foreach ($headers as $header) {
                echo '<Cell ss:StyleID="Header"><Data ss:Type="String">'.$this->excelXmlEscape($header).'</Data></Cell>';
            }
            echo '</Row>';

            foreach (array_slice($rows, 1) as $row) {
                echo '<Row>';
                foreach ($row as $cellIndex => $cell) {
                    $header = $headers[$cellIndex] ?? '';
                    $isMoney = in_array($header, $moneyColumns, true);
                    $isNumber = $isMoney || in_array($header, ['No', 'Jumlah Kursi'], true);
                    $style = $isMoney ? 'Money' : (in_array($header, ['No', 'Jumlah Kursi'], true) ? 'Center' : 'Data');
                    $value = $isNumber && is_numeric($cell) ? (string) (int) $cell : (string) $cell;
                    $type = $isNumber && is_numeric($cell) ? 'Number' : 'String';
                    echo '<Cell ss:StyleID="'.$style.'"><Data ss:Type="'.$type.'">'.$this->excelXmlEscape($value).'</Data></Cell>';
                }
                echo '</Row>';
            }

            if (! empty($meta['summary'])) {
                $parts = [];
                foreach ($meta['summary'] as $label => $value) {
                    $parts[] = $label.': '.number_format((float) $value, 0, ',', '.');
                }
                echo '<Row/><Row><Cell ss:StyleID="Summary" ss:MergeAcross="'.($columnCount - 1).'"><Data ss:Type="String">'.$this->excelXmlEscape(implode(' | ', $parts)).'</Data></Cell></Row>';
            }

            echo '</Table>';
            // AutoFilter membuat dropdown filter Excel pada setiap kolom data.
            echo '<AutoFilter x:Range="R6C1:R'.$lastDataRow.'C'.$columnCount.'" xmlns="urn:schemas-microsoft-com:office:excel"/>';
            echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>6</SplitHorizontal><TopRowBottomPane>6</TopRowBottomPane><ActivePane>2</ActivePane></WorksheetOptions>';
            echo '</Worksheet></Workbook>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function excelXmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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
}
