import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../core/services/travs_location_service.dart';
import '../../core/theme/travs_theme.dart';

enum _TravSMapLayer { standard, satellite }

class TravSPickupMapPickerPage extends StatefulWidget {
  const TravSPickupMapPickerPage({
    this.initialPickup,
    this.initialLocationQuery,
    this.title = 'Pilih Titik Jemput',
    this.locationLabel = 'titik jemput',
    this.showCurrentLocationButton = true,
    this.locationService,
    super.key,
  });

  final TravSPickupPoint? initialPickup;
  final String? initialLocationQuery;
  final String title;
  final String locationLabel;
  final bool showCurrentLocationButton;
  @visibleForTesting
  final TravSLocationService? locationService;

  @override
  State<TravSPickupMapPickerPage> createState() =>
      _TravSPickupMapPickerPageState();
}

class _TravSPickupMapPickerPageState extends State<TravSPickupMapPickerPage> {
  static const LatLng _pontianakCenter = LatLng(-0.0263, 109.3425);

  late final TravSLocationService _locationService =
      widget.locationService ?? TravSLocationService();
  final MapController _mapController = MapController();

  late LatLng _selectedPoint;
  late String _selectedAddress;
  bool _isResolvingAddress = false;
  bool _isResolvingInitialPoint = false;
  bool _hasSelectedPoint = false;
  _TravSMapLayer _mapLayer = _TravSMapLayer.standard;
  int _lookupToken = 0;
  Timer? _mapMoveDebounce;

  @override
  void initState() {
    super.initState();
    _selectedAddress =
        'Ketuk peta untuk memilih ${widget.locationLabel.toLowerCase()}.';
    final initialPickup = widget.initialPickup;
    if (initialPickup != null) {
      _selectedPoint = LatLng(initialPickup.latitude, initialPickup.longitude);
      _selectedAddress = initialPickup.address;
      _hasSelectedPoint = true;
    } else {
      // Fallback aman memakai pusat peta existing; bukan koordinat hardcode per kota.
      _selectedPoint = _pontianakCenter;
      final initialLocationQuery = widget.initialLocationQuery?.trim() ?? '';
      if (initialLocationQuery.isNotEmpty) {
        _isResolvingInitialPoint = true;
        _resolveInitialPoint(initialLocationQuery);
      }
    }
  }

  @override
  void dispose() {
    _mapMoveDebounce?.cancel();
    super.dispose();
  }

