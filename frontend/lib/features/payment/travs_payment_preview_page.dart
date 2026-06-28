import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/models/app_role_preview.dart';
import '../../core/models/app_settings.dart';
import '../../core/models/travs_booking_contract.dart';
import '../../core/models/travs_user_session.dart';
import '../../core/services/travs_api_service.dart';
import '../../core/services/travs_location_service.dart';
import '../../core/theme/travs_theme.dart';
import '../pickup/travs_pickup_map_picker_page.dart';

class TravSCheckoutDraft {
  const TravSCheckoutDraft({
    required this.seatCount,
    required this.baggageMethod,
    required this.baggageWeight,
    required this.baggageSize,
    required this.baggageLength,
    required this.baggageWidth,
    required this.baggageItems,
    required this.ticketSubtotal,
    required this.weightFee,
    required this.sizeFee,
    required this.itemFee,
  });

  final int seatCount;
  final String baggageMethod;
  final double baggageWeight;
  final int baggageSize;
  final int baggageLength;
  final int baggageWidth;
  final int baggageItems;
  final double ticketSubtotal;
  final double weightFee;
  final double sizeFee;
  final double itemFee;

  double get total => ticketSubtotal + weightFee + sizeFee + itemFee;
}

class TravSPaymentPreviewPage extends StatefulWidget {
  const TravSPaymentPreviewPage({
    required this.detail,
    required this.appSettings,
    required this.checkoutDraft,
    required this.selectedRole,
    required this.session,
    required this.selectedSeatNumbers,
    required this.baggageMethod,
    required this.baggageWeight,
    required this.baggageSize,
    required this.baggageLength,
    required this.baggageWidth,
    required this.baggageItems,
    required this.lockExpiresAt,
    super.key,
  });

  final TravSScheduleDetail detail;
  final AppSettings appSettings;
  final TravSCheckoutDraft checkoutDraft;
  final AppRolePreview selectedRole;
  final TravSUserSession session;
  final List<int> selectedSeatNumbers;
  final String baggageMethod;
  final double baggageWeight;
  final int baggageSize;
  final int baggageLength;
  final int baggageWidth;
  final int baggageItems;
  final String lockExpiresAt;

  @override
  State<TravSPaymentPreviewPage> createState() =>
      _TravSPaymentPreviewPageState();
}

