import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/models/travs_notification.dart';

void main() {
  test('feed notifikasi membaca status dan badge dari response backend', () {
    final feed = TravSNotificationFeed.fromJson({
      'unread_count': 1,
      'items': [
        {
          'id': 8,
          'judul': 'Pembayaran berhasil',
          'pesan': 'Tiket sudah tersedia.',
          'jenis': 'pembayaran',
          'referensi_id': 12,
          'sudah_dibaca': false,
          'created_at': '2026-06-22T08:00:00+07:00',
        },
      ],
    });

    expect(feed.unreadCount, 1);
    expect(feed.items, hasLength(1));
    expect(feed.items.first.id, 8);
    expect(feed.items.first.sudahDibaca, isFalse);
    expect(feed.items.first.createdAt, isNotNull);
  });

  test('feed notifikasi tetap aman saat response kosong', () {
    final feed = TravSNotificationFeed.fromJson(<String, dynamic>{});

    expect(feed.items, isEmpty);
    expect(feed.unreadCount, 0);
  });
}
