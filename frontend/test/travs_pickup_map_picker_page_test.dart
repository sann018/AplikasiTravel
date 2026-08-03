import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/core/services/travs_location_service.dart';
import 'package:frontend/features/pickup/travs_pickup_map_picker_page.dart';
import 'package:latlong2/latlong.dart';

void main() {
  testWidgets('tujuan Sanggau membuka peta pada hasil geocoding Sanggau', (
    tester,
  ) async {
    final locationService = _FakeLocationService(
      destinationPoints: const {
        'Sanggau': TravSPickupPoint(
          latitude: 0.119166,
          longitude: 110.598164,
          address: 'Sanggau, Kalimantan Barat',
        ),
      },
    );

    await _pumpPicker(
      tester,
      locationService: locationService,
      initialLocationQuery: 'Sanggau',
    );

    expect(locationService.addressQueries, ['Sanggau']);
    expect(find.text('0.119166, 110.598164'), findsOneWidget);
    expect(find.text('Sanggau, Kalimantan Barat'), findsOneWidget);
  });

  testWidgets('tujuan Sintang membuka peta pada hasil geocoding Sintang', (
    tester,
  ) async {
    final locationService = _FakeLocationService(
      destinationPoints: const {
        'Sintang': TravSPickupPoint(
          latitude: 0.080238,
          longitude: 111.495499,
          address: 'Sintang, Kalimantan Barat',
        ),
      },
    );

    await _pumpPicker(
      tester,
      locationService: locationService,
      initialLocationQuery: 'Sintang',
    );

    expect(locationService.addressQueries, ['Sintang']);
    expect(find.text('0.080238, 111.495499'), findsOneWidget);
    expect(find.text('Sintang, Kalimantan Barat'), findsOneWidget);
  });

  testWidgets('titik turun tersimpan dipakai tanpa geocoding ulang tujuan', (
    tester,
  ) async {
    final locationService = _FakeLocationService(
      destinationPoints: const {
        'Sintang': TravSPickupPoint(
          latitude: 0.080238,
          longitude: 111.495499,
          address: 'Sintang, Kalimantan Barat',
        ),
      },
    );

    await _pumpPicker(
      tester,
      locationService: locationService,
      initialLocationQuery: 'Sintang',
      initialPickup: const TravSPickupPoint(
        latitude: 0.123456,
        longitude: 111.654321,
        address: 'Titik turun yang sudah dipilih',
      ),
    );

    expect(locationService.addressQueries, isEmpty);
    expect(find.text('0.123456, 111.654321'), findsOneWidget);
    expect(find.text('Titik turun yang sudah dipilih'), findsOneWidget);
  });

  testWidgets('marker berpindah memperbarui koordinat dan alamat', (
    tester,
  ) async {
    final locationService = _FakeLocationService(
      destinationPoints: const {
        'Sanggau': TravSPickupPoint(
          latitude: 0.119166,
          longitude: 110.598164,
          address: 'Sanggau, Kalimantan Barat',
        ),
      },
    );
    await _pumpPicker(
      tester,
      locationService: locationService,
      initialLocationQuery: 'Sanggau',
    );

    final map = tester.widget<FlutterMap>(find.byType(FlutterMap));
    const tappedPoint = LatLng(0.1000, 110.2000);
    map.options.onTap?.call(
      const TapPosition(Offset.zero, Offset.zero),
      tappedPoint,
    );
    await tester.pump();
    await tester.pump();

    final resolvedPoint = locationService.lastCoordinatePoint;
    expect(resolvedPoint, isNotNull);
    expect(resolvedPoint!.latitude, closeTo(tappedPoint.latitude, 0.000001));
    expect(resolvedPoint.longitude, closeTo(tappedPoint.longitude, 0.000001));
    expect(
      find.text(
        '${resolvedPoint.latitude.toStringAsFixed(6)}, '
        '${resolvedPoint.longitude.toStringAsFixed(6)}',
      ),
      findsOneWidget,
    );
    expect(find.text(locationService.coordinateAddress), findsOneWidget);
  });

  testWidgets('geocoding tujuan gagal memakai fallback dan pesan yang jelas', (
    tester,
  ) async {
    final locationService = _FakeLocationService(
      destinationError: const TravSLocationException(
        'Koordinat tujuan belum dapat ditemukan.',
      ),
    );

    await _pumpPicker(
      tester,
      locationService: locationService,
      initialLocationQuery: 'Tujuan Tanpa Koordinat',
    );

    expect(find.text('-0.026300, 109.342500'), findsOneWidget);
    expect(
      find.text(
        'Koordinat tujuan belum dapat ditemukan. Peta dibuka pada lokasi fallback; silakan pilih titik turun secara manual.',
      ),
      findsOneWidget,
    );
  });

  testWidgets('titik pusat fallback tidak dapat disimpan sebelum dipilih', (
    tester,
  ) async {
    final locationService = _FakeLocationService();

    await tester.pumpWidget(
      MaterialApp(
        home: TravSPickupMapPickerPage(locationService: locationService),
      ),
    );
    await tester.pump();

    final saveButton = tester.widget<ElevatedButton>(
      find.widgetWithText(ElevatedButton, 'Gunakan Titik Ini'),
    );
    expect(saveButton.onPressed, isNull);
  });
}

Future<void> _pumpPicker(
  WidgetTester tester, {
  required _FakeLocationService locationService,
  required String initialLocationQuery,
  TravSPickupPoint? initialPickup,
}) async {
  await tester.pumpWidget(
    MaterialApp(
      home: TravSPickupMapPickerPage(
        initialPickup: initialPickup,
        initialLocationQuery: initialLocationQuery,
        title: 'Pilih Titik Turun',
        locationLabel: 'titik turun',
        showCurrentLocationButton: false,
        locationService: locationService,
      ),
    ),
  );
  await tester.pump();
  await tester.pump();
}

class _FakeLocationService extends TravSLocationService {
  _FakeLocationService({
    this.destinationPoints = const {},
    this.destinationError,
  });

  final Map<String, TravSPickupPoint> destinationPoints;
  final Object? destinationError;
  final List<String> addressQueries = [];
  TravSPickupPoint? lastCoordinatePoint;
  final String coordinateAddress = 'Alamat hasil reverse geocoding marker';

  @override
  Future<TravSPickupPoint> resolvePickupFromAddress(String address) async {
    addressQueries.add(address);
    if (destinationError case final error?) {
      throw error;
    }

    final point = destinationPoints[address];
    if (point == null) {
      throw TravSLocationException(
        'Koordinat tujuan $address belum ditemukan.',
      );
    }
    return point;
  }

  @override
  Future<TravSPickupPoint> resolvePickupFromCoordinates({
    required double latitude,
    required double longitude,
  }) async {
    final point = TravSPickupPoint(
      latitude: latitude,
      longitude: longitude,
      address: coordinateAddress,
    );
    lastCoordinatePoint = point;
    return point;
  }
}
