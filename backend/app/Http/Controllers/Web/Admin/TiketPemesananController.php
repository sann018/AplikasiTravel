<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pemesanan;
use App\Models\TiketElektronik;
use Illuminate\Http\Response;

class TiketPemesananController extends Controller
{
    public function show(Pemesanan $pemesanan, TiketElektronik $tiketElektronik): Response
    {
        return $this->ticketView($pemesanan, $tiketElektronik, false);
    }

    public function download(Pemesanan $pemesanan, TiketElektronik $tiketElektronik): Response
    {
        return $this->ticketView($pemesanan, $tiketElektronik, true);
    }

    private function ticketView(
        Pemesanan $pemesanan,
        TiketElektronik $tiketElektronik,
        bool $autoDownload,
    ): Response {
        $tiketElektronik->load('detailPemesanan');

        // ID tiket selalu dicocokkan dengan pemesanan pada URL agar admin
        // tidak keliru membuka tiket milik pemesanan lain.
        abort_unless(
            (int) $tiketElektronik->detailPemesanan?->pemesanan_id === (int) $pemesanan->id,
            404,
        );

        $pemesanan->load([
            'jadwal.rute',
            'jadwal.kendaraan',
            'jadwal.pengemudi.pengguna',
        ]);

        $detail = $tiketElektronik->detailPemesanan;
        $jadwal = $pemesanan->jadwal;
        $rute = $jadwal?->rute;
        $kendaraan = $jadwal?->kendaraan;
        $pengemudi = $jadwal?->pengemudi?->pengguna;
        $routeLabel = trim(($rute?->kota_asal ?? '—').' - '.($rute?->kota_tujuan ?? '—'));
        $departure = $jadwal?->waktu_berangkat;
        $safeBookingCode = preg_replace('/[^A-Za-z0-9_-]+/', '-', $pemesanan->kode_booking) ?: 'pemesanan';
        $safeSeat = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $detail->nomor_kursi) ?: 'kursi';

        $downloadPayload = [
            'qr_token' => $tiketElektronik->token_unik_qr,
            'kode_tiket' => $tiketElektronik->kode_qr,
            'kode_pemesanan' => $pemesanan->kode_booking,
            'rute' => $routeLabel,
            'tanggal' => $departure?->translatedFormat('d F Y') ?? '—',
            'waktu' => $departure?->format('H:i') ?? '—',
            'penumpang' => $detail->nama_penumpang,
            'nomor_kursi' => (string) $detail->nomor_kursi,
            'kendaraan' => $kendaraan?->merk_kendaraan ?: '—',
            'nomor_plat' => $kendaraan?->nomor_plat ?: '—',
            'pengemudi' => $pengemudi?->nama ?: '—',
            'status_pindai' => $tiketElektronik->sudah_dipindai ? 'Sudah dipindai' : 'Belum dipindai',
            'nama_berkas' => "tiket-{$safeBookingCode}-kursi-{$safeSeat}.png",
        ];

        // Token QR tidak disimpan pada cache browser atau proxy karena hanya
        // diperlukan admin saat melihat dan mengunduh tiket ini.
        return response()
            ->view('admin.pemesanan.tiket', [
                'pemesanan' => $pemesanan,
                'detail' => $detail,
                'tiket' => $tiketElektronik,
                'downloadPayload' => $downloadPayload,
                'autoDownload' => $autoDownload,
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
