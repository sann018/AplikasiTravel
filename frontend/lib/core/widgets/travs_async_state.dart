import 'package:flutter/material.dart';

import '../services/travs_api_service.dart';
import '../theme/travs_theme.dart';

/// Widget reusable untuk menampilkan state loading, error, dan empty secara
/// konsisten di seluruh halaman TravS. Menghilangkan kebutuhan fallback mock
/// karena setiap state ditangani secara eksplisit dan jujur ke pengguna.
///
/// Prinsip: satu widget, satu tanggung jawab — menampilkan feedback async
/// yang ramah tanpa menyembunyikan kegagalan dari pengguna.
class TravSAsyncState extends StatelessWidget {
  const TravSAsyncState._({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.onRetry,
    this.isLoading = false,
  });

  /// State loading — ditampilkan saat data sedang dimuat dari backend.
  factory TravSAsyncState.loading({String? message}) {
    return TravSAsyncState._(
      icon: Icons.hourglass_top_rounded,
      title: message ?? 'Memuat data...',
      subtitle: 'Mohon tunggu sebentar.',
      isLoading: true,
    );
  }

  /// State error — ditampilkan saat API gagal atau koneksi terputus.
  /// [onRetry] memberikan tombol "Coba Lagi" agar pengguna punya aksi.
  factory TravSAsyncState.error({
    required Object error,
    VoidCallback? onRetry,
  }) {
    // Ambil pesan error yang ramah untuk ditampilkan ke pengguna.
    // Tidak menampilkan stack trace atau detail teknis (rules #26).
    final message = friendlyErrorMessage(error);

    return TravSAsyncState._(
      icon: Icons.cloud_off_rounded,
      title: 'Gagal Memuat Data',
      subtitle: message,
      onRetry: onRetry,
    );
  }

  /// State empty — ditampilkan saat data berhasil dimuat tapi kosong.
  factory TravSAsyncState.empty({
    String? title,
    String? subtitle,
    IconData? icon,
  }) {
    return TravSAsyncState._(
      icon: icon ?? Icons.inbox_rounded,
      title: title ?? 'Belum Ada Data',
      subtitle: subtitle ?? 'Data yang kamu cari belum tersedia saat ini.',
    );
  }

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onRetry;
  final bool isLoading;

  /// Mengekstrak pesan error yang ramah tanpa menampilkan detail teknis
  /// seperti stack trace atau nama tabel ke pengguna (sesuai rules #26).
  static String friendlyErrorMessage(Object error) {
    if (error is TravSApiException) {
      return error.message;
    }

    final raw = error.toString().toLowerCase();

    if (raw.contains('socketexception') ||
        raw.contains('connection refused') ||
        raw.contains('network')) {
      return 'Tidak dapat terhubung ke server. Periksa koneksi internet dan pastikan backend berjalan.';
    }

    if (raw.contains('timeout')) {
      return 'Koneksi ke server terlalu lama. Coba lagi dalam beberapa saat.';
    }

    if (raw.contains('formatexception') || raw.contains('json')) {
      return 'Server mengirim data yang tidak dapat dibaca. Hubungi admin jika masalah berlanjut.';
    }

    return 'Terjadi kesalahan yang tidak terduga. Silakan coba lagi.';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 48),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Ikon status — loading pakai spinner, lainnya pakai ikon statis
            if (isLoading)
              const SizedBox(
                width: 48,
                height: 48,
                child: CircularProgressIndicator(
                  strokeWidth: 3,
                  color: TravSTheme.orange,
                ),
              )
            else
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: TravSTheme.navy.withValues(alpha: 0.08),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, size: 40, color: TravSTheme.navy),
              ),

            const SizedBox(height: 20),

            // Judul state
            Text(
              title,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w700,
              ),
            ),

            const SizedBox(height: 8),

            // Deskripsi — pesan ramah tanpa detail teknis
            Text(
              subtitle,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
                height: 1.4,
              ),
            ),

            // Tombol retry jika tersedia
            if (onRetry != null) ...[
              const SizedBox(height: 24),
              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded, size: 18),
                label: const Text('Coba Lagi'),
                style: FilledButton.styleFrom(
                  backgroundColor: TravSTheme.navy,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 12,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
