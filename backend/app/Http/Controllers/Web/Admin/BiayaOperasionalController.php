<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BiayaOperasionalPerjalanan;
use App\Models\Jadwal;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BiayaOperasionalController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);

        // Rekap final hanya memakai biaya yang sudah disetujui admin.
        $rekapRecords = (clone $query)
            ->where('status_verifikasi', 'disetujui')
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

        $biaya = $query->latest('tanggal_biaya')->paginate(12)->withQueryString();
        $editingBiaya = $request->filled('edit')
            ? BiayaOperasionalPerjalanan::query()->findOrFail($request->integer('edit'))
            : null;

        return view('admin.biaya.index', [
            'biaya' => $biaya,
            'editingBiaya' => $editingBiaya,
            'jadwalOptions' => Jadwal::query()
                ->with('rute')
                ->orderByDesc('waktu_berangkat')
                ->limit(100)
                ->get(),
            'stats' => [
                'hari_ini' => (float) BiayaOperasionalPerjalanan::query()
                    ->where('status_verifikasi', 'disetujui')
                    ->whereDate('tanggal_biaya', today())
                    ->sum('nominal'),
                'bulan_ini' => (float) BiayaOperasionalPerjalanan::query()
                    ->where('status_verifikasi', 'disetujui')
                    ->whereMonth('tanggal_biaya', today()->month)
                    ->whereYear('tanggal_biaya', today()->year)
                    ->sum('nominal'),
                'total_item' => BiayaOperasionalPerjalanan::query()
                    ->where('status_verifikasi', 'disetujui')
                    ->count(),
            ],
            'filters' => $request->only(['search', 'jadwal_id', 'tanggal_mulai', 'tanggal_selesai']),
            'rekap' => $rekap,
        ]);
    }

    public function export(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        $format = in_array($format, ['csv', 'xls', 'xlsx'], true) ? $format : 'csv';

        // Export memakai filter yang sama dengan daftar biaya agar data yang diunduh sesuai tampilan admin.
        $records = $this->filteredQuery($request)
            ->latest('tanggal_biaya')
            ->get();
        $rows = $this->buildExportRows($records);
        $filename = 'biaya-operasional-'.now()->format('Ymd-His');

        return match ($format) {
            'xlsx' => $this->downloadXlsx($rows, "{$filename}.xlsx"),
            'xls' => $this->downloadHtmlExcel($rows, "{$filename}.xls"),
            default => $this->downloadCsv($rows, "{$filename}.csv"),
        };
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request);
        $payload['created_by'] = $request->user()->id;
        $payload['status_verifikasi'] = 'disetujui';
        $payload['diverifikasi_oleh'] = $request->user()->id;
        $payload['diverifikasi_pada'] = now();

        BiayaOperasionalPerjalanan::query()->create($payload);

        return redirect()
            ->route('admin.biaya.index')
            ->with('status', 'Biaya operasional berhasil dicatat.');
    }

    public function update(Request $request, BiayaOperasionalPerjalanan $biayaOperasional): RedirectResponse
    {
        $biayaOperasional->update($this->validatedPayload($request));

        return redirect()
            ->route('admin.biaya.index')
            ->with('status', 'Biaya operasional berhasil diperbarui.');
    }

    public function destroy(BiayaOperasionalPerjalanan $biayaOperasional): RedirectResponse
    {
        try {
            $biayaOperasional->delete();
        } catch (QueryException) {
            return back()->with('error', 'Biaya operasional tidak dapat dihapus.');
        }

        return back()->with('status', 'Biaya operasional berhasil dihapus.');
    }

    public function verify(Request $request, BiayaOperasionalPerjalanan $biayaOperasional): RedirectResponse
    {
        $validated = $request->validate([
            'status_verifikasi' => ['required', 'in:disetujui,ditolak'],
            'catatan_verifikasi' => ['nullable', 'string', 'max:1000'],
        ], [
            'status_verifikasi.required' => 'Status verifikasi wajib dipilih.',
            'status_verifikasi.in' => 'Status verifikasi tidak valid.',
        ]);

        $biayaOperasional->update([
            'status_verifikasi' => $validated['status_verifikasi'],
            'catatan_verifikasi' => $validated['catatan_verifikasi'] ?? null,
            'diverifikasi_oleh' => $request->user()->id,
            'diverifikasi_pada' => now(),
        ]);

        $message = $validated['status_verifikasi'] === 'disetujui'
            ? 'Pengajuan biaya sudah disetujui dan masuk rekap.'
            : 'Pengajuan biaya sudah ditolak.';

        return back()->with('status', $message);
    }

    public function rekap(): RedirectResponse
    {
        // Alamat lama tetap diarahkan agar bookmark admin tidak berakhir di halaman 404.
        return redirect()->route('admin.biaya.index');
    }

    private function filteredQuery(Request $request)
    {
        $query = BiayaOperasionalPerjalanan::query()->with(['jadwal.rute', 'pencatat']);

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

        if ($request->filled('jadwal_id')) {
            $query->where('jadwal_id', $request->integer('jadwal_id'));
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
            'Status',
            'Pencatat',
            'Keterangan',
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
                'Rp'.number_format((float) $item->nominal, 0, ',', '.'),
                ucwords(str_replace('_', ' ', $item->status_verifikasi ?: 'disetujui')),
                $item->pencatat?->nama ?? '-',
                $item->keterangan ?: '-',
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
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadHtmlExcel(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<h2>Laporan Biaya Operasional</h2>';
            echo '<p>Dicetak pada '.e(now()->translatedFormat('d F Y H:i')).'</p>';
            echo '<table border="1" cellspacing="0" cellpadding="8" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:12px;">';

            foreach ($rows as $rowIndex => $row) {
                echo '<tr>';

                foreach ($row as $cellIndex => $cell) {
                    $tag = $rowIndex === 0 ? 'th' : 'td';
                    $style = $rowIndex === 0
                        ? ' style="font-weight:bold;background:#10233f;color:#ffffff;text-align:center;"'
                        : ' style="text-align:'.($cellIndex === 0 ? 'center' : 'left').';"';
                    echo "<{$tag}{$style}>".e((string) $cell)."</{$tag}>";
                }

                echo '</tr>';
            }

            echo '</table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function downloadXlsx(array $rows, string $filename)
    {
        if (! class_exists(\ZipArchive::class)) {
            return $this->downloadHtmlExcel($rows, str_replace('.xlsx', '.xls', $filename));
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'travs-xlsx-');
        $zip = new \ZipArchive();

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

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'jadwal_id' => ['required', 'exists:jadwal,id'],
            'jenis_biaya' => ['required', 'string', 'max:100'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'tanggal_biaya' => ['required', 'date'],
        ], [
            'jadwal_id.required' => 'Jadwal terkait wajib dipilih.',
            'jenis_biaya.required' => 'Jenis biaya wajib diisi.',
            'nominal.required' => 'Nominal biaya wajib diisi.',
            'nominal.min' => 'Nominal biaya tidak boleh negatif.',
            'tanggal_biaya.required' => 'Tanggal biaya wajib diisi.',
        ]);
    }
}