class _TravSPaymentPreviewPageState extends State<TravSPaymentPreviewPage>
    with WidgetsBindingObserver {
  final TravSApiService _apiService = TravSApiService();
  final TravSLocationService _locationService = TravSLocationService();
  final ImagePicker _imagePicker = ImagePicker();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _pickupLatitudeController =
      TextEditingController();
  final TextEditingController _pickupLongitudeController =
      TextEditingController();
  final TextEditingController _pickupAddressController =
      TextEditingController();
  final TextEditingController _pickupNoteController = TextEditingController();
  final TextEditingController _dropoffLatitudeController =
      TextEditingController();
  final TextEditingController _dropoffLongitudeController =
      TextEditingController();
  final TextEditingController _dropoffAddressController =
      TextEditingController();
  final TextEditingController _dropoffNoteController = TextEditingController();

  late final List<_PassengerFormController> _passengerForms;
  late final List<_PaymentMethodOption> _paymentOptions;
  String? _selectedMethod;
  bool _isSubmitting = false;
  bool _isResolvingPickup = false;
  bool _isResolvingDropoff = false;
  bool _isUploadingBaggagePhoto = false;
  bool _isPickingBaggagePhoto = false;
  bool _isRefreshingPayment = false;
  bool _showLeaveWarningOnResume = false;
  bool _hasOpenedGatewayCheckout = false;
  String? _errorMessage;
  TravSBookingResult? _createdBooking;
  final List<XFile> _selectedBaggagePhotos = <XFile>[];
  TravSUploadedBaggagePhoto? _uploadedBaggagePhoto;
  List<TravSUploadedBaggagePhoto> _uploadedBaggagePhotos =
      <TravSUploadedBaggagePhoto>[];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _paymentOptions = _resolvePaymentOptions(
      widget.detail.paymentMethods,
      cashEnabled: widget.appSettings.paymentCashEnabled,
    );
    _selectedMethod = _paymentOptions.isNotEmpty
        ? _paymentOptions.first.key
        : null;

    final user = widget.session.user;
    _passengerForms = List<_PassengerFormController>.generate(
      widget.selectedSeatNumbers.length,
      (index) {
        final seatNumber = widget.selectedSeatNumbers[index];
        return _PassengerFormController(
          seatNumber: seatNumber,
          name: index == 0 ? user.nama : '',
          phone: index == 0 ? user.noHp : '',
        );
      },
    );
    _loadFormDraft();
    _registerDraftListeners();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _pickupLatitudeController.dispose();
    _pickupLongitudeController.dispose();
    _pickupAddressController.dispose();
    _pickupNoteController.dispose();
    _dropoffLatitudeController.dispose();
    _dropoffLongitudeController.dispose();
    _dropoffAddressController.dispose();
    _dropoffNoteController.dispose();
    for (final passenger in _passengerForms) {
      passenger.dispose();
    }
    super.dispose();
  }

  String get _draftKey =>
      'draft_booking_${widget.session.user.id}_${widget.detail.schedule.id}';

  /// Draft dipakai supaya isian panjang tidak hilang saat aplikasi tertutup
  /// sementara. Data password tidak ikut disimpan demi keamanan akun.
  Future<void> _loadFormDraft() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final rawDraft = prefs.getString(_draftKey);
      if (rawDraft == null || rawDraft.isEmpty) return;

      final draft = jsonDecode(rawDraft);
      if (draft is! Map<String, dynamic> || !mounted) return;

      setState(() {
        _pickupLatitudeController.text =
            draft['pickup_latitude'] as String? ??
            _pickupLatitudeController.text;
        _pickupLongitudeController.text =
            draft['pickup_longitude'] as String? ??
            _pickupLongitudeController.text;
        _pickupAddressController.text =
            draft['pickup_address'] as String? ?? _pickupAddressController.text;
        _pickupNoteController.text =
            draft['pickup_note'] as String? ?? _pickupNoteController.text;
        _dropoffLatitudeController.text =
            draft['dropoff_latitude'] as String? ??
            _dropoffLatitudeController.text;
        _dropoffLongitudeController.text =
            draft['dropoff_longitude'] as String? ??
            _dropoffLongitudeController.text;
        _dropoffAddressController.text =
            draft['dropoff_address'] as String? ??
            _dropoffAddressController.text;
        _dropoffNoteController.text =
            draft['dropoff_note'] as String? ?? _dropoffNoteController.text;
        _selectedMethod = draft['payment_method'] as String? ?? _selectedMethod;

        final passengers = draft['passengers'];
        if (passengers is List) {
          for (
            var index = 0;
            index < passengers.length && index < _passengerForms.length;
            index++
          ) {
            final passenger = passengers[index];
            if (passenger is Map<String, dynamic>) {
              _passengerForms[index].nameController.text =
                  passenger['name'] as String? ??
                  _passengerForms[index].nameController.text;
              _passengerForms[index].phoneController.text =
                  passenger['phone'] as String? ??
                  _passengerForms[index].phoneController.text;
            }
          }
        }
      });
    } catch (_) {
      // Kalau draft rusak, form tetap bisa dipakai normal tanpa crash.
    }
  }

  void _registerDraftListeners() {
    final controllers = <TextEditingController>[
      _pickupLatitudeController,
      _pickupLongitudeController,
      _pickupAddressController,
      _pickupNoteController,
      _dropoffLatitudeController,
      _dropoffLongitudeController,
      _dropoffAddressController,
      _dropoffNoteController,
      for (final passenger in _passengerForms) ...[
        passenger.nameController,
        passenger.phoneController,
      ],
    ];

    for (final controller in controllers) {
      controller.addListener(() {
        _saveFormDraft();
      });
    }
  }

  Future<void> _saveFormDraft() async {
    if (_createdBooking != null) return;

    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _draftKey,
        jsonEncode({
          'pickup_latitude': _pickupLatitudeController.text,
          'pickup_longitude': _pickupLongitudeController.text,
          'pickup_address': _pickupAddressController.text,
          'pickup_note': _pickupNoteController.text,
          'dropoff_latitude': _dropoffLatitudeController.text,
          'dropoff_longitude': _dropoffLongitudeController.text,
          'dropoff_address': _dropoffAddressController.text,
          'dropoff_note': _dropoffNoteController.text,
          'payment_method': _selectedMethod,
          'passengers': _passengerForms
              .map(
                (passenger) => {
                  'name': passenger.nameController.text,
                  'phone': passenger.phoneController.text,
                },
              )
              .toList(),
        }),
      );
    } catch (_) {
      // Draft hanya bantuan lokal, jadi gagal menyimpan tidak boleh
      // menghalangi penumpang menyelesaikan pemesanan.
    }
  }

  Future<void> _clearFormDraft() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_draftKey);
    } catch (_) {
      // Draft yang gagal dihapus tidak memengaruhi data booking di server.
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive) {
      if (_createdBooking == null && !_isPickingBaggagePhoto) {
        _showLeaveWarningOnResume = true;
      }
      return;
    }

    if (state != AppLifecycleState.resumed) {
      return;
    }

    if (_createdBooking != null) {
      _refreshCreatedBookingStatus(showSuccessMessage: true);
      return;
    }

    if (_showLeaveWarningOnResume && mounted) {
      _showLeaveWarningOnResume = false;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          _confirmLeaveFlow();
        }
      });
    }
  }

  bool get _hasBaggageData =>
      widget.baggageWeight > 0 ||
      widget.baggageSize > 0 ||
      widget.baggageItems > 0;

  TravSPickupPoint? get _draftPickupPoint {
    final latitude = double.tryParse(_pickupLatitudeController.text.trim());
    final longitude = double.tryParse(_pickupLongitudeController.text.trim());
    final address = _pickupAddressController.text.trim();

    if (latitude == null || longitude == null || address.isEmpty) {
      return null;
    }

    return TravSPickupPoint(
      latitude: latitude,
      longitude: longitude,
      address: address,
    );
  }

  bool get _hasDropoffDraft =>
      _dropoffLatitudeController.text.trim().isNotEmpty &&
      _dropoffLongitudeController.text.trim().isNotEmpty &&
      _dropoffAddressController.text.trim().isNotEmpty;

  TravSPickupPoint? get _draftDropoffPoint {
    final latitude = double.tryParse(_dropoffLatitudeController.text.trim());
    final longitude = double.tryParse(_dropoffLongitudeController.text.trim());
    final address = _dropoffAddressController.text.trim();

    if (latitude == null || longitude == null || address.isEmpty) {
      return null;
    }

    return TravSPickupPoint(
      latitude: latitude,
      longitude: longitude,
      address: address,
    );
  }

  /// Mengubah koordinat yang diketik manual menjadi alamat yang mudah dibaca.
  /// Fungsi ini dijalankan setelah pengguna selesai mengisi latitude/longitude.
  Future<void> _autoResolveAddressFromCoordinates() async {
    final latitude = double.tryParse(_pickupLatitudeController.text.trim());
    final longitude = double.tryParse(_pickupLongitudeController.text.trim());

    // Pencarian alamat hanya dijalankan jika kedua koordinat sudah valid.
    if (latitude == null || longitude == null) return;
    if (latitude < -90 || latitude > 90) return;
    if (longitude < -180 || longitude > 180) return;

    setState(() {
      _isResolvingPickup = true;
    });

    try {
      final pickup = await _locationService.resolvePickupFromCoordinates(
        latitude: latitude,
        longitude: longitude,
      );

      if (!mounted) return;

      setState(() {
        _pickupAddressController.text = pickup.address;
        _isResolvingPickup = false;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _isResolvingPickup = false;
        });
      }
    }
  }

  void _applyPickup(TravSPickupPoint pickup) {
    setState(() {
      _pickupLatitudeController.text = pickup.latitude.toStringAsFixed(6);
      _pickupLongitudeController.text = pickup.longitude.toStringAsFixed(6);
      _pickupAddressController.text = pickup.address;
    });
  }

  Future<void> _pickFromCurrentLocation() async {
    setState(() {
      _isResolvingPickup = true;
      _errorMessage = null;
    });

    try {
      final pickup = await _locationService.resolveCurrentPickup();
      if (!mounted) {
        return;
      }

      _applyPickup(pickup);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Titik jemput berhasil diisi dari GPS perangkat.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } on TravSLocationException catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _errorMessage = error.message;
      });
    } finally {
      if (mounted) {
        setState(() {
          _isResolvingPickup = false;
        });
      }
    }
  }

  Future<void> _pickFromMap() async {
    final pickup = await Navigator.of(context).push<TravSPickupPoint>(
      MaterialPageRoute<TravSPickupPoint>(
        builder: (_) =>
            TravSPickupMapPickerPage(initialPickup: _draftPickupPoint),
      ),
    );

    if (pickup != null && mounted) {
      _applyPickup(pickup);
    }
  }

  Future<void> _autoResolveDropoffAddress() async {
    final latitude = double.tryParse(_dropoffLatitudeController.text.trim());
    final longitude = double.tryParse(_dropoffLongitudeController.text.trim());

    if (latitude == null || longitude == null) return;
    if (latitude < -90 || latitude > 90) return;
    if (longitude < -180 || longitude > 180) return;

    setState(() {
      _isResolvingDropoff = true;
    });

    try {
      final dropoff = await _locationService.resolvePickupFromCoordinates(
        latitude: latitude,
        longitude: longitude,
      );

      if (!mounted) return;
      setState(() {
        _dropoffAddressController.text = dropoff.address;
      });
    } catch (_) {
      // Alamat tetap bisa diketik manual jika layanan geocoding sedang gagal.
    } finally {
      if (mounted) {
        setState(() {
          _isResolvingDropoff = false;
        });
      }
    }
  }

  void _applyDropoff(TravSPickupPoint dropoff) {
    setState(() {
      _dropoffLatitudeController.text = dropoff.latitude.toStringAsFixed(6);
      _dropoffLongitudeController.text = dropoff.longitude.toStringAsFixed(6);
      _dropoffAddressController.text = dropoff.address;
    });
  }

  Future<void> _pickDropoffFromMap() async {
    final dropoff = await Navigator.of(context).push<TravSPickupPoint>(
      MaterialPageRoute<TravSPickupPoint>(
        builder: (_) => TravSPickupMapPickerPage(
          initialPickup: _draftDropoffPoint,
          title: 'Pilih Titik Turun',
          locationLabel: 'titik turun',
        ),
      ),
    );

    if (dropoff != null && mounted) {
      _applyDropoff(dropoff);
    }
  }

  Future<void> _selectBaggagePhoto(ImageSource source) async {
    if (_selectedBaggagePhotos.length >= 3) {
      setState(() {
        _errorMessage = 'Maksimal 3 foto barang bawaan yang bisa dipilih.';
      });
      return;
    }

    _isPickingBaggagePhoto = true;
    try {
      final photo = await _imagePicker.pickImage(
        source: source,
        imageQuality: 82,
        maxWidth: 1600,
        maxHeight: 1600,
      );

      if (photo == null || !mounted) {
        return;
      }

      setState(() {
        _selectedBaggagePhotos.add(photo);
        _uploadedBaggagePhoto = null;
        _uploadedBaggagePhotos = <TravSUploadedBaggagePhoto>[];
        _errorMessage = null;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _errorMessage =
            'Foto barang bawaan belum bisa dipilih. Coba gunakan galeri atau kamera lain.';
      });
    } finally {
      _isPickingBaggagePhoto = false;
    }
  }

  void _removeBaggagePhoto(int index) {
    if (index < 0 || index >= _selectedBaggagePhotos.length) return;

    setState(() {
      _selectedBaggagePhotos.removeAt(index);
      _uploadedBaggagePhoto = null;
      _uploadedBaggagePhotos = <TravSUploadedBaggagePhoto>[];
    });
  }

  Future<List<String>> _resolveBaggagePhotoUrls() async {
    if (!_hasBaggageData) {
      return const <String>[];
    }

    if (_uploadedBaggagePhotos.isNotEmpty) {
      return _uploadedBaggagePhotos
          .map((photo) => photo.path)
          .where((path) => path.isNotEmpty)
          .take(3)
          .toList();
    }

    if (_selectedBaggagePhotos.isEmpty) {
      if (widget.appSettings.baggagePhotoRequired) {
        setState(() {
          _errorMessage =
              'Foto barang bawaan wajib diunggah sebelum pemesanan.';
        });
      }
      return const <String>[];
    }

    setState(() {
      _isUploadingBaggagePhoto = true;
    });

    try {
      final uploadedPhotos = <TravSUploadedBaggagePhoto>[];
      for (final photo in _selectedBaggagePhotos.take(3)) {
        final uploadedPhoto = await _apiService.uploadBaggagePhoto(
          token: widget.session.token,
          photo: photo,
        );
        uploadedPhotos.add(uploadedPhoto);
      }

      if (!mounted) {
        return const <String>[];
      }

      setState(() {
        _uploadedBaggagePhotos = uploadedPhotos;
        _uploadedBaggagePhoto = uploadedPhotos.isNotEmpty
            ? uploadedPhotos.first
            : null;
      });

      return uploadedPhotos
          .map((photo) => photo.path)
          .where((path) => path.isNotEmpty)
          .take(3)
          .toList();
    } on TravSApiException catch (error) {
      if (mounted) {
        setState(() {
          _errorMessage = error.message;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _errorMessage =
              'Upload foto barang bawaan gagal. Periksa koneksi internet lalu coba lagi.';
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _isUploadingBaggagePhoto = false;
        });
      }
    }

    return const <String>[];
  }

  Future<void> _submitBooking() async {
    if (_selectedMethod == null) {
      setState(() {
        _errorMessage =
            'Belum ada metode pembayaran aktif. Silakan hubungi admin.';
      });
      return;
    }

    final form = _formKey.currentState;
    if (form == null || !form.validate()) {
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
    });

    try {
      final baggagePhotoUrls = await _resolveBaggagePhotoUrls();
      if (!mounted) {
        return;
      }
      if (_hasBaggageData &&
          widget.appSettings.baggagePhotoRequired &&
          baggagePhotoUrls.isEmpty) {
        return;
      }

      final booking = await _apiService.createBooking(
        token: widget.session.token,
        payload: TravSCreateBookingPayload(
          jadwalId: widget.detail.schedule.id,
          seatNumbers: widget.selectedSeatNumbers,
          pickupLatitude: double.parse(_pickupLatitudeController.text.trim()),
          pickupLongitude: double.parse(_pickupLongitudeController.text.trim()),
          pickupAddress: _pickupAddressController.text.trim(),
          pickupNote: _pickupNoteController.text.trim().isEmpty
              ? null
              : _pickupNoteController.text.trim(),
          dropoffLatitude: double.parse(_dropoffLatitudeController.text.trim()),
          dropoffLongitude: double.parse(
            _dropoffLongitudeController.text.trim(),
          ),
          dropoffAddress: _dropoffAddressController.text.trim(),
          dropoffNote: _dropoffNoteController.text.trim().isEmpty
              ? null
              : _dropoffNoteController.text.trim(),
          paymentMethod: _selectedMethod!,
          passengers: _passengerForms
              .map(
                (item) => TravSPassengerPayload(
                  seatNumber: item.seatNumber,
                  namaPenumpang: item.nameController.text.trim(),
                  noHpPenumpang: item.phoneController.text.trim(),
                ),
              )
              .toList(),
          baggageItems: _buildBaggagePayload(baggagePhotoUrls),
        ),
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _createdBooking = booking;
      });
      await _clearFormDraft();
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pemesanan berhasil dibuat. Lanjutkan pembayaran.'),
          behavior: SnackBarBehavior.floating,
        ),
      );

      if ((booking.payment.method == 'qris' ||
              booking.payment.method == 'transfer') &&
          booking.payment.hasCheckoutUrl &&
          !_hasOpenedGatewayCheckout) {
        _hasOpenedGatewayCheckout = true;
        await _openPaymentCheckout(booking.payment.checkoutUrl);
      }
    } on TravSApiException catch (error) {
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      setState(() {
        _errorMessage =
            'Pemesanan belum dapat diproses. Periksa lagi data penumpang dan titik jemput.';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  /// Membuka halaman pembayaran Pakasir di browser eksternal.
  /// Penumpang menyelesaikan pembayaran di sana, lalu kembali ke aplikasi.
  Future<void> _openPaymentCheckout(String url) async {
    final uri = Uri.tryParse(url);
    if (uri == null) {
      setState(() {
        _errorMessage = 'URL pembayaran tidak valid. Silakan coba lagi.';
      });
      return;
    }

    try {
      final launched = await launchUrl(
        uri,
        mode: LaunchMode.externalApplication,
      );
      if (!launched && mounted) {
        setState(() {
          _errorMessage =
              'Tidak dapat membuka halaman pembayaran. Pastikan browser tersedia.';
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Gagal membuka halaman pembayaran Pakasir.';
        });
      }
    }
  }

  Future<void> _refreshCreatedBookingStatus({
    bool showSuccessMessage = false,
  }) async {
    final booking = _createdBooking;
    if (booking == null || _isRefreshingPayment) {
      return;
    }

    setState(() {
      _isRefreshingPayment = true;
    });

    try {
      final refreshedBooking = await _apiService.refreshPaymentStatus(
        token: widget.session.token,
        bookingId: booking.id,
      );

      if (!mounted) {
        return;
      }

      final wasPaid = _createdBooking?.isPaid ?? false;
      setState(() {
        _createdBooking = refreshedBooking;
        _errorMessage = null;
      });

      if (showSuccessMessage && refreshedBooking.isPaid && !wasPaid) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Pembayaran berhasil. Tiket sudah aktif.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } on TravSApiException catch (error) {
      if (mounted) {
        setState(() {
          _errorMessage = error.message;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _errorMessage =
              'Status pembayaran belum dapat diperbarui. Silakan coba lagi beberapa saat.';
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _isRefreshingPayment = false;
        });
      }
    }
  }

  List<TravSBaggagePayload> _buildBaggagePayload(
    List<String> baggagePhotoUrls,
  ) {
    if (!_hasBaggageData) {
      return const <TravSBaggagePayload>[];
    }

    final method = widget.baggageMethod;
    final usesWeight = method == 'berat';
    final usesSize = method == 'ukuran';

    return <TravSBaggagePayload>[
      TravSBaggagePayload(
        deskripsiItem: usesSize
            ? 'Barang bawaan ukuran besar'
            : 'Barang bawaan penumpang',
        metodeHitung: method,
        beratKg: usesWeight && widget.baggageWeight > 0
            ? widget.baggageWeight
            : null,
        ukuranCm: usesSize && widget.baggageSize > 0
            ? widget.baggageSize
            : null,
        panjangCm: usesSize && widget.baggageLength > 0
            ? widget.baggageLength
            : null,
        lebarCm: usesSize && widget.baggageWidth > 0
            ? widget.baggageWidth
            : null,
        jumlahItem: widget.baggageItems > 0 ? widget.baggageItems : 1,
        fotoUrl: baggagePhotoUrls.isNotEmpty ? baggagePhotoUrls.first : null,
        fotoUrls: baggagePhotoUrls.take(3).toList(),
      ),
    ];
  }

  List<_PaymentMethodOption> _resolvePaymentOptions(
    TravSPaymentMethods methods, {
    required bool cashEnabled,
  }) {
    final options = <_PaymentMethodOption>[];
    if (methods.qrisEnabled) {
      options.add(
        const _PaymentMethodOption(
          key: 'qris',
          label: 'QRIS',
          helper:
              'Cocok untuk pembayaran cepat dari e-wallet dan mobile banking.',
          icon: Icons.qr_code_2_rounded,
        ),
      );
    }
    if (methods.transferEnabled) {
      options.add(
        const _PaymentMethodOption(
          key: 'transfer',
          label: 'Transfer Bank',
          helper: 'Gunakan rekening tujuan yang nanti diberikan admin.',
          icon: Icons.account_balance_rounded,
        ),
      );
    }
    if (methods.cashEnabled && cashEnabled) {
      options.add(
        _PaymentMethodOption(
          key: 'cash',
          label: 'Bayar Tunai',
          helper: widget.appSettings.paymentCashNote,
          icon: Icons.payments_rounded,
        ),
      );
    }
    return options;
  }

  String? _validateCoordinate(
    String? value, {
    required String label,
    required double min,
    required double max,
  }) {
    final raw = value?.trim() ?? '';
    if (raw.isEmpty) {
      return '$label wajib diisi.';
    }

    final parsed = double.tryParse(raw);
    if (parsed == null) {
      return '$label harus berupa angka desimal.';
    }

    if (parsed < min || parsed > max) {
      return '$label berada di luar rentang yang valid.';
    }

    return null;
  }

  String? _validateRequiredText(String? value, String label) {
    if ((value ?? '').trim().isEmpty) {
      return '$label wajib diisi.';
    }
    return null;
  }

  Future<void> _handleFormSessionExpired() async {
    if (_createdBooking != null || !mounted) return;

    await _clearFormDraft();
    if (!mounted) return;

    final shouldLeave = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Sesi Pemesanan Berakhir'),
        content: const Text(
          'Waktu pengisian telah habis sehingga kursi dilepas kembali. Silakan pilih jadwal dan kursi dari awal.',
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Oke'),
          ),
        ],
      ),
    );

    if (shouldLeave == true && mounted) {
      Navigator.of(context).popUntil((route) => route.isFirst);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pemesanan dibatalkan karena sesi pengisian berakhir.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _handlePaymentSessionExpired() async {
    if (_createdBooking == null || !mounted) return;

    await _refreshCreatedBookingStatus();
    if (!mounted || (_createdBooking?.isPaid ?? false)) return;

    final shouldLeave = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Waktu Pembayaran Berakhir'),
        content: const Text(
          'Pemesanan dibatalkan karena pembayaran melewati batas waktu. Status akhir tetap mengikuti pengecekan dari server.',
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Oke'),
          ),
        ],
      ),
    );

    if (shouldLeave == true && mounted) {
      Navigator.of(context).popUntil((route) => route.isFirst);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pemesanan dibatalkan karena waktu pembayaran habis.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _confirmLeaveFlow() async {
    final isPaymentStep = _createdBooking != null;
    final title = isPaymentStep
        ? 'Keluar dari pembayaran?'
        : 'Batalkan pemesanan?';
    final content = isPaymentStep
        ? 'Pemesanan tetap tersimpan sampai batas pembayaran habis. Kamu bisa kembali membayar dari daftar pemesanan.'
        : 'Data pemesanan yang belum dikirim akan hilang jika kamu keluar dari halaman ini.';
    final cancelLabel = isPaymentStep
        ? 'Keluar, tetap simpan'
        : 'Ya, batalkan pemesanan';
    final continueLabel = isPaymentStep
        ? 'Lanjutkan pembayaran'
        : 'Lanjutkan pemesanan';

    final shouldLeave = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          backgroundColor: Colors.white,
          title: Text(
            title,
            style: const TextStyle(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w900,
            ),
          ),
          content: Text(
            content,
            style: const TextStyle(color: TravSTheme.navyMuted),
          ),
          actions: [
            OutlinedButton(
              onPressed: () => Navigator.of(context).pop(true),
              style: OutlinedButton.styleFrom(
                foregroundColor: TravSTheme.danger,
                side: const BorderSide(color: TravSTheme.danger),
              ),
              child: Text(cancelLabel),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(false),
              style: FilledButton.styleFrom(backgroundColor: TravSTheme.navy),
              child: Text(continueLabel),
            ),
          ],
        );
      },
    );

    if (shouldLeave == true && mounted) {
      Navigator.of(context).pop(false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope<bool>(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) {
          _confirmLeaveFlow();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            onPressed: _confirmLeaveFlow,
            icon: const Icon(Icons.arrow_back_rounded),
          ),
          title: Text(
            _createdBooking == null ? 'Pemesanan Jadwal' : 'Pembayaran',
          ),
        ),
        body: Container(
          decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
          child: SafeArea(
            child: _createdBooking == null
                ? _CheckoutFormView(
                    formKey: _formKey,
                    header: _buildHeaderCard(context),
                    paymentMethodCard: _buildPaymentMethodCard(context),
                    pickupCard: _buildPickupCard(context),
                    dropoffCard: _buildDropoffCard(context),
                    passengerCard: _buildPassengerCard(context),
                    baggageCard: _buildBaggageCard(context),
                    summaryCard: _buildSummaryCard(context),
                    errorMessage: _errorMessage,
                    isSubmitting: _isSubmitting,
                    onSubmit: _submitBooking,
                  )
                : _buildCreatedBookingView(context, _createdBooking!),
          ),
        ),
      ),
    );
  }

  Widget _buildHeaderCard(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: TravSTheme.headerGradient,
        borderRadius: BorderRadius.circular(30),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1F10233F),
            blurRadius: 24,
            offset: Offset(0, 16),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _HeaderPill(
                label: widget.selectedRole == AppRolePreview.passenger
                    ? 'Akun Penumpang'
                    : widget.selectedRole == AppRolePreview.driver
                    ? 'Akun Pengemudi'
                    : 'Akun Guest',
              ),
              _HeaderPill(
                label:
                    'Kunci form ${widget.appSettings.seatLockFormMinutes} menit',
                color: TravSTheme.orange,
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            '${widget.detail.schedule.asal} - ${widget.detail.schedule.tujuan}',
            style: theme.textTheme.headlineSmall?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Kursi ${widget.selectedSeatNumbers.map((seat) => 'A$seat').join(', ')} sudah dikunci. Lengkapi data sebelum ${_formatDateTime(widget.lockExpiresAt)}.',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: Colors.white.withValues(alpha: 0.9),
            ),
          ),
          const SizedBox(height: 18),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _HeaderInfo(
                icon: Icons.schedule_rounded,
                text: _formatDateTime(widget.detail.schedule.waktuBerangkat),
              ),
              _HeaderInfo(
                icon: Icons.payments_rounded,
                text:
                    'Estimasi Rp${_formatCurrency(widget.checkoutDraft.total)}',
              ),
            ],
          ),
          const SizedBox(height: 14),
          _CountdownPanel(
            title: 'Sisa waktu pengisian form',
            deadlineIso: widget.lockExpiresAt,
            icon: Icons.timer_outlined,
            onDark: true,
            onExpired: () {
              _handleFormSessionExpired();
            },
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentMethodCard(BuildContext context) {
    final theme = Theme.of(context);

    return _SectionCard(
      title: 'Metode Pembayaran',
      subtitle:
          'Pilih metode pembayaran yang aktif. Layar detail sesudah pemesanan akan menyesuaikan pola QRIS, transfer, atau tunai.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (_paymentOptions.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: TravSTheme.orangeSoft,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                'Belum ada metode pembayaran yang tersedia saat ini.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: TravSTheme.orange,
                  fontWeight: FontWeight.w700,
                ),
              ),
            )
          else
            ..._paymentOptions.map(
              (option) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _PaymentMethodTile(
                  option: option,
                  isSelected: _selectedMethod == option.key,
                  onTap: () {
                    setState(() => _selectedMethod = option.key);
                    _saveFormDraft();
                  },
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildPickupCard(BuildContext context) {
    return _SectionCard(
      title: 'Titik Jemput',
      subtitle:
          'Pilih titik jemput dari GPS atau peta, lalu alamat akan diisi otomatis agar admin dan pengemudi menerima lokasi yang jelas.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              OutlinedButton.icon(
                onPressed: _isResolvingPickup ? null : _pickFromCurrentLocation,
                icon: Icon(
                  _isResolvingPickup
                      ? Icons.sync_rounded
                      : Icons.my_location_rounded,
                ),
                label: Text(
                  _isResolvingPickup ? 'Mengambil GPS...' : 'Gunakan GPS',
                ),
              ),
              OutlinedButton.icon(
                onPressed: _isResolvingPickup ? null : _pickFromMap,
                icon: const Icon(Icons.map_outlined),
                label: const Text('Pilih di Peta'),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: TextFormField(
                  controller: _pickupLatitudeController,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                    signed: true,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Latitude',
                    hintText: '-0.0263',
                  ),
                  validator: (value) => _validateCoordinate(
                    value,
                    label: 'Latitude',
                    min: -90,
                    max: 90,
                  ),
                  // Cari nama alamat setelah pengguna selesai mengisi koordinat.
                  onEditingComplete: _autoResolveAddressFromCoordinates,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextFormField(
                  controller: _pickupLongitudeController,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                    signed: true,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Longitude',
                    hintText: '109.3425',
                  ),
                  validator: (value) => _validateCoordinate(
                    value,
                    label: 'Longitude',
                    min: -180,
                    max: 180,
                  ),
                  // Cari nama alamat setelah pengguna selesai mengisi koordinat.
                  onEditingComplete: _autoResolveAddressFromCoordinates,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _pickupAddressController,
            minLines: 2,
            maxLines: 3,
            decoration: InputDecoration(
              labelText: 'Titik Jemput Anda',
              hintText: 'Terisi otomatis dari koordinat GPS atau peta.',
              helperText: _isResolvingPickup
                  ? 'Mengambil nama alamat dari koordinat...'
                  : null,
            ),
            validator: (value) => _validateRequiredText(value, 'Alamat jemput'),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _pickupNoteController,
            minLines: 2,
            maxLines: 3,
            maxLength: 500,
            decoration: const InputDecoration(
              labelText: 'Catatan / Patokan Jemput',
              hintText: 'Contoh: depan toko atau rumah pagar hijau',
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDropoffCard(BuildContext context) {
    final theme = Theme.of(context);

    return _SectionCard(
      title: 'Titik Turun',
      subtitle:
          'Tentukan lokasi tujuan penumpang. Alamat ini akan tampil di manifest pengemudi dan dapat dibuka melalui Google Maps.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          OutlinedButton.icon(
            onPressed: _isResolvingDropoff ? null : _pickDropoffFromMap,
            icon: const Icon(Icons.map_outlined),
            label: const Text('Pilih Titik Turun di Peta'),
          ),
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: TravSTheme.orangeSoft,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Text(
              'Tips: pilih titik turun lewat peta aplikasi. Jika ingin memakai Google Maps, salin latitude dan longitude tujuan, lalu isi patokan seperti nama toko, simpang, gerbang, atau ciri bangunan terdekat.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navy,
                height: 1.45,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          if (_hasDropoffDraft) ...[
            const SizedBox(height: 14),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: TravSTheme.surfaceSoft,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Tujuan Penumpang',
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _dropoffAddressController.text.trim(),
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Koordinat: ${_dropoffLatitudeController.text.trim()}, ${_dropoffLongitudeController.text.trim()}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navyMuted,
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: TextFormField(
                  controller: _dropoffLatitudeController,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                    signed: true,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Latitude Tujuan',
                    hintText: '0.1285',
                  ),
                  validator: (value) => _validateCoordinate(
                    value,
                    label: 'Latitude tujuan',
                    min: -90,
                    max: 90,
                  ),
                  onEditingComplete: _autoResolveDropoffAddress,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextFormField(
                  controller: _dropoffLongitudeController,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                    signed: true,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Longitude Tujuan',
                    hintText: '111.4986',
                  ),
                  validator: (value) => _validateCoordinate(
                    value,
                    label: 'Longitude tujuan',
                    min: -180,
                    max: 180,
                  ),
                  onEditingComplete: _autoResolveDropoffAddress,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _dropoffAddressController,
            minLines: 2,
            maxLines: 3,
            decoration: InputDecoration(
              labelText: 'Alamat Tujuan / Tempat Turun',
              hintText: 'Pilih melalui peta atau isi alamat tujuan lengkap.',
              helperText: _isResolvingDropoff
                  ? 'Mengambil nama alamat tujuan...'
                  : null,
            ),
            validator: (value) => _validateRequiredText(value, 'Alamat tujuan'),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _dropoffNoteController,
            minLines: 2,
            maxLines: 3,
            maxLength: 500,
            decoration: const InputDecoration(
              labelText: 'Catatan / Patokan Turun',
              hintText: 'Contoh: depan minimarket atau gerbang kampus',
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPassengerCard(BuildContext context) {
    return _SectionCard(
      title: 'Biodata Penumpang',
      subtitle:
          'Satu penumpang untuk satu kursi. Data ini akan dipakai pada detail pemesanan, tiket, dan manifest perjalanan.',
      child: Column(
        children: _passengerForms
            .map(
              (passenger) => Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: TravSTheme.surfaceSoft,
                    borderRadius: BorderRadius.circular(24),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Penumpang Kursi A${passenger.seatNumber}',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              color: TravSTheme.navy,
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: passenger.nameController,
                        textCapitalization: TextCapitalization.words,
                        decoration: _passengerInputDecoration(
                          icon: Icons.person_outline_rounded,
                          label: 'Nama lengkap penumpang',
                          hint: 'Contoh: Ihsan Penumpang',
                        ),
                        validator: (value) =>
                            _validateRequiredText(value, 'Nama penumpang'),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: passenger.phoneController,
                        keyboardType: TextInputType.phone,
                        decoration: _passengerInputDecoration(
                          icon: Icons.phone_outlined,
                          label: 'Nomor HP / WhatsApp',
                          hint: 'Contoh: 081234567891',
                        ),
                        validator: (value) =>
                            _validateRequiredText(value, 'Nomor HP penumpang'),
                      ),
                    ],
                  ),
                ),
              ),
            )
            .toList(),
      ),
    );
  }

  InputDecoration _passengerInputDecoration({
    required IconData icon,
    required String label,
    required String hint,
  }) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: Icon(icon),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: TravSTheme.line),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: TravSTheme.line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: TravSTheme.orange, width: 1.4),
      ),
    );
  }

  Widget _buildBaggageCard(BuildContext context) {
    final theme = Theme.of(context);

    return _SectionCard(
      title: 'Barang Bawaan',
      subtitle: 'Ringkasan barang bawaan yang akan dibawa dalam perjalanan.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (!_hasBaggageData)
            Text(
              'Belum ada bagasi tambahan yang dicatat pada pemesanan ini.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            )
          else
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (widget.baggageWeight > 0)
                  _SummaryChip(
                    icon: Icons.scale_rounded,
                    label:
                        '${widget.baggageWeight.toStringAsFixed(0)} kg bagasi',
                  ),
                if (widget.baggageSize > 0)
                  _SummaryChip(
                    icon: Icons.straighten_rounded,
                    label:
                        '${widget.baggageLength} x ${widget.baggageWidth} cm',
                  ),
                if (widget.baggageItems > 0)
                  _SummaryChip(
                    icon: Icons.inventory_2_outlined,
                    label: '${widget.baggageItems} item bagasi',
                  ),
              ],
            ),
          // Opsi foto tetap tersedia agar penumpang bisa mendokumentasikan
          // barang bawaan sebelum pemesanan dikirim.
          ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: TravSTheme.line),
              ),
              child: Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: TravSTheme.orangeSoft,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(
                      Icons.photo_camera_back_outlined,
                      color: TravSTheme.orange,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.appSettings.baggagePhotoRequired
                              ? 'Foto barang bawaan wajib'
                              : 'Foto barang bawaan',
                          style: theme.textTheme.titleSmall?.copyWith(
                            color: TravSTheme.navy,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _uploadedBaggagePhoto != null
                              ? 'Foto sudah terunggah dan akan disimpan bersama pemesanan.'
                              : _selectedBaggagePhotos.isNotEmpty
                              ? '${_selectedBaggagePhotos.length}/3 foto dipilih.'
                              : 'Ambil foto dari kamera atau pilih dari galeri sebelum submit.',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: TravSTheme.navyMuted,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                OutlinedButton.icon(
                  onPressed:
                      _isUploadingBaggagePhoto ||
                          _selectedBaggagePhotos.length >= 3
                      ? null
                      : () => _selectBaggagePhoto(ImageSource.camera),
                  icon: const Icon(Icons.photo_camera_outlined),
                  label: const Text('Kamera'),
                ),
                OutlinedButton.icon(
                  onPressed:
                      _isUploadingBaggagePhoto ||
                          _selectedBaggagePhotos.length >= 3
                      ? null
                      : () => _selectBaggagePhoto(ImageSource.gallery),
                  icon: const Icon(Icons.photo_library_outlined),
                  label: const Text('Galeri'),
                ),
              ],
            ),
            if (_selectedBaggagePhotos.isNotEmpty) ...[
              const SizedBox(height: 12),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: List<Widget>.generate(_selectedBaggagePhotos.length, (
                  index,
                ) {
                  final photo = _selectedBaggagePhotos[index];
                  return Stack(
                    clipBehavior: Clip.none,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(16),
                        child: FutureBuilder(
                          future: photo.readAsBytes(),
                          builder: (context, snapshot) {
                            if (snapshot.hasData) {
                              return Image.memory(
                                snapshot.data!,
                                width: 76,
                                height: 76,
                                fit: BoxFit.cover,
                              );
                            }

                            return Container(
                              width: 76,
                              height: 76,
                              color: TravSTheme.surfaceMuted,
                              alignment: Alignment.center,
                              child: const Icon(
                                Icons.image_outlined,
                                color: TravSTheme.navyMuted,
                              ),
                            );
                          },
                        ),
                      ),
                      Positioned(
                        top: -8,
                        right: -8,
                        child: IconButton.filled(
                          visualDensity: VisualDensity.compact,
                          iconSize: 16,
                          style: IconButton.styleFrom(
                            backgroundColor: TravSTheme.navy,
                            foregroundColor: Colors.white,
                            minimumSize: const Size(28, 28),
                          ),
                          onPressed: () => _removeBaggagePhoto(index),
                          icon: const Icon(Icons.close_rounded),
                          tooltip: 'Hapus foto ${index + 1}',
                        ),
                      ),
                    ],
                  );
                }),
              ),
            ],
            if (_isUploadingBaggagePhoto) ...[
              const SizedBox(height: 12),
              LinearProgressIndicator(
                color: TravSTheme.orange,
                backgroundColor: TravSTheme.orangeSoft,
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _buildSummaryCard(BuildContext context) {
    final baggageRules = widget.detail.baggageRules;
    final freeWeight =
        baggageRules.freeWeightKg * widget.checkoutDraft.seatCount;
    final freeSize = baggageRules.freeSizeCm * widget.checkoutDraft.seatCount;
    final freeItems =
        baggageRules.freeItemCount * widget.checkoutDraft.seatCount;

    return _SectionCard(
      title: 'Ringkasan Pembayaran',
      subtitle:
          'Nominal di bawah ini mengikuti hasil estimasi tiket dan barang bawaan.',
      child: Column(
        children: [
          _SummaryRow(
            label: 'Subtotal tiket',
            value: 'Rp${_formatCurrency(widget.checkoutDraft.ticketSubtotal)}',
          ),
          if (widget.checkoutDraft.weightFee > 0)
            _SummaryRow(
              label:
                  'Tambahan berat (${_formatNumber(widget.checkoutDraft.baggageWeight - freeWeight)} kg)',
              value: 'Rp${_formatCurrency(widget.checkoutDraft.weightFee)}',
            ),
          if (widget.checkoutDraft.sizeFee > 0)
            _SummaryRow(
              label:
                  'Tambahan ukuran (${widget.checkoutDraft.baggageSize - freeSize} cm)',
              value: 'Rp${_formatCurrency(widget.checkoutDraft.sizeFee)}',
            ),
          if (widget.checkoutDraft.itemFee > 0)
            _SummaryRow(
              label:
                  'Tambahan item (${widget.checkoutDraft.baggageItems - freeItems} item)',
              value: 'Rp${_formatCurrency(widget.checkoutDraft.itemFee)}',
            ),
          const Divider(height: 26),
          _SummaryRow(
            label: 'Total pembayaran',
            value: 'Rp${_formatCurrency(widget.checkoutDraft.total)}',
            emphasize: true,
          ),
        ],
      ),
    );
  }

  Widget _buildCreatedBookingView(
    BuildContext context,
    TravSBookingResult booking,
  ) {
    final payment = booking.payment;
    final isPaid = payment.status == 'berhasil';
    final helperText = isPaid
        ? 'Pembayaran sudah terkonfirmasi. Tiket elektronik siap dibuka dari daftar tiket penumpang.'
        : payment.method == 'cash'
        ? 'Pemesanan berhasil tersimpan. Pembayaran tunai masih perlu dilunasi dan diverifikasi admin.'
        : payment.method == 'transfer'
        ? 'Pemesanan berhasil tersimpan. Lanjutkan transfer melalui Pakasir sebelum ${_formatDateTime(payment.expiredAt)}.'
        : 'Pemesanan berhasil tersimpan. Lanjutkan pembayaran gateway sebelum ${_formatDateTime(payment.expiredAt)}.';

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
      children: [
        Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: isPaid
                  ? const [Color(0xFF1F9D67), Color(0xFF176D4A)]
                  : const [TravSTheme.navy, TravSTheme.navySoft],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(30),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _HeaderPill(
                label: isPaid ? 'Pembayaran Berhasil' : 'Menunggu Pembayaran',
                color: isPaid ? Colors.white : TravSTheme.orange,
                textColor: isPaid ? TravSTheme.success : Colors.white,
              ),
              const SizedBox(height: 16),
              Text(
                booking.kodeBooking,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                helperText,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Colors.white.withValues(alpha: 0.92),
                ),
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  _HeaderInfo(icon: Icons.route_rounded, text: booking.rute),
                  _HeaderInfo(
                    icon: Icons.calendar_month_rounded,
                    text: _formatDateTime(booking.waktuBerangkat),
                  ),
                  _HeaderInfo(
                    icon: Icons.payments_outlined,
                    text: payment.method.toUpperCase(),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _buildGatewayPaymentCard(context, booking),
        if (!isPaid && payment.expiredAt.isNotEmpty) ...[
          const SizedBox(height: 16),
          _SectionCard(
            title: 'Waktu Pembayaran',
            subtitle:
                'Batas pembayaran mengikuti aturan ${widget.appSettings.seatLockPaymentMinutes} menit setelah pemesanan dibuat.',
            child: _CountdownPanel(
              title: 'Sisa waktu pembayaran',
              deadlineIso: payment.expiredAt,
              icon: Icons.payments_outlined,
              onExpired: () {
                _handlePaymentSessionExpired();
              },
            ),
          ),
        ],
        const SizedBox(height: 16),
        _SectionCard(
          title: 'Detail Pemesanan',
          subtitle:
              'Ringkasan pemesanan ini dipakai juga oleh daftar pemesanan, riwayat, dan tiket penumpang.',
          child: Column(
            children: [
              _SummaryRow(label: 'Status pemesanan', value: booking.status),
              _SummaryRow(label: 'Status pembayaran', value: payment.status),
              _SummaryRow(
                label: 'Metode pembayaran',
                value: payment.method.toUpperCase(),
              ),
              _SummaryRow(
                label: 'Jumlah kursi',
                value: '${booking.jumlahKursi} kursi',
              ),
              _SummaryRow(
                label: 'Batas pembayaran',
                value: _formatDateTime(booking.batasPembayaran),
              ),
              const Divider(height: 26),
              _SummaryRow(
                label: 'Total tagihan',
                value: 'Rp${_formatCurrency(booking.totalHarga)}',
                emphasize: true,
              ),
            ],
          ),
        ),
        if (_errorMessage != null) ...[
          const SizedBox(height: 16),
          _MessageBanner(message: _errorMessage!),
        ],
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: () => Navigator.of(context).pop(true),
          icon: const Icon(Icons.check_circle_outline_rounded),
          label: Text(isPaid ? 'Kembali ke Tiket' : 'Simpan dan Kembali'),
        ),
      ],
    );
  }

  Widget _buildGatewayPaymentCard(
    BuildContext context,
    TravSBookingResult booking,
  ) {
    final payment = booking.payment;
    final providerLabel = payment.provider.trim().isEmpty
        ? 'Pakasir'
        : payment.provider.toUpperCase();
    final usesGateway =
        payment.usesGateway || payment.provider.trim().isNotEmpty;
    final totalPayment = payment.totalPayment > 0
        ? payment.totalPayment
        : booking.totalHarga;
    final baseAmount = payment.amount > 0 ? payment.amount : booking.totalHarga;

    if (payment.method == 'qris') {
      return _SectionCard(
        title: usesGateway ? 'Pembayaran Gateway' : 'Detail Pembayaran',
        subtitle: payment.hasCheckoutUrl
            ? 'Tekan tombol di bawah untuk membuka halaman pembayaran Pakasir dan menyelesaikan transaksi.'
            : 'Scan QRIS di bawah menggunakan e-wallet atau mobile banking untuk menyelesaikan pembayaran.',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _GatewayBadgeRow(
              title: 'QRIS $providerLabel',
              subtitle:
                  'Project ${payment.project.isEmpty ? 'travs' : payment.project}',
            ),
            const SizedBox(height: 14),

            // Jika Pakasir memberi tautan pembayaran, arahkan pengguna ke sana
            // supaya transaksi diproses melalui halaman penyedia pembayaran.
            if (payment.hasCheckoutUrl) ...[
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => _openPaymentCheckout(payment.checkoutUrl),
                  icon: const Icon(Icons.open_in_browser_rounded),
                  label: const Text('Bayar via Pakasir'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: TravSTheme.navy,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    textStyle: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 14),
            ],

            // Kode QR tetap ditampilkan jika nomor pembayaran QRIS tersedia.
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: TravSTheme.line),
              ),
              child: Column(
                children: [
                  if (payment.hasPaymentNumber) ...[
                    Container(
                      width: 210,
                      height: 210,
                      decoration: BoxDecoration(
                        color: TravSTheme.surfaceSoft,
                        borderRadius: BorderRadius.circular(24),
                      ),
                      child: Center(
                        child: QrImageView(
                          data: payment.paymentNumber,
                          version: QrVersions.auto,
                          size: 176,
                          backgroundColor: Colors.white,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  Text(
                    payment.hasCheckoutUrl
                        ? 'Atau scan QRIS di atas dari e-wallet'
                        : 'Scan QRIS untuk melanjutkan pembayaran',
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Status: ${payment.gatewayStatus.isEmpty ? 'menunggu pembayaran' : payment.gatewayStatus}',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navyMuted,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _SummaryRow(
              label: 'Nominal transaksi',
              value: 'Rp${_formatCurrency(baseAmount)}',
            ),
            if (payment.fee > 0)
              _SummaryRow(
                label: 'Biaya layanan',
                value: 'Rp${_formatCurrency(payment.fee)}',
              ),
            _SummaryRow(
              label: 'Total pembayaran',
              value: 'Rp${_formatCurrency(totalPayment)}',
              emphasize: true,
            ),
            const SizedBox(height: 14),
            _InstructionList(
              steps: payment.hasCheckoutUrl
                  ? const [
                      'Tekan tombol "Bayar via Pakasir" untuk membuka halaman pembayaran.',
                      'Selesaikan pembayaran di halaman Pakasir yang terbuka.',
                      'Kembali ke aplikasi setelah pembayaran berhasil.',
                    ]
                  : const [
                      'Buka aplikasi e-wallet atau mobile banking.',
                      'Scan QRIS yang tampil pada layar pembayaran.',
                      'Pastikan nominal sesuai sebelum konfirmasi pembayaran.',
                    ],
            ),
          ],
        ),
      );
    }

    if (payment.method == 'transfer') {
      return _SectionCard(
        title: usesGateway ? 'Transfer via Pakasir' : 'Instruksi Transfer',
        subtitle: payment.hasCheckoutUrl
            ? 'Tekan tombol bayar untuk membuka halaman Pakasir, lalu pilih bank atau virtual account yang tersedia.'
            : 'Transfer ditata seperti halaman eksternal agar nomor pembayaran dan total tagihan langsung terbaca.',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _GatewayBadgeRow(
              title: 'Transfer via $providerLabel',
              subtitle: payment.hasCheckoutUrl
                  ? 'Pilih bank di halaman Pakasir'
                  : 'Nomor pembayaran / virtual account',
            ),
            const SizedBox(height: 14),
            if (payment.hasCheckoutUrl) ...[
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => _openPaymentCheckout(payment.checkoutUrl),
                  icon: const Icon(Icons.open_in_browser_rounded),
                  label: const Text('Bayar via Pakasir'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: TravSTheme.navy,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    textStyle: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 14),
            ] else
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: TravSTheme.surfaceSoft,
                  borderRadius: BorderRadius.circular(22),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Nomor Pembayaran',
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: TravSTheme.navyMuted,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      payment.hasPaymentNumber
                          ? payment.paymentNumber
                          : booking.kodeBooking,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: TravSTheme.navy,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            const SizedBox(height: 14),
            _SummaryRow(
              label: 'Nominal transaksi',
              value: 'Rp${_formatCurrency(baseAmount)}',
            ),
            if (payment.fee > 0)
              _SummaryRow(
                label: 'Biaya layanan',
                value: 'Rp${_formatCurrency(payment.fee)}',
              ),
            _SummaryRow(
              label: 'Total pembayaran',
              value: 'Rp${_formatCurrency(totalPayment)}',
              emphasize: true,
            ),
            _SummaryRow(
              label: 'Batas pembayaran',
              value: _formatDateTime(payment.expiredAt),
            ),
            const SizedBox(height: 14),
            _InstructionList(
              steps: payment.hasCheckoutUrl
                  ? const [
                      'Buka halaman Pakasir dari tombol bayar.',
                      'Pilih bank atau virtual account yang tersedia di Pakasir.',
                      'Selesaikan pembayaran sesuai nominal yang ditampilkan.',
                    ]
                  : const [
                      'Salin nomor pembayaran atau virtual account yang tersedia.',
                      'Lakukan transfer sesuai nominal total pembayaran.',
                      'Simpan bukti transfer sampai status pembayaran berubah.',
                    ],
            ),
          ],
        ),
      );
    }

    return _SectionCard(
      title: 'Pembayaran Tunai',
      subtitle:
          'Pembayaran tunai tetap ditampilkan seperti layar instruksi eksternal agar langkah penumpang jelas sebelum berangkat.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _GatewayBadgeRow(
            title: 'Bayar Tunai',
            subtitle: 'Pelunasan melalui admin / petugas travel',
          ),
          const SizedBox(height: 14),
          _SummaryRow(
            label: 'Total yang harus dibayar',
            value: 'Rp${_formatCurrency(totalPayment)}',
            emphasize: true,
          ),
          if (payment.expiredAt.isNotEmpty)
            _SummaryRow(
              label: 'Batas konfirmasi',
              value: _formatDateTime(payment.expiredAt),
            ),
          const SizedBox(height: 14),
          const _InstructionList(
            steps: [
              'Siapkan nominal pembayaran sesuai total tagihan.',
              'Tunjukkan kode pemesanan saat melakukan pelunasan.',
              'Tiket akan aktif setelah admin mengonfirmasi pembayaran tunai.',
            ],
          ),
        ],
      ),
    );
  }
}

