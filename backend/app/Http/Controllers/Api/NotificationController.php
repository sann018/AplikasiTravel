<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller untuk endpoint notifikasi mobile.
 *
 * KONSEP UTAMA:
 * Aplikasi Flutter melakukan "polling" — yaitu menanyakan ke server secara berkala
 * (setiap 30 detik) apakah ada notifikasi baru. Ini berbeda dengan "push" (FCM)
 * di mana server yang aktif mengirim ke HP. Polling lebih sederhana karena tidak
 * butuh layanan eksternal seperti Firebase.
 *
 * ALUR KERJA:
 * 1. User login di mobile → Flutter mulai timer polling setiap 30 detik
 * 2. Setiap 30 detik, Flutter panggil GET /api/v1/notifications
 * 3. Backend cek tabel `notifikasi` milik user tersebut
 * 4. Jika ada notif baru → Flutter tampilkan sebagai notifikasi sistem Android
 * 5. User buka halaman notifikasi → Flutter panggil POST /read-all
 *
 * KEAMANAN:
 * - Setiap endpoint memerlukan auth:sanctum (token login)
 * - User hanya bisa melihat notifikasi miliknya sendiri (filter pengguna_id)
 * - Rate limit `travs-read` mencegah spam request berlebihan
 */
class NotificationController extends Controller
{
    /**
     * Mengembalikan daftar notifikasi user yang sedang login.
     *
     * PARAMETER OPTIONAL:
     * - `since_id`: ID notifikasi terakhir yang sudah diterima mobile.
     *   Jika dikirim, backend hanya mengembalikan notifikasi dengan ID lebih besar.
     *   Ini menghemat bandwidth karena mobile tidak perlu download ulang notif lama.
     * - `limit`: Jumlah maksimal notifikasi yang dikembalikan (default 20, max 50).
     *
     * RESPONSE:
     * - `items`: Array notifikasi terbaru (urut dari yang paling baru)
     * - `unread_count`: Jumlah notifikasi yang belum dibaca (untuk badge angka di icon lonceng)
     */
    public function index(Request $request): JsonResponse
    {
        // Validasi input dari mobile — Laravel otomatis menolak jika format salah.
        // Ini penting agar user tidak bisa mengirim data aneh yang merusak query.
        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 20);

        // Ambil user yang sedang login dari token Sanctum.
        // Laravel Sanctum otomatis memverifikasi token di header Authorization.
        $user = $request->user();

        // Query notifikasi milik user ini saja, urut dari yang terbaru.
        // `pengguna_id` memastikan user A tidak bisa lihat notifikasi user B.
        $query = Notifikasi::query()
            ->where('pengguna_id', $user->id)
            ->orderByDesc('id');

        // Filter since_id: hanya ambil notifikasi yang lebih baru dari ID tertentu.
        // Mobile menyimpan ID terakhir yang sudah diterima, lalu kirim ke sini
        // agar tidak download ulang notifikasi yang sudah ada di lokal.
        if ($sinceId > 0) {
            $query->where('id', '>', $sinceId);
        }

        $notifikasi = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => [
                // Map setiap model Notifikasi ke format JSON yang konsisten.
                'items' => $notifikasi->map(fn (Notifikasi $row) => $this->formatNotifikasi($row))->all(),
                // Hitung total notifikasi belum dibaca — dipakai untuk badge angka.
                'unread_count' => Notifikasi::query()
                    ->where('pengguna_id', $user->id)
                    ->where('sudah_dibaca', false)
                    ->count(),
            ],
        ]);
    }

    /**
     * Endpoint ringan khusus untuk polling badge unread di header app.
     *
     * KENAPA DIPISAH DARI INDEX:
     * Endpoint ini hanya mengembalikan angka (integer), bukan daftar notifikasi.
     * Ini jauh lebih ringan dan cepat — cocok untuk dipanggil lebih sering
     * (misalnya setiap 15 detik) tanpa membebani server.
     *
     * DIPAKAI UNTUK:
     * Badge angka merah di icon lonceng pada header aplikasi mobile.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notifikasi::query()
            ->where('pengguna_id', $request->user()->id)
            ->where('sudah_dibaca', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * Menandai satu notifikasi sebagai sudah dibaca.
     *
     * KEAMANAN:
     * Memakai route model binding (`Notifikasi $notifikasi`) — Laravel otomatis
     * mencari notifikasi berdasarkan ID di URL. Jika tidak ditemukan, otomatis 404.
     * Setelah itu kita cek apakah notifikasi tersebut milik user yang login.
     * Ini mencegah user A menandai notifikasi user B sebagai dibaca.
     *
     * DIPAKAI SAAT:
     * User tap/klik salah satu item notifikasi di halaman daftar notifikasi.
     */
    public function markAsRead(Request $request, Notifikasi $notifikasi): JsonResponse
    {
        // Cek kepemilikan: notifikasi ini harus milik user yang sedang login.
        // Tanpa pengecekan ini, user bisa manipulasi ID di URL untuk akses data orang lain.
        if ($notifikasi->pengguna_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan.',
            ], 404);
        }

        // forceFill dipakai karena `sudah_dibaca` mungkin tidak ada di $fillable.
        // Ini aman karena kita yang mengontrol value-nya (selalu true).
        $notifikasi->forceFill(['sudah_dibaca' => true])->save();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'data' => $this->formatNotifikasi($notifikasi),
        ]);
    }

    /**
     * Menandai seluruh notifikasi user sebagai sudah dibaca (bulk update).
     *
     * DIPAKAI SAAT:
     * User membuka halaman daftar notifikasi — semua dianggap sudah dilihat.
     * Ini lebih efisien daripada memanggil markAsRead satu per satu.
     *
     * RESPONSE:
     * - `affected`: Jumlah notifikasi yang berubah status. Berguna untuk debugging.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        // Update langsung di database tanpa load model satu per satu.
        // Ini jauh lebih cepat daripada loop + save() untuk setiap notifikasi.
        $affected = Notifikasi::query()
            ->where('pengguna_id', $request->user()->id)
            ->where('sudah_dibaca', false)
            ->update(['sudah_dibaca' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
            'data' => [
                'affected' => $affected,
            ],
        ]);
    }

    /**
     * Format model Notifikasi menjadi array JSON yang konsisten.
     *
     * KENAPA PAKAI METHOD TERPISAH:
     * Agar format response selalu sama di semua endpoint (index, markAsRead).
     * Jika nanti perlu tambah field, cukup ubah di satu tempat ini saja.
     *
     * @return array<string, mixed>
     */
    private function formatNotifikasi(Notifikasi $notifikasi): array
    {
        return [
            'id' => $notifikasi->id,
            'judul' => $notifikasi->judul,
            'pesan' => $notifikasi->pesan,
            'jenis' => $notifikasi->jenis,                          // 'pembayaran', 'perjalanan', atau 'sistem'
            'referensi_id' => $notifikasi->referensi_id,            // ID pemesanan/jadwal terkait (nullable)
            'sudah_dibaca' => (bool) $notifikasi->sudah_dibaca,     // Cast ke boolean agar JSON konsisten
            'created_at' => $notifikasi->created_at?->toIso8601String(), // Format ISO 8601 agar Flutter mudah parse
        ];
    }
}
