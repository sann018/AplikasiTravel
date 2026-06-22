<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Services\NotificationHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Menyediakan data notifikasi untuk aplikasi mobile.
 *
 * Flutter mengecek notifikasi secara berkala. Setiap query selalu dibatasi
 * dengan ID pengguna supaya satu akun tidak bisa membaca notifikasi akun lain.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationHistoryService $notificationHistoryService)
    {
    }

    /**
     * Mengambil notifikasi milik pengguna yang sedang login.
     * `since_id` mencegah data lama dikirim ulang, sedangkan `limit` membatasi
     * jumlah hasil agar balasan API tetap ringan.
     */
    public function index(Request $request): JsonResponse
    {
        // Parameter dari mobile divalidasi dulu agar nilai yang tidak sesuai
        // tidak ikut masuk ke proses pencarian database.
        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'mark_as_read' => ['nullable', 'boolean'],
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 20);

        // Pengguna diambil dari token yang sudah diverifikasi oleh Sanctum.
        $user = $request->user();

        // Filter berdasarkan pengguna mencegah notifikasi akun lain ikut terbaca.
        // Hasil diurutkan dari yang terbaru agar informasi penting tampil lebih dulu.
        $query = Notifikasi::query()
            ->where('pengguna_id', $user->id)
            ->orderByDesc('id');

        // Jika mobile mengirim ID terakhir, backend cukup mengambil notifikasi
        // yang lebih baru agar data lama tidak dikirim berulang kali.
        if ($sinceId > 0) {
            $query->where('id', '>', $sinceId);
        }

        $notifikasi = $query->limit($limit)->get();

        // Status baca hanya berubah saat halaman notifikasi benar-benar dibuka.
        // Permintaan polling tanpa parameter ini tetap aman dan tidak mengurangi badge.
        if ((bool) ($validated['mark_as_read'] ?? false)) {
            $this->notificationHistoryService->markDisplayedAsRead($user->id, $notifikasi);
            $notifikasi->each->forceFill(['sudah_dibaca' => true]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                // Setiap notifikasi dibentuk dengan struktur JSON yang sama untuk mobile.
                'items' => $notifikasi->map(fn (Notifikasi $row) => $this->formatNotifikasi($row))->all(),
                // Jumlah belum dibaca dipakai sebagai angka pada ikon lonceng.
                'unread_count' => Notifikasi::query()
                    ->where('pengguna_id', $user->id)
                    ->where('sudah_dibaca', false)
                    ->count(),
            ],
        ]);
    }

    /**
     * Mengambil jumlah notifikasi yang belum dibaca untuk angka pada ikon lonceng.
     * Endpoint ini dipisah karena hanya membutuhkan satu angka, bukan seluruh daftar.
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
     * Menandai satu notifikasi sebagai sudah dibaca setelah kepemilikannya dicek.
     */
    public function markAsRead(Request $request, Notifikasi $notifikasi): JsonResponse
    {
        // Kepemilikan wajib dicek agar perubahan ID pada URL tidak memberi
        // akses ke notifikasi milik akun lain.
        if ($notifikasi->pengguna_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan.',
            ], 404);
        }

        // Nilai `sudah_dibaca` dikendalikan backend dan selalu diisi true,
        // jadi pengguna tidak bisa menentukan nilainya sendiri dari permintaan.
        $notifikasi->forceFill(['sudah_dibaca' => true])->save();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'data' => $this->formatNotifikasi($notifikasi),
        ]);
    }

    /**
     * Menandai semua notifikasi pengguna sebagai sudah dibaca lewat satu query.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        // Semua notifikasi diperbarui lewat satu query agar lebih ringan
        // dibandingkan menyimpan setiap baris satu per satu.
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
     * Menghapus riwayat terbaca milik pengguna tanpa menyentuh notifikasi baru.
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(['7_days', '30_days', '90_days', 'all_read'])],
        ]);

        $userId = $request->user()->id;
        $deletedCount = $this->notificationHistoryService->deleteReadHistory(
            $validated['period'],
            $userId,
        );

        return response()->json([
            'success' => true,
            'message' => $deletedCount > 0
                ? 'Riwayat notifikasi berhasil dibersihkan.'
                : 'Tidak ada riwayat notifikasi yang perlu dibersihkan.',
            'data' => [
                'deleted_count' => $deletedCount,
                'unread_count' => Notifikasi::query()
                    ->where('pengguna_id', $userId)
                    ->where('sudah_dibaca', false)
                    ->count(),
            ],
        ]);
    }

    /**
     * Menyatukan bentuk JSON notifikasi agar semua endpoint memakai struktur yang sama.
     *
     * @return array<string, mixed>
     */
    private function formatNotifikasi(Notifikasi $notifikasi): array
    {
        return [
            'id' => $notifikasi->id,
            'judul' => $notifikasi->judul,
            'pesan' => $notifikasi->pesan,
            // Jenis dipakai mobile untuk menentukan ikon dan warna notifikasi.
            'jenis' => $notifikasi->jenis,
            // Referensi menghubungkan notifikasi ke pemesanan atau jadwal, jika ada.
            'referensi_id' => $notifikasi->referensi_id,
            // Nilai boolean menjaga status baca tetap konsisten di Flutter.
            'sudah_dibaca' => (bool) $notifikasi->sudah_dibaca,
            // ISO 8601 dipakai agar waktu mudah dibaca oleh DateTime di Flutter.
            'created_at' => $notifikasi->created_at?->toIso8601String(),
        ];
    }
}