class _CheckoutFormView extends StatelessWidget {
  const _CheckoutFormView({
    required this.formKey,
    required this.header,
    required this.paymentMethodCard,
    required this.pickupCard,
    required this.dropoffCard,
    required this.passengerCard,
    required this.baggageCard,
    required this.summaryCard,
    required this.errorMessage,
    required this.isSubmitting,
    required this.onSubmit,
  });

  final GlobalKey<FormState> formKey;
  final Widget header;
  final Widget paymentMethodCard;
  final Widget pickupCard;
  final Widget dropoffCard;
  final Widget passengerCard;
  final Widget baggageCard;
  final Widget summaryCard;
  final String? errorMessage;
  final bool isSubmitting;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        children: [
          header,
          const SizedBox(height: 16),
          paymentMethodCard,
          const SizedBox(height: 16),
          pickupCard,
          const SizedBox(height: 16),
          dropoffCard,
          const SizedBox(height: 16),
          passengerCard,
          const SizedBox(height: 16),
          baggageCard,
          const SizedBox(height: 16),
          summaryCard,
          if (errorMessage != null) ...[
            const SizedBox(height: 16),
            _MessageBanner(message: errorMessage!),
          ],
          const SizedBox(height: 18),
          ElevatedButton.icon(
            onPressed: isSubmitting ? null : onSubmit,
            icon: Icon(
              isSubmitting ? Icons.sync_rounded : Icons.lock_clock_outlined,
            ),
            label: Text(
              isSubmitting
                  ? 'Menyimpan Pemesanan...'
                  : 'Buat Pemesanan Sekarang',
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  final String title;
  final String subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: theme.textTheme.titleLarge?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              subtitle,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navyMuted,
              ),
            ),
            const SizedBox(height: 18),
            child,
          ],
        ),
      ),
    );
  }
}