  Future<void> _resolveInitialPoint(String destination) async {
    try {
      // Posisi awal titik turun ditentukan dari tujuan rute, bukan GPS penumpang.
      final destinationPoint = await _locationService.resolvePickupFromAddress(
        destination,
      );
      if (!mounted) return;

      setState(() {
        _selectedPoint = LatLng(
          destinationPoint.latitude,
          destinationPoint.longitude,
        );
        _selectedAddress = destinationPoint.address;
        _isResolvingInitialPoint = false;
      });
    } on TravSLocationException catch (error) {
      if (!mounted) return;
      setState(() {
        _isResolvingInitialPoint = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${error.message} Peta dibuka pada lokasi fallback; silakan pilih titik turun secara manual.',
          ),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isResolvingInitialPoint = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Koordinat tujuan belum dapat dimuat. Peta dibuka pada lokasi fallback; silakan pilih titik turun secara manual.',
          ),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  void _handleMapPositionChanged(MapCamera camera, bool hasGesture) {
    if (!hasGesture) return;

    final point = camera.center;
    setState(() {
      _selectedPoint = point;
      _hasSelectedPoint = true;
    });

    _mapMoveDebounce?.cancel();
    _mapMoveDebounce = Timer(
      // Jeda mencegah pencarian alamat berulang saat peta masih digeser.
      const Duration(milliseconds: 1100),
      () => _resolveAddress(point),
    );
  }

  Future<void> _selectPoint(LatLng point) async {
    setState(() {
      _selectedPoint = point;
      _hasSelectedPoint = true;
    });
    await _resolveAddress(point);
  }

  Future<void> _resolveAddress(LatLng point) async {
    final currentLookup = ++_lookupToken;
    setState(() {
      _isResolvingAddress = true;
    });

    try {
      final pickup = await _locationService.resolvePickupFromCoordinates(
        latitude: point.latitude,
        longitude: point.longitude,
      );

      if (!mounted || currentLookup != _lookupToken) {
        return;
      }

      setState(() {
        _selectedAddress = pickup.address;
      });
    } catch (error) {
      if (!mounted || currentLookup != _lookupToken) {
        return;
      }

      setState(() {
        _selectedAddress =
            'Gagal mengambil alamat. Coba geser pin atau ketik manual.';
      });
    } finally {
      if (mounted && currentLookup == _lookupToken) {
        setState(() {
          _isResolvingAddress = false;
        });
      }
    }
  }

  Future<void> _useCurrentLocation() async {
    try {
      final pickup = await _locationService.resolveCurrentPickup();
      if (!mounted) {
        return;
      }

      final target = LatLng(pickup.latitude, pickup.longitude);
      _mapController.move(target, 16);
      setState(() {
        _selectedPoint = target;
        _selectedAddress = pickup.address;
        _hasSelectedPoint = true;
      });
    } on TravSLocationException catch (error) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.message),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  void _changeZoom(double delta) {
    final camera = _mapController.camera;
    final targetZoom = (camera.zoom + delta).clamp(3.0, 19.0);
    _mapController.move(camera.center, targetZoom);
  }

  void _setMapLayer(_TravSMapLayer layer) {
    if (_mapLayer == layer) return;
    setState(() {
      _mapLayer = layer;
    });
  }

  void _confirmSelection() {
    if (!_hasSelectedPoint) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Ketuk atau geser peta untuk menentukan ${widget.locationLabel} terlebih dahulu.',
          ),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    Navigator.of(context).pop(
      TravSPickupPoint(
        latitude: _selectedPoint.latitude,
        longitude: _selectedPoint.longitude,
        address: _selectedAddress,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          onPressed: () => Navigator.of(context).maybePop(),
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        title: Text(widget.title),
      ),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
            child: Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: TravSTheme.headerGradient,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Ketuk peta untuk memilih ${widget.locationLabel}',
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Geser atau ketuk peta, lalu periksa pin sebelum menggunakan lokasi.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: Colors.white.withValues(alpha: 0.88),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Expanded(
            flex: 6,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(28),
                child: Stack(
                  children: [
                    if (_isResolvingInitialPoint)
                      const Center(child: CircularProgressIndicator())
                    else
                      FlutterMap(
                        mapController: _mapController,
                        options: MapOptions(
                          initialCenter: _selectedPoint,
                          initialZoom: 15,
                          minZoom: 3,
                          maxZoom: 19,
                          onTap: (_, point) => _selectPoint(point),
                          onPositionChanged: _handleMapPositionChanged,
                        ),
                        children: [
                          TileLayer(
                            urlTemplate: _mapLayer == _TravSMapLayer.satellite
                                ? 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
                                : 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                            userAgentPackageName: 'com.ruslytravel.app',
                          ),
                          MarkerLayer(
                            markers: [
                              Marker(
                                point: _selectedPoint,
                                width: 54,
                                height: 54,
                                child: Container(
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(999),
                                    boxShadow: const [
                                      BoxShadow(
                                        color: Color(0x26000000),
                                        blurRadius: 12,
                                        offset: Offset(0, 6),
                                      ),
                                    ],
                                  ),
                                  child: const Icon(
                                    Icons.location_pin,
                                    color: TravSTheme.orange,
                                    size: 34,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          RichAttributionWidget(
                            attributions: [
                              TextSourceAttribution(
                                _mapLayer == _TravSMapLayer.satellite
                                    ? 'Esri World Imagery'
                                    : 'OpenStreetMap contributors',
                              ),
                            ],
                          ),
                        ],
                      ),
                    Positioned(
                      top: 12,
                      right: 12,
                      child: Material(
                        color: Colors.white,
                        elevation: 3,
                        borderRadius: BorderRadius.circular(14),
                        clipBehavior: Clip.antiAlias,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            PopupMenuButton<_TravSMapLayer>(
                              tooltip: 'Pilih tampilan peta',
                              initialValue: _mapLayer,
                              onSelected: _setMapLayer,
                              icon: Icon(
                                _mapLayer == _TravSMapLayer.satellite
                                    ? Icons.satellite_alt_outlined
                                    : Icons.layers_outlined,
                                color: TravSTheme.navy,
                              ),
                              itemBuilder: (context) => const [
                                PopupMenuItem(
                                  value: _TravSMapLayer.standard,
                                  child: Text('Peta standar'),
                                ),
                                PopupMenuItem(
                                  value: _TravSMapLayer.satellite,
                                  child: Text('Satelit'),
                                ),
                              ],
                            ),
                            const SizedBox(
                              width: 34,
                              child: Divider(height: 1),
                            ),
                            IconButton(
                              tooltip: 'Perbesar peta',
                              onPressed: () => _changeZoom(1),
                              icon: const Icon(Icons.add_rounded),
                              color: TravSTheme.navy,
                            ),
                            const SizedBox(
                              width: 34,
                              child: Divider(height: 1),
                            ),
                            IconButton(
                              tooltip: 'Perkecil peta',
                              onPressed: () => _changeZoom(-1),
                              icon: const Icon(Icons.remove_rounded),
                              color: TravSTheme.navy,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Flexible(
            flex: 4,
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
              child: Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: TravSTheme.line),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Koordinat Terpilih',
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: TravSTheme.navy,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      '${_selectedPoint.latitude.toStringAsFixed(6)}, ${_selectedPoint.longitude.toStringAsFixed(6)}',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: TravSTheme.navy,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _isResolvingAddress
                          ? 'Sedang mengambil alamat...'
                          : _selectedAddress,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: TravSTheme.navyMuted,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        if (widget.showCurrentLocationButton) ...[
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _isResolvingAddress
                                  ? null
                                  : _useCurrentLocation,
                              icon: const Icon(Icons.my_location_rounded),
                              label: const Text('Lokasi Saya'),
                            ),
                          ),
                          const SizedBox(width: 12),
                        ],
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: _isResolvingAddress || !_hasSelectedPoint
                                ? null
                                : _confirmSelection,
                            icon: const Icon(
                              Icons.check_circle_outline_rounded,
                            ),
                            label: const Text('Gunakan Titik Ini'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
