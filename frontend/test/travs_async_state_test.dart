import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/core/services/travs_api_service.dart';
import 'package:frontend/core/widgets/travs_async_state.dart';

/// Test TDD untuk widget TravSAsyncState — memastikan setiap state
/// (loading, error, empty) ditampilkan dengan benar dan konsisten.
/// Ini menggantikan perilaku lama yang menyembunyikan error di balik mock.
void main() {
  group('TravSAsyncState', () {
    // =========================================================================
    // Test: State loading menampilkan spinner dan pesan
    // =========================================================================

    testWidgets('loading state menampilkan spinner dan pesan default', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: TravSAsyncState.loading()),
        ),
      );

      // Harus ada CircularProgressIndicator
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      // Harus ada teks loading default
      expect(find.text('Memuat data...'), findsOneWidget);
      expect(find.text('Mohon tunggu sebentar.'), findsOneWidget);
    });

    testWidgets('loading state menampilkan pesan kustom', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TravSAsyncState.loading(message: 'Memuat beranda...'),
          ),
        ),
      );

      expect(find.text('Memuat beranda...'), findsOneWidget);
    });

    // =========================================================================
    // Test: State error menampilkan pesan ramah dan tombol retry
    // =========================================================================

    testWidgets('error state menampilkan pesan API exception', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TravSAsyncState.error(
              error: const TravSApiException('Jadwal tidak ditemukan.'),
            ),
          ),
        ),
      );

      // Harus ada judul error
      expect(find.text('Gagal Memuat Data'), findsOneWidget);
      // Harus ada pesan dari exception
      expect(find.text('Jadwal tidak ditemukan.'), findsOneWidget);
      // Tidak boleh ada spinner
      expect(find.byType(CircularProgressIndicator), findsNothing);
    });

    testWidgets('error state menampilkan pesan koneksi untuk SocketException', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TravSAsyncState.error(
              error: Exception('SocketException: Connection refused'),
            ),
          ),
        ),
      );

      // Harus menampilkan pesan koneksi yang ramah
      expect(
        find.textContaining('Tidak dapat terhubung ke server'),
        findsOneWidget,
      );
    });

    testWidgets('error state menampilkan pesan timeout', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TravSAsyncState.error(
              error: Exception('TimeoutException after 0:00:30'),
            ),
          ),
        ),
      );

      expect(
        find.textContaining('terlalu lama'),
        findsOneWidget,
      );
    });

    testWidgets('error state menampilkan tombol retry jika callback diberikan', (
      WidgetTester tester,
    ) async {
      var retryCount = 0;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TravSAsyncState.error(
              error: const TravSApiException('Gagal.'),
              onRetry: () => retryCount++,
            ),
          ),
        ),
      );

      // Harus ada tombol "Coba Lagi"
      final retryButton = find.text('Coba Lagi');
      expect(retryButton, findsOneWidget);

      // Tap tombol retry
      await tester.tap(retryButton);
      expect(retryCount, 1);
    });

    testWidgets('error state tidak menampilkan tombol retry jika null', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TravSAsyncState.error(
              error: const TravSApiException('Gagal.'),
            ),
          ),
        ),
      );

      expect(find.text('Coba Lagi'), findsNothing);
    });

    // =========================================================================
    // Test: State empty menampilkan pesan kosong
    // =========================================================================

    testWidgets('empty state menampilkan pesan default', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: TravSAsyncState.empty()),
        ),
      );

      expect(find.text('Belum Ada Data'), findsOneWidget);
      expect(
        find.text('Data yang kamu cari belum tersedia saat ini.'),
        findsOneWidget,
      );
    });

    testWidgets('empty state menampilkan pesan kustom', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TravSAsyncState.empty(
              title: 'Belum Ada Pemesanan',
              subtitle: 'Mulai pesan perjalanan pertamamu!',
            ),
          ),
        ),
      );

      expect(find.text('Belum Ada Pemesanan'), findsOneWidget);
      expect(find.text('Mulai pesan perjalanan pertamamu!'), findsOneWidget);
    });

    // =========================================================================
    // Test: friendlyErrorMessage tidak membocorkan detail teknis
    // =========================================================================

    test('friendlyErrorMessage menyembunyikan detail teknis', () {
      // Error generik — tidak boleh menampilkan stack trace
      final genericMsg = TravSAsyncState.friendlyErrorMessage(
        Exception('SQLSTATE[42S02]: Base table not found'),
      );
      expect(genericMsg, isNot(contains('SQLSTATE')));
      expect(genericMsg, contains('tidak terduga'));

      // API exception — tampilkan pesan langsung
      final apiMsg = TravSAsyncState.friendlyErrorMessage(
        const TravSApiException('Kursi sudah terisi.'),
      );
      expect(apiMsg, 'Kursi sudah terisi.');

      // Network error — pesan koneksi ramah
      final networkMsg = TravSAsyncState.friendlyErrorMessage(
        Exception('SocketException: Connection refused'),
      );
      expect(networkMsg, contains('terhubung ke server'));
    });
  });
}