class _PaymentMethodTile extends StatelessWidget {
  const _PaymentMethodTile({
    required this.option,
    required this.isSelected,
    required this.onTap,
  });

  final _PaymentMethodOption option;
  final bool isSelected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(22),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: isSelected ? TravSTheme.orangeSoft : TravSTheme.surfaceSoft,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(
            color: isSelected ? TravSTheme.orange : TravSTheme.line,
            width: isSelected ? 1.4 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: isSelected
                    ? TravSTheme.orange.withValues(alpha: 0.16)
                    : Colors.white,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                option.icon,
                color: isSelected ? TravSTheme.orange : TravSTheme.navy,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    option.label,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: TravSTheme.navy,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    option.helper,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: TravSTheme.navyMuted,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              isSelected
                  ? Icons.radio_button_checked_rounded
                  : Icons.radio_button_off_rounded,
              color: isSelected ? TravSTheme.orange : TravSTheme.navyMuted,
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.label,
    required this.value,
    this.emphasize = false,
  });

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: emphasize ? TravSTheme.navy : TravSTheme.navyMuted,
                fontWeight: emphasize ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
          ),
          Text(
            value,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: emphasize ? FontWeight.w800 : FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryChip extends StatelessWidget {
  const _SummaryChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: TravSTheme.surfaceSoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: TravSTheme.line),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18, color: TravSTheme.orange),
          const SizedBox(width: 8),
          Text(
            label,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _GatewayBadgeRow extends StatelessWidget {
  const _GatewayBadgeRow({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: TravSTheme.orangeSoft,
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Icon(
            Icons.open_in_new_rounded,
            color: TravSTheme.orange,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InstructionList extends StatelessWidget {
  const _InstructionList({required this.steps});

  final List<String> steps;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Petunjuk',
          style: theme.textTheme.titleSmall?.copyWith(
            color: TravSTheme.navy,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 8),
        for (var index = 0; index < steps.length; index++) ...[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 24,
                height: 24,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: TravSTheme.surfaceSoft,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  '${index + 1}',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  steps[index],
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                  ),
                ),
              ),
            ],
          ),
          if (index != steps.length - 1) const SizedBox(height: 8),
        ],
      ],
    );
  }
}

