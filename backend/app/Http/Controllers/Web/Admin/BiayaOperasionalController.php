<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BiayaOperasionalPerjalanan;
use App\Models\Jadwal;
use App\Models\Rute;
use App\Services\OperationalExpenseCategoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BiayaOperasionalController extends Controller
{
    public function __construct(
        private readonly OperationalExpenseCategoryService $expenseCategoryService,
    ) {}

    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);

        // Semua pencatatan baru langsung final; status lama hanya dipertahankan
        // sebagai kompatibilitas database dan tidak menjadi alur kerja admin.
        $rekapRecords = (clone $query)
            ->orderByDesc('tanggal_biaya')
            ->get();
        $rekap = $rekapRecords
            ->groupBy(fn (BiayaOperasionalPerjalanan $item) => optional($item->jadwal?->rute)->kota_asal.' - '.optional($item->jadwal?->rute)->kota_tujuan)
            ->map(function (Collection $items, string $routeLabel) {
                return [
                    'route' => $routeLabel !== ' - ' ? $routeLabel : 'Tanpa rute',
                    'total' => (float) $items->sum('nominal'),
                    'count' => $items->count(),
                    'latest_date' => optional($items->max('tanggal_biaya'))->format('Y-m-d H:i:s'),
                ];
            })
            ->values();
        $highestCostRoute = $rekap->sortByDesc('total')->first();

        $biaya = $query->latest('tanggal_biaya')->paginate(12)->withQueryString();

        return view('admin.biaya.index', [
            'biaya' => $biaya,
            'routeOptions' => Rute::query()
                ->whereHas('jadwal')
                ->orderBy('kota_asal')
                ->orderBy('kota_tujuan')
                ->get(),
            'stats' => [
                'hari_ini' => (float) BiayaOperasionalPerjalanan::query()
                    ->finalized()
                    ->whereDate('tanggal_biaya', today())
                    ->sum('nominal'),
                'bulan_ini' => (float) BiayaOperasionalPerjalanan::query()
                    ->finalized()
                    ->whereMonth('tanggal_biaya', today()->month)
                    ->whereYear('tanggal_biaya', today()->year)
                    ->sum('nominal'),
                'total_item' => BiayaOperasionalPerjalanan::query()
                    ->finalized()
                    ->count(),
            ],
            'filters' => $request->only(['search', 'rute_id', 'tanggal_mulai', 'tanggal_selesai']),
            'expenseCategories' => $this->expenseCategoryService->categories(),
            'rekap' => $rekap,
            'periodSummary' => [
                'total_biaya' => (int) $rekapRecords->sum('nominal'),
                'jumlah_transaksi' => $rekapRecords->count(),
                'rute_tertinggi' => $highestCostRoute['route'] ?? '—',
                'rute_tertinggi_total' => (int) ($highestCostRoute['total'] ?? 0),
                'periode_label' => $this->periodLabel($request),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.biaya.create', [
            'jadwalOptions' => $this->scheduleOptions(),
            'expenseCategories' => $this->expenseCategoryService->categories(),
        ]);
    }

    public function edit(BiayaOperasionalPerjalanan $biayaOperasional): View
    {
        return view('admin.biaya.edit', [
            'biaya' => $biayaOperasional,
            'jadwalOptions' => $this->scheduleOptions(),
            'expenseCategories' => $this->expenseCategoryService
                ->categoriesForEdit($biayaOperasional->jenis_biaya),
        ]);
    }

    public function export(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        $format = in_array($format, ['csv', 'xls'], true) ? $format : 'csv';

        // Export memakai filter yang sama dengan daftar biaya agar data yang diunduh sesuai tampilan admin.
        $records = $this->filteredQuery($request)
            ->latest('tanggal_biaya')
            ->get();
        $rows = $this->buildExportRows($records);
        $filename = 'biaya-operasional-'.now()->format('Ymd-His');

        return match ($format) {
            'xls' => $this->downloadHtmlExcel(
                $rows,
                "{$filename}.xls",
                $request,
                $records->count(),
                (float) $records->sum('nominal'),
            ),
            default => $this->downloadCsv($rows, "{$filename}.csv"),
        };
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request);
        $proofFile = $payload['foto_bukti_nota'];
        unset($payload['foto_bukti_nota']);

        // Bukti disimpan sebelum data dibuat dan dibersihkan kembali apabila
        // penyimpanan database gagal agar tidak meninggalkan file yatim.
        $proofPath = $proofFile->store('biaya-operasional', 'public');
        $payload['created_by'] = $request->user()->id;
        $payload['bukti_url'] = $proofPath;
        try {
            BiayaOperasionalPerjalanan::query()->create($payload);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($proofPath);

            throw $exception;
        }

        return redirect()
            ->route('admin.biaya.index')
            ->with('status', 'Biaya operasional berhasil dicatat.');
    }

    public function update(Request $request, BiayaOperasionalPerjalanan $biayaOperasional): RedirectResponse
    {
        $payload = $this->validatedPayload($request, $biayaOperasional);
        $proofFile = $payload['foto_bukti_nota'] ?? null;
        unset($payload['foto_bukti_nota']);

        $oldProofPath = $biayaOperasional->bukti_url;
        $newProofPath = $proofFile?->store('biaya-operasional', 'public');
        if ($newProofPath) {
            $payload['bukti_url'] = $newProofPath;
        }

        try {
            $biayaOperasional->update($payload);
        } catch (Throwable $exception) {
            if ($newProofPath) {
                Storage::disk('public')->delete($newProofPath);
            }

            throw $exception;
        }

        if ($newProofPath && $oldProofPath && $oldProofPath !== $newProofPath) {
            Storage::disk('public')->delete($oldProofPath);
        }

        return redirect()
            ->route('admin.biaya.index')
            ->with('status', 'Biaya operasional berhasil diperbarui.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:60'],
        ], [
            'nama_kategori.required' => 'Nama kategori pengeluaran wajib diisi.',
            'nama_kategori.max' => 'Nama kategori pengeluaran maksimal 60 karakter.',
        ]);

        $this->expenseCategoryService->add(
            $validated['nama_kategori'],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.biaya.index')
            ->with('status', 'Kategori pengeluaran berhasil ditambahkan.');
    }

    public function destroyCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:60'],
        ]);

        $this->expenseCategoryService->remove(
            $validated['nama_kategori'],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.biaya.index')
            ->with('status', 'Kategori pengeluaran berhasil dihapus dari pilihan input.');
    }

    public function destroy(BiayaOperasionalPerjalanan $biayaOperasional): RedirectResponse
    {
        $proofPath = $biayaOperasional->bukti_url;

        try {
            $biayaOperasional->delete();
        } catch (QueryException) {
            return back()->with('error', 'Biaya operasional tidak dapat dihapus.');
        }

        if ($proofPath) {
            Storage::disk('public')->delete($proofPath);
        }

        return back()->with('status', 'Biaya operasional berhasil dihapus.');
    }

    public function rekap(): RedirectResponse
    {
        // Alamat lama tetap diarahkan agar bookmark admin tidak berakhir di halaman 404.
        return redirect()->route('admin.biaya.index');
    }

    private function filteredQuery(Request $request)
    {
        $tanggalSelesaiRules = ['nullable', 'date'];
        if ($request->filled('tanggal_mulai')) {
            $tanggalSelesaiRules[] = 'after_or_equal:tanggal_mulai';
        }

        // Filter divalidasi sebelum tanggal dibaca agar URL rusak tidak memicu error server.
        $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'rute_id' => ['nullable', 'integer', Rule::exists('rute', 'id')],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => $tanggalSelesaiRules,
        ], [
            'tanggal_mulai.date' => 'Tanggal mulai tidak valid.',
            'tanggal_selesai.date' => 'Tanggal selesai tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ]);

        $query = BiayaOperasionalPerjalanan::query()
            ->finalized()
            ->with(['jadwal.rute', 'pencatat']);

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('jenis_biaya', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%")
                    ->orWhereHas('jadwal.rute', function ($ruteQuery) use ($search) {
                        $ruteQuery
                            ->where('kota_asal', 'like', "%{$search}%")
                            ->orWhere('kota_tujuan', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('rute_id')) {
            $query->whereHas('jadwal', function ($jadwalQuery) use ($request) {
                $jadwalQuery->where('rute_id', $request->integer('rute_id'));
            });
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal_biaya', '>=', $request->date('tanggal_mulai'));
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('tanggal_biaya', '<=', $request->date('tanggal_selesai'));
        }

        return $query;
    }

    private function buildExportRows(Collection $records): array
    {
        $rows = [[
            'No',
            'Tanggal Biaya',
            'Rute',
            'Jenis Biaya',
            'Nominal',
            'Pencatat',
            'Keterangan',
            'URL Bukti',
        ]];

        foreach ($records as $index => $item) {
            $route = $item->jadwal?->rute
                ? $item->jadwal->rute->kota_asal.' - '.$item->jadwal->rute->kota_tujuan
                : 'Tanpa rute';

            $rows[] = [
                (string) ($index + 1),
                $item->tanggal_biaya?->format('d/m/Y H:i') ?? '-',
                $route,
                $item->jenis_biaya,
                (float) $item->nominal,
                $item->pencatat?->nama ?? '-',
                $item->keterangan ?: '-',
                $item->bukti_url
                    ? url(Storage::disk('public')->url($item->bukti_url))
                    : '-',
            ];
        }

        return $rows;
    }

    private function downloadCsv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            echo "\xEF\xBB\xBF";

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadHtmlExcel(
        array $rows,
        string $filename,
        ?Request $request = null,
        int $recordCount = 0,
        float $totalCost = 0,
    ): StreamedResponse {
        return response()->streamDownload(function () use ($rows, $request, $recordCount, $totalCost) {
            $headers = $rows[0] ?? [];
            $printedBy = $request?->user()?->nama ?? 'Admin';
            $period = $request ? $this->periodLabel($request) : 'Semua periode';
            $recordCount = $recordCount > 0 ? $recordCount : max(count($rows) - 1, 0);

            echo '<html><head><meta charset="UTF-8">';
            echo '<style>
                body{font-family:Arial,sans-serif;color:#10233f;font-size:12px}
                .brand{font-size:18px;font-weight:bold;text-align:center}
                .title{font-size:16px;font-weight:bold;text-align:center;margin-top:4px}
                .meta{margin:14px 0 18px;line-height:1.6}
                table{border-collapse:collapse;width:100%;table-layout:fixed}
                th,td{border:1px solid #94a3b8;padding:8px;vertical-align:top;white-space:normal;word-wrap:break-word}
                th{background:#10233f;color:#fff;font-weight:bold;text-align:center}
                .center{text-align:center}
                .right{text-align:right}
                .summary{background:#e8eef5;font-weight:bold}
            </style></head><body>';
            echo '<div class="brand">RUSLY TRAVEL</div>';
            echo '<div class="title">LAPORAN BIAYA OPERASIONAL</div>';
            echo '<div class="meta">';
            echo 'Periode: '.e($period).'<br>';
            echo 'Dicetak oleh: '.e($printedBy).'<br>';
            echo 'Tanggal cetak: '.e(now()->translatedFormat('d F Y H:i'));
            echo '</div>';
            echo '<table><colgroup>';

            foreach ($headers as $header) {
                $width = match ($header) {
                    'No' => 48,
                    'Tanggal Biaya' => 125,
                    'Rute' => 180,
                    'Jenis Biaya' => 150,
                    'Nominal' => 110,
                    'Pencatat' => 140,
                    default => 220,
                };
                echo '<col style="width:'.$width.'px">';
            }

            echo '</colgroup>';

            foreach ($rows as $rowIndex => $row) {
                echo '<tr>';

                foreach ($row as $cellIndex => $cell) {
                    if ($rowIndex === 0) {
                        echo '<th>'.e((string) $cell).'</th>';

                        continue;
                    }

                    $header = $headers[$cellIndex] ?? '';
                    $class = $header === 'No' ? 'center' : ($header === 'Nominal' ? 'right' : '');
                    $displayValue = $header === 'Nominal'
                        ? $this->formatRupiah((float) $cell)
                        : (string) $cell;
                    echo '<td class="'.$class.'">'.e($displayValue).'</td>';
                }

                echo '</tr>';
            }

            echo '<tr class="summary">';
            echo '<td colspan="4" class="right">TOTAL BIAYA</td>';
            echo '<td class="right">'.e($this->formatRupiah($totalCost)).'</td>';
            echo '<td colspan="2">Jumlah data: '.e((string) $recordCount).'</td>';
            echo '</tr>';
            echo '</table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function periodLabel(Request $request): string
    {
        $startDate = $request->date('tanggal_mulai');
        $endDate = $request->date('tanggal_selesai');

        if ($startDate && $endDate) {
            return $startDate->translatedFormat('d F Y').' sampai '.$endDate->translatedFormat('d F Y');
        }

        if ($startDate) {
            return 'Mulai '.$startDate->translatedFormat('d F Y');
        }

        if ($endDate) {
            return 'Sampai '.$endDate->translatedFormat('d F Y');
        }

        return 'Semua periode';
    }

    private function formatRupiah(float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }

    private function downloadXlsx(array $rows, string $filename)
    {
        if (! class_exists(\ZipArchive::class)) {
            return $this->downloadHtmlExcel($rows, str_replace('.xlsx', '.xls', $filename));
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'travs-xlsx-');
        $zip = new \ZipArchive;

        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tempPath);

            return $this->downloadHtmlExcel($rows, str_replace('.xlsx', '.xls', $filename));
        }

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRelations());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelations());
        $zip->addFromString('xl/styles.xml', $this->xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxSheet($rows));
        $zip->close();

        $content = file_get_contents($tempPath) ?: '';
        @unlink($tempPath);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function xlsxSheet(array $rows): string
    {
        $xmlRows = '';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xmlRows .= '<row r="'.$rowNumber.'">';

            foreach ($row as $columnIndex => $cell) {
                $cellRef = $this->xlsxColumnName($columnIndex + 1).$rowNumber;
                $value = htmlspecialchars((string) $cell, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $style = $rowIndex === 0 ? ' s="1"' : ($columnIndex === 0 ? ' s="2"' : ' s="3"');
                $xmlRows .= '<c r="'.$cellRef.'"'.$style.' t="inlineStr"><is><t>'.$value.'</t></is></c>';
            }

            $xmlRows .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$xmlRows.'</sheetData>'
            .'</worksheet>';
    }

    private function xlsxColumnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function xlsxRootRelations(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Biaya Operasional" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function xlsxWorkbookRelations(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><color rgb="FF10233F"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF10233F"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function validatedPayload(
        Request $request,
        ?BiayaOperasionalPerjalanan $biayaOperasional = null,
    ): array {
        $allowedCategories = $biayaOperasional
            ? $this->expenseCategoryService->categoriesForEdit($biayaOperasional->jenis_biaya)
            : $this->expenseCategoryService->categories();

        return $request->validate([
            'jadwal_id' => ['required', 'exists:jadwal,id'],
            'jenis_biaya' => ['required', 'string', 'max:60', Rule::in($allowedCategories)],
            'nominal' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['required', 'string', 'min:10', 'max:1000'],
            'tanggal_biaya' => ['required', 'date'],
            'foto_bukti_nota' => [
                $biayaOperasional?->bukti_url ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:3072',
            ],
        ], [
            'jadwal_id.required' => 'Jadwal terkait wajib dipilih.',
            'jenis_biaya.required' => 'Jenis biaya wajib diisi.',
            'jenis_biaya.in' => 'Kategori pengeluaran tidak tersedia. Pilih kategori yang dikelola admin.',
            'nominal.required' => 'Nominal biaya wajib diisi.',
            'nominal.min' => 'Nominal biaya minimal Rp1.',
            'keterangan.required' => 'Keterangan biaya wajib diisi.',
            'keterangan.min' => 'Keterangan biaya minimal 10 karakter agar mudah diperiksa.',
            'tanggal_biaya.required' => 'Tanggal biaya wajib diisi.',
            'foto_bukti_nota.required' => 'Foto bukti/nota wajib diunggah.',
            'foto_bukti_nota.image' => 'Bukti pengeluaran harus berupa gambar.',
            'foto_bukti_nota.mimes' => 'Format bukti harus JPG, JPEG, PNG, atau WebP.',
            'foto_bukti_nota.max' => 'Ukuran foto bukti maksimal 3 MB.',
        ]);
    }

    private function scheduleOptions(): Collection
    {
        return Jadwal::query()
            ->with('rute')
            ->orderByDesc('waktu_berangkat')
            ->limit(100)
            ->get();
    }
}
