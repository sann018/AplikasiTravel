import 'package:flutter/material.dart';

import '../services/travs_api_service.dart';
import '../theme/travs_theme.dart';

/// Widget bantu untuk menampilkan kondisi memuat, gagal, atau data kosong
/// dengan tampilan yang sama di seluruh halaman RuslyTravel.
///
/// Tujuannya supaya tiap halaman tidak perlu membuat tampilan gagal sendiri,
/// dan pengguna tetap mendapat pesan yang jelas saat data belum siap.
class TravSAsyncState extends StatelessWidget {
  const TravSAsyncState._({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.onRetry,
    this.isLoading = false,
  });

  /// Dipakai saat halaman sedang mengambil data dari backend.
  factory TravSAsyncState.loading({String? message}) {
    return TravSAsyncState._(
      icon: Icons.hourglass_top_rounded,
      title: message ?? 'Memuat data...',
      subtitle: 'Mohon tunggu sebentar.',
      isLoading: true,
    );
  }

  /// Dipakai saat API gagal atau koneksi terputus.
  /// [onRetry] membuat pengguna bisa mencoba memuat data lagi.
  factory TravSAsyncState.error({
    required Object error,
    VoidCallback? onRetry,
  }) {
    // Pesan teknis disaring dulu supaya yang tampil tetap mudah dipahami.
    // Detail seperti stack trace tidak cocok ditampilkan ke pengguna.
    final message = friendlyErrorMessage(error);

    return TravSAsyncState._(
      icon: Icons.cloud_off_rounded,
      title: 'Gagal Memuat Data',
      subtitle: message,
      onRetry: onRetry,
    );
  }

  /// Dipakai saat permintaan berhasil, tetapi memang belum ada data.
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

  /// Mengubah kesalahan mentah menjadi pesan yang aman dan ramah dibaca pengguna.
  /// Detail teknis cukup untuk log/developer, bukan untuk layar aplikasi.
  static String friendlyErrorMessage(Object error) {
    if (error is TravSApiException) {
      return error.message;
    }

    final raw = error.toString().toLowerCase();

    if (raw.contains('socketexception') ||
        raw.contains('connection refused') ||
        raw.contains('network')) {
      return 'Tidak dapat terhubung ke layanan. Periksa koneksi internet Anda dan coba lagi.';
    }

    if (raw.contains('timeout')) {
      return 'Koneksi ke server terlalu lama. Coba lagi dalam beberapa saat.';
    }

    if (raw.contains('formatexception') || raw.contains('json')) {
      return 'Server mengirim data yang tidak dapat dibaca. Hubungi admin jika masalah berlanjut.';
    }

    return 'Terjadi kendala yang tidak terduga. Silakan coba lagi.';
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
            // Saat masih memuat data, tampilkan pemutar agar pengguna tahu prosesnya berjalan.
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

            // Judul singkat agar pengguna langsung paham kondisi halaman.
            Text(
              title,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w700,
              ),
            ),

            const SizedBox(height: 8),

            // Deskripsi dibuat sederhana, tanpa membocorkan detail teknis server.
            Text(
              subtitle,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navyMuted,
                height: 1.4,
              ),
            ),

            // Tombol ini hanya muncul jika halaman menyediakan aksi coba lagi.
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