class _MessageBanner extends StatelessWidget {
  const _MessageBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F1),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFF3B6B6)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.only(top: 2),
            child: Icon(Icons.error_outline_rounded, color: TravSTheme.danger),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: TravSTheme.danger,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HeaderPill extends StatelessWidget {
  const _HeaderPill({
    required this.label,
    this.color = Colors.white,
    this.textColor = TravSTheme.navy,
  });

  final String label;
  final Color color;
  final Color textColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: color == Colors.white ? 1 : 0.18),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(color: textColor, fontWeight: FontWeight.w800),
      ),
    );
  }
}

class _HeaderInfo extends StatelessWidget {
  const _HeaderInfo({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18, color: Colors.white),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              text,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CountdownPanel extends StatefulWidget {
  const _CountdownPanel({
    required this.title,
    required this.deadlineIso,
    required this.icon,
    this.onDark = false,
    this.onExpired,
  });

  final String title;
  final String deadlineIso;
  final IconData icon;
  final bool onDark;
  final VoidCallback? onExpired;

  @override
  State<_CountdownPanel> createState() => _CountdownPanelState();
}

class _CountdownPanelState extends State<_CountdownPanel> {
  Timer? _timer;
  late Duration _remaining;
  bool _expiredCallbackSent = false;

  @override
  void initState() {
    super.initState();
    _remaining = _calculateRemaining();
    _notifyExpiredIfNeeded();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) {
        setState(() {
          _remaining = _calculateRemaining();
        });
        _notifyExpiredIfNeeded();
      }
    });
  }

  @override
  void didUpdateWidget(covariant _CountdownPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.deadlineIso != widget.deadlineIso) {
      _expiredCallbackSent = false;
      _remaining = _calculateRemaining();
      _notifyExpiredIfNeeded();
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Duration _calculateRemaining() {
    final deadline = DateTime.tryParse(widget.deadlineIso)?.toLocal();
    if (deadline == null) {
      return Duration.zero;
    }

    final difference = deadline.difference(DateTime.now());
    return difference.isNegative ? Duration.zero : difference;
  }

  void _notifyExpiredIfNeeded() {
    if (_expiredCallbackSent || _remaining != Duration.zero) {
      return;
    }

    _expiredCallbackSent = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        widget.onExpired?.call();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final background = widget.onDark
        ? Colors.white.withValues(alpha: 0.12)
        : TravSTheme.surfaceSoft;
    final foreground = widget.onDark ? Colors.white : TravSTheme.navy;
    final muted = widget.onDark
        ? Colors.white.withValues(alpha: 0.76)
        : TravSTheme.navyMuted;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(20),
        border: widget.onDark ? null : Border.all(color: TravSTheme.line),
      ),
      child: Row(
        children: [
          Icon(
            widget.icon,
            color: widget.onDark ? Colors.white : TravSTheme.orange,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.title,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: muted,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _formatDuration(_remaining),
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: foreground,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
          ),
          Text(
            _remaining == Duration.zero ? 'Berakhir' : 'Aktif',
            style: theme.textTheme.labelLarge?.copyWith(
              color: _remaining == Duration.zero
                  ? TravSTheme.danger
                  : (widget.onDark ? Colors.white : TravSTheme.success),
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _PassengerFormController {
  _PassengerFormController({
    required this.seatNumber,
    required String name,
    required String phone,
  }) : nameController = TextEditingController(text: name),
       phoneController = TextEditingController(text: phone);

  final int seatNumber;
  final TextEditingController nameController;
  final TextEditingController phoneController;

  void dispose() {
    nameController.dispose();
    phoneController.dispose();
  }
}

class _PaymentMethodOption {
  const _PaymentMethodOption({
    required this.key,
    required this.label,
    required this.helper,
    required this.icon,
  });

  final String key;
  final String label;
  final String helper;
  final IconData icon;
}

String _formatDateTime(String isoValue) {
  if (isoValue.isEmpty) {
    return '-';
  }

  final parsed = DateTime.tryParse(isoValue)?.toLocal();
  if (parsed == null) {
    return '-';
  }

  return DateFormat('dd MMM yyyy, HH:mm', 'id_ID').format(parsed);
}

String _formatDuration(Duration duration) {
  final hours = duration.inHours;
  final minutes = duration.inMinutes.remainder(60).toString().padLeft(2, '0');
  final seconds = duration.inSeconds.remainder(60).toString().padLeft(2, '0');

  if (hours > 0) {
    return '${hours.toString().padLeft(2, '0')}:$minutes:$seconds';
  }

  return '$minutes:$seconds';
}

String _formatNumber(num value) {
  return NumberFormat.decimalPattern(
    'id_ID',
  ).format(value.clamp(0, double.infinity));
}

String _formatCurrency(double value) {
  return NumberFormat.decimalPattern('id_ID').format(value);
}
