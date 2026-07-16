<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BiayaOperasionalPerjalanan;
use App\Models\Jadwal;
use App\Models\Rute;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BiayaOperasionalController extends Controller
{
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
            'rekap' => $rekap,
        ]);
    }

    public function create(): View
    {
        return view('admin.biaya.create', [
            'jadwalOptions' => $this->scheduleOptions(),
        ]);
    }

    public function edit(BiayaOperasionalPerjalanan $biayaOperasional): View
    {
        return view('admin.biaya.edit', [
            'biaya' => $biayaOperasional,
            'jadwalOptions' => $this->scheduleOptions(),
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
        unset($payload['bukti_nota']);
        $payload['created_by'] = $request->user()->id;
        $payload['bukti_url'] = $request->file('bukti_nota')->store('biaya-operasional', 'public');
        // Status internal tetap diisi agar query lama tetap kompatibel, tetapi
        // pencatatan biaya tidak lagi melalui proses persetujuan.
        $payload['status_verifikasi'] = 'disetujui';

        BiayaOperasionalPerjalanan::query()->create($payload);

        return redirect()
            ->route('admin.biaya.index')
            ->with('status', 'Biaya operasional berhasil dicatat.');
    }

    public function update(Request $request, BiayaOperasionalPerjalanan $biayaOperasional): RedirectResponse
    {
        $payload = $this->validatedPayload($request, $biayaOperasional);
        unset($payload['bukti_nota']);
        $oldProofPath = $biayaOperasional->bukti_url;

        if ($request->hasFile('bukti_nota')) {
            $payload['bukti_url'] = $request->file('bukti_nota')->store('biaya-operasional', 'public');
        }

        $biayaOperasional->update($payload);

        if (isset($payload['bukti_url']) && $oldProofPath && $oldProofPath !== $payload['bukti_url']) {
            Storage::disk('public')->delete($oldProofPath);
        }

        return redirect()
            ->route('admin.biaya.index')
            ->with('status', 'Biaya operasional berhasil diperbarui.');
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

    public function verify(Request $request, BiayaOperasionalPerjalanan $biayaOperasional): RedirectResponse
    {
        // Route lama dipertahankan agar tautan lama tidak error, tetapi proses
        // setujui/tolak sudah tidak digunakan pada pencatatan biaya saat ini.
        return back()->with('status', 'Pencatatan biaya operasional tidak memerlukan verifikasi.');
    }

    public function rekap(): RedirectResponse
    {
        // Alamat lama tetap diarahkan agar bookmark admin tidak berakhir di halaman 404.
        return redirect()->route('admin.biaya.index');
    }

    private function filteredQuery(Request $request)
    {
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
    ): array
    {
        // Bukti wajib untuk pencatatan baru. Saat edit, bukti lama boleh tetap
        // dipakai agar admin tidak perlu mengunggah ulang file yang sama.
        $proofPresenceRule = $biayaOperasional?->bukti_url ? 'nullable' : 'required';

        return $request->validate([
            'jadwal_id' => ['required', 'exists:jadwal,id'],
            'jenis_biaya' => ['required', 'string', 'max:100'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['required', 'string', 'min:10', 'max:1000'],
            'tanggal_biaya' => ['required', 'date'],
            'bukti_nota' => [$proofPresenceRule, 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], [
            'jadwal_id.required' => 'Jadwal terkait wajib dipilih.',
            'jenis_biaya.required' => 'Jenis biaya wajib diisi.',
            'nominal.required' => 'Nominal biaya wajib diisi.',
            'nominal.min' => 'Nominal biaya minimal Rp1.',
            'keterangan.required' => 'Keterangan biaya wajib diisi.',
            'keterangan.min' => 'Keterangan biaya minimal 10 karakter agar mudah diperiksa.',
            'tanggal_biaya.required' => 'Tanggal biaya wajib diisi.',
            'bukti_nota.required' => 'Foto bukti nota wajib dilampirkan.',
            'bukti_nota.image' => 'Bukti nota harus berupa gambar.',
            'bukti_nota.mimes' => 'Bukti nota harus berformat JPG, JPEG, PNG, atau WEBP.',
            'bukti_nota.max' => 'Ukuran bukti nota maksimal 3 MB.',
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
