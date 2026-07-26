import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
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
import '../ticket/travs_ticket_detail_page.dart';

enum _CheckoutLocationTab { pickup, dropoff }

enum _PaymentFamily { qris, virtualAccount, cash }

enum _PaymentDisplayStatus { pending, paid, expired, failed, cancelled }

class TravSCheckoutDraft {
  const TravSCheckoutDraft({
    required this.seatCount,
    required this.passengerCount,
    required this.baggageMethod,
    required this.baggagePlacement,
    required this.baggageSizeCategory,
    required this.largeBaggageType,
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
  final int passengerCount;
  final String baggageMethod;
  final String baggagePlacement;
  final String baggageSizeCategory;
  final String largeBaggageType;
  final double baggageWeight;
  final int baggageSize;
  final int baggageLength;
  final int baggageWidth;
  final int baggageItems;
  final int ticketSubtotal;
  final int weightFee;
  final int sizeFee;
  final int itemFee;

  int get total => ticketSubtotal;
}

class TravSPaymentPreviewPage extends StatefulWidget {
  const TravSPaymentPreviewPage({
    required this.detail,
    required this.appSettings,
    required this.checkoutDraft,
    required this.selectedRole,
    required this.session,
    required this.selectedSeatNumbers,
    required this.baggagePlacement,
    required this.baggageWeight,
    required this.baggageItems,
    required this.lockExpiresAt,
    this.initialBooking,
    super.key,
  });

  final TravSScheduleDetail detail;
  final AppSettings appSettings;
  final TravSCheckoutDraft checkoutDraft;
  final AppRolePreview selectedRole;
  final TravSUserSession session;
  final List<int> selectedSeatNumbers;
  final String baggagePlacement;
  final double baggageWeight;
  final int baggageItems;
  final String lockExpiresAt;

  /// Booking awal dipakai saat halaman pembayaran dibuka kembali atau diuji.
  final TravSBookingResult? initialBooking;

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
  final TextEditingController _baggageDescriptionController =
      TextEditingController();

  late final List<_PassengerFormController> _passengerForms;
  late final List<_PaymentMethodOption> _paymentOptions;
  _PaymentFamily? _selectedPaymentFamily;
  String? _selectedVirtualAccount;
  bool _isCreatingPayment = false;
  bool _isSubmitting = false;
  bool _isResolvingPickup = false;
  bool _isResolvingDropoff = false;
  bool _isUploadingBaggagePhoto = false;
  bool _isPickingBaggagePhoto = false;
  bool _isRefreshingPayment = false;
  // Penjaga agar tombol menuju halaman tiket tidak bisa dispam sehingga
  // halaman tiket tidak menumpuk berlapis (double-push).
  bool _isOpeningTicketPage = false;
  bool _isLeavingPage = false;
  bool _showLeaveWarningOnResume = false;
  bool _isFormSessionExpired = false;
  bool _isPaymentSessionExpired = false;
  bool _isFormExpiryDialogVisible = false;
  bool _isPaymentExpiryDialogVisible = false;
  bool _hasBaggage = false;
  bool _hasFormBeenEdited = false;
  _CheckoutLocationTab _selectedLocationTab = _CheckoutLocationTab.pickup;
  String? _errorMessage;
  Future<TravSBookingResult?>? _paymentRefreshFuture;
  Timer? _paymentResumeTimer;
  late final String _stableFormLockDeadlineIso;
  TravSBookingResult? _createdBooking;
  final List<XFile> _selectedBaggagePhotos = <XFile>[];
  TravSUploadedBaggagePhoto? _uploadedBaggagePhoto;
  List<TravSUploadedBaggagePhoto> _uploadedBaggagePhotos =
      <TravSUploadedBaggagePhoto>[];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _stableFormLockDeadlineIso = _resolveStableFormLockDeadlineIso();
    _paymentOptions = _resolvePaymentOptions(
      widget.detail.paymentMethods,
      cashEnabled: widget.appSettings.paymentCashEnabled,
    );
    _createdBooking = widget.initialBooking;

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

  String _resolveStableFormLockDeadlineIso() {
    final parsedDeadline = DateTime.tryParse(widget.lockExpiresAt)?.toLocal();
    final maxMinutes = widget.detail.bookingRules.seatLockFormMinutes;
    if (parsedDeadline == null || maxMinutes <= 0) {
      return widget.lockExpiresAt;
    }

    // Deadline form distabilkan sekali saat halaman dibuat. Jika validasi form
    // memicu rebuild, countdown tidak ikut maju ulang dari waktu terbaru.
    final maxDeadline = DateTime.now().add(Duration(minutes: maxMinutes));
    final effectiveDeadline = parsedDeadline.isAfter(maxDeadline)
        ? maxDeadline
        : parsedDeadline;
    return effectiveDeadline.toIso8601String();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _paymentResumeTimer?.cancel();
    _pickupLatitudeController.dispose();
    _pickupLongitudeController.dispose();
    _pickupAddressController.dispose();
    _pickupNoteController.dispose();
    _dropoffLatitudeController.dispose();
    _dropoffLongitudeController.dispose();
    _dropoffAddressController.dispose();
    _dropoffNoteController.dispose();
    _baggageDescriptionController.dispose();
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
      if (_hasFormBeenEdited) return;

      setState(() {
        _restoreEmptyController(
          _pickupLatitudeController,
          draft['pickup_latitude'],
        );
        _restoreEmptyController(
          _pickupLongitudeController,
          draft['pickup_longitude'],
        );
        _restoreEmptyController(
          _pickupAddressController,
          draft['pickup_address'],
        );
        _restoreEmptyController(_pickupNoteController, draft['pickup_note']);
        _restoreEmptyController(
          _dropoffLatitudeController,
          draft['dropoff_latitude'],
        );
        _restoreEmptyController(
          _dropoffLongitudeController,
          draft['dropoff_longitude'],
        );
        _restoreEmptyController(
          _dropoffAddressController,
          draft['dropoff_address'],
        );
        _restoreEmptyController(_dropoffNoteController, draft['dropoff_note']);
        _hasBaggage = draft['has_baggage'] as bool? ?? _hasBaggage;
        _baggageDescriptionController.text =
            draft['baggage_description'] as String? ??
            _baggageDescriptionController.text;

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
      _baggageDescriptionController,
      for (final passenger in _passengerForms) ...[
        passenger.nameController,
        passenger.phoneController,
      ],
    ];

    for (final controller in controllers) {
      controller.addListener(() {
        _hasFormBeenEdited = true;
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
          'has_baggage': _hasBaggage,
          'baggage_description': _baggageDescriptionController.text,
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
      _paymentResumeTimer?.cancel();
      _paymentResumeTimer = Timer(const Duration(milliseconds: 800), () {
        if (mounted) {
          _refreshCreatedBookingStatus(showSuccessMessage: true);
        }
      });
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

  bool get _hasBaggageData => _hasBaggage;

  bool get _requiresBaggagePhoto => false;

  String? get _baggageInputError {
    if (_hasBaggage && _baggageDescriptionController.text.trim().isEmpty) {
      return 'Nama atau keterangan barang bawaan wajib diisi.';
    }

    return null;
  }

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
          // Tujuan rute menjadi pusat awal hanya jika belum ada titik tersimpan.
          initialLocationQuery: widget.detail.schedule.tujuan,
          title: 'Pilih Titik Turun',
          locationLabel: 'titik turun',
          showCurrentLocationButton: false,
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
      if (_requiresBaggagePhoto) {
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
    if (_isSubmitting) {
      return;
    }
    if (!await _ensureFormSessionActive() || !mounted) {
      return;
    }

    final baggageInputError = _baggageInputError;
    if (baggageInputError != null) {
      setState(() {
        _errorMessage = baggageInputError;
      });
      return;
    }

    final form = _formKey.currentState;
    if (form == null) {
      return;
    }

    final isFormValid = form.validate();
    if (!isFormValid) {
      final invalidLocationTab = !_isPickupInputValid
          ? _CheckoutLocationTab.pickup
          : !_isDropoffInputValid
          ? _CheckoutLocationTab.dropoff
          : null;
      setState(() {
        if (invalidLocationTab != null) {
          _selectedLocationTab = invalidLocationTab;
          _errorMessage = invalidLocationTab == _CheckoutLocationTab.pickup
              ? 'Lengkapi data pada tab Titik Jemput.'
              : 'Lengkapi data pada tab Titik Tujuan / Turun.';
        } else {
          _errorMessage = 'Periksa kembali data form yang belum lengkap.';
        }
      });
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
      if (_requiresBaggagePhoto && baggagePhotoUrls.isEmpty) {
        return;
      }
      // Upload foto dapat memakan waktu. Deadline wajib diperiksa kembali tepat
      // sebelum request pembuatan pemesanan dikirim ke backend.
      if (!await _ensureFormSessionActive()) {
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
          pickupNote: _pickupNoteController.text.trim(),
          dropoffLatitude: double.parse(_dropoffLatitudeController.text.trim()),
          dropoffLongitude: double.parse(
            _dropoffLongitudeController.text.trim(),
          ),
          dropoffAddress: _dropoffAddressController.text.trim(),
          dropoffNote: _dropoffNoteController.text.trim(),
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
    } on TravSApiException catch (error) {
      if (_isExpiredFormSessionMessage(error.message)) {
        await _handleFormSessionExpired();
      } else if (mounted) {
        setState(() {
          _errorMessage = error.message;
        });
      }
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

  bool get _isPickupInputValid =>
      _isCoordinateInputValid(
        _pickupLatitudeController.text,
        min: -90,
        max: 90,
      ) &&
      _isCoordinateInputValid(
        _pickupLongitudeController.text,
        min: -180,
        max: 180,
      ) &&
      _pickupAddressController.text.trim().isNotEmpty &&
      _pickupNoteController.text.trim().isNotEmpty;

  bool get _isDropoffInputValid =>
      _isCoordinateInputValid(
        _dropoffLatitudeController.text,
        min: -90,
        max: 90,
      ) &&
      _isCoordinateInputValid(
        _dropoffLongitudeController.text,
        min: -180,
        max: 180,
      ) &&
      _dropoffAddressController.text.trim().isNotEmpty &&
      _dropoffNoteController.text.trim().isNotEmpty;

  bool _isCoordinateInputValid(
    String value, {
    required double min,
    required double max,
  }) {
    final parsed = double.tryParse(value.trim());
    return parsed != null && parsed >= min && parsed <= max;
  }

  void _restoreEmptyController(
    TextEditingController controller,
    Object? value,
  ) {
    if (controller.text.isEmpty && value is String) {
      controller.text = value;
    }
  }

  Future<TravSBookingResult?> _refreshCreatedBookingStatus({
    bool showSuccessMessage = false,
  }) {
    final activeRefresh = _paymentRefreshFuture;
    if (activeRefresh != null) {
      return activeRefresh;
    }

    final refreshFuture = _performCreatedBookingStatusRefresh(
      showSuccessMessage: showSuccessMessage,
    );
    _paymentRefreshFuture = refreshFuture;
    return refreshFuture.whenComplete(() {
      if (identical(_paymentRefreshFuture, refreshFuture)) {
        _paymentRefreshFuture = null;
      }
    });
  }

  Future<TravSBookingResult?> _performCreatedBookingStatusRefresh({
    required bool showSuccessMessage,
  }) async {
    final booking = _createdBooking;
    if (booking == null) {
      return null;
    }

    if (booking.payment.isAwaitingMethodSelection ||
        !booking.payment.usesGateway) {
      try {
        final refreshedBooking = await _apiService.loadPassengerBookingDetail(
          token: widget.session.token,
          bookingId: booking.id,
        );
        if (mounted) setState(() => _createdBooking = refreshedBooking);
        return refreshedBooking;
      } catch (_) {
        return null;
      }
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
        return refreshedBooking;
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
      return refreshedBooking;
    } on TravSApiException catch (error) {
      if (mounted) {
        setState(() {
          _errorMessage = error.message;
        });
      }
      return null;
    } catch (_) {
      if (mounted) {
        setState(() {
          _errorMessage =
              'Status pembayaran belum dapat diperbarui. Silakan coba lagi beberapa saat.';
        });
      }
      return null;
    } finally {
      if (mounted) {
        setState(() {
          _isRefreshingPayment = false;
        });
      }
    }
  }

  Future<void> _openRelatedTicket(TravSBookingResult booking) async {
    // Cegah klik berulang membuka halaman tiket berlapis-lapis. Penjaga ini
    // penting karena ada proses menunggu (await) pengecekan pembayaran sebelum
    // halaman tiket dibuka, sehingga tombol masih bisa ditekan berkali-kali.
    if (_isOpeningTicketPage) {
      return;
    }

    _isOpeningTicketPage = true;
    try {
      if (!booking.isPaid) {
        await _refreshCreatedBookingStatus(showSuccessMessage: true);
      }

      if (!mounted) {
        return;
      }

      final refreshedBooking = _createdBooking ?? booking;
      if (!refreshedBooking.isPaid) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Pembayaran masih menunggu konfirmasi. Periksa kembali beberapa saat lagi.',
            ),
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }

      await Navigator.of(context).push<bool>(
        MaterialPageRoute<bool>(
          builder: (_) => TravSTicketDetailPage(
            session: widget.session,
            bookingId: refreshedBooking.id,
          ),
        ),
      );
    } finally {
      _isOpeningTicketPage = false;
    }
  }

  List<TravSBaggagePayload> _buildBaggagePayload(
    List<String> baggagePhotoUrls,
  ) {
    if (!_hasBaggageData) {
      return const <TravSBaggagePayload>[];
    }

    return <TravSBaggagePayload>[
      TravSBaggagePayload(
        deskripsiItem: _baggageDescriptionController.text.trim(),
        // Field tarif lama dikosongkan; backend hanya menyimpan keterangan dan
        // foto barang bawaan untuk kebutuhan penumpang serta pengemudi.
        metodeHitung: null,
        posisiBarang: 'masuk_bagasi',
        beratKg: null,
        ukuranCm: null,
        panjangCm: null,
        lebarCm: null,
        jumlahItem: 1,
        fotoUrl: baggagePhotoUrls.isNotEmpty ? baggagePhotoUrls.first : null,
        fotoUrls: baggagePhotoUrls.take(3).toList(),
      ),
    ];
  }

  List<_PaymentMethodOption> _resolvePaymentOptions(
    TravSPaymentMethods methods, {
    required bool cashEnabled,
  }) {
    return methods.activeOptions
        .where((method) => method.family != 'cash' || cashEnabled)
        .map(
          (method) => _PaymentMethodOption(
            key: method.code,
            label: method.label,
            family: method.family,
          ),
        )
        .toList();
  }

  String? get _selectedPaymentMethod => switch (_selectedPaymentFamily) {
    _PaymentFamily.qris => 'qris',
    _PaymentFamily.virtualAccount => _selectedVirtualAccount,
    _PaymentFamily.cash => 'cash',
    null => null,
  };

  Future<void> _createPayment(TravSBookingResult booking) async {
    if (_isCreatingPayment) return;

    final paymentMethod = _selectedPaymentMethod;
    if (paymentMethod == null) {
      setState(() {
        _errorMessage = _selectedPaymentFamily == _PaymentFamily.virtualAccount
            ? 'Pilih bank Virtual Account terlebih dahulu.'
            : 'Pilih metode pembayaran terlebih dahulu.';
      });
      return;
    }

    setState(() {
      _isCreatingPayment = true;
      _errorMessage = null;
    });

    try {
      final updated = await _apiService.chooseBookingPaymentMethod(
        token: widget.session.token,
        bookingId: booking.id,
        paymentMethod: paymentMethod,
      );
      if (!mounted) return;
      setState(() => _createdBooking = updated);
    } on TravSApiException catch (error) {
      if (!mounted) return;
      setState(() => _errorMessage = error.message);
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _errorMessage =
            'Pembayaran belum dapat dibuat. Periksa koneksi lalu coba lagi.';
      });
    } finally {
      if (mounted) setState(() => _isCreatingPayment = false);
    }
  }

  Widget _buildChoosePaymentCard(
    BuildContext context,
    TravSBookingResult booking,
  ) {
    final vaOptions = _paymentOptions
        .where((option) => option.family == 'va')
        .toList();
    final families = <_PaymentFamily>{
      if (_paymentOptions.any((option) => option.family == 'qris'))
        _PaymentFamily.qris,
      if (vaOptions.isNotEmpty) _PaymentFamily.virtualAccount,
      if (_paymentOptions.any((option) => option.family == 'cash'))
        _PaymentFamily.cash,
    };

    // Card tetap memberi pesan jika opsi backend kosong. SegmentedButton tidak
    // boleh dibuat tanpa pilihan karena dapat membuat body gagal dirender.
    if (families.isEmpty) {
      return const _SectionCard(
        title: 'Rincian Pembayaran',
        subtitle: '',
        child: _PaymentDataError(
          message:
              'Metode pembayaran belum dapat dimuat. Kembali ke detail jadwal lalu coba lagi.',
        ),
      );
    }

    return _SectionCard(
      title: 'Rincian Pembayaran',
      subtitle: '',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SummaryRow(
            label: 'Total sementara',
            value: 'Rp${_formatCurrency(booking.totalHarga)}',
            emphasize: true,
          ),
          _SummaryRow(
            label: 'Harga tiket',
            value: 'Rp${_formatCurrency(booking.totalHarga)}',
          ),
          const Divider(height: 28),
          Text(
            'Pilih Metode Pembayaran',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 14),
          LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxWidth < 420;
              return SegmentedButton<_PaymentFamily>(
                segments: [
                  if (families.contains(_PaymentFamily.qris))
                    const ButtonSegment(
                      value: _PaymentFamily.qris,
                      label: Text('QRIS'),
                      icon: Icon(Icons.qr_code_2_rounded),
                    ),
                  if (families.contains(_PaymentFamily.virtualAccount))
                    ButtonSegment(
                      value: _PaymentFamily.virtualAccount,
                      label: Text(
                        compact ? 'VA' : 'Virtual Account',
                        textAlign: TextAlign.center,
                      ),
                      icon: const Icon(Icons.account_balance_rounded),
                    ),
                  if (families.contains(_PaymentFamily.cash))
                    const ButtonSegment(
                      value: _PaymentFamily.cash,
                      label: Text('Tunai'),
                      icon: Icon(Icons.payments_rounded),
                    ),
                ],
                selected: _selectedPaymentFamily == null
                    ? const {}
                    : {_selectedPaymentFamily!},
                showSelectedIcon: false,
                emptySelectionAllowed: true,
                multiSelectionEnabled: false,
                // SegmentedButton vertikal tidak boleh berada dalam ListView
                // karena tinggi tak terbatas dapat membuat layout gagal.
                direction: Axis.horizontal,
                expandedInsets: EdgeInsets.zero,
                onSelectionChanged: _isCreatingPayment
                    ? null
                    : (selection) {
                        setState(() {
                          // Pilihan kosong dibaca aman saat metode aktif ditekan
                          // kembali oleh penumpang.
                          _selectedPaymentFamily = selection.isEmpty
                              ? null
                              : selection.first;
                          if (_selectedPaymentFamily !=
                              _PaymentFamily.virtualAccount) {
                            _selectedVirtualAccount = null;
                          }
                          _errorMessage = null;
                        });
                      },
              );
            },
          ),
          if (_selectedPaymentFamily == _PaymentFamily.virtualAccount) ...[
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _selectedVirtualAccount,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Pilih bank',
                prefixIcon: Icon(Icons.account_balance_rounded),
              ),
              items: vaOptions
                  .map(
                    (option) => DropdownMenuItem(
                      value: option.key,
                      child: Text(
                        option.label.replaceFirst('Virtual Account ', ''),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  )
                  .toList(),
              onChanged: _isCreatingPayment
                  ? null
                  : (value) {
                      setState(() {
                        _selectedVirtualAccount = value;
                        _errorMessage = null;
                      });
                    },
            ),
          ],
          if (_errorMessage != null) ...[
            const SizedBox(height: 14),
            // Pesan ditempatkan dekat pilihan agar langsung terlihat.
            _MessageBanner(message: _errorMessage!),
          ],
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _isCreatingPayment
                  ? null
                  : () => _createPayment(booking),
              child: _isCreatingPayment
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Buat Pembayaran'),
            ),
          ),
        ],
      ),
    );
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

  String? _validatePassengerPhone(String? value) {
    final raw = value?.trim() ?? '';
    if (raw.isEmpty) {
      return 'Nomor HP penumpang wajib diisi.';
    }
    if (!RegExp(r'^[0-9]{11,12}$').hasMatch(raw)) {
      return 'Nomor HP penumpang harus terdiri dari 11 sampai 12 digit.';
    }
    return null;
  }

  bool _isDeadlineActive(String deadlineIso) {
    final deadline = DateTime.tryParse(deadlineIso)?.toLocal();
    return deadline != null && deadline.isAfter(DateTime.now());
  }

  Future<bool> _ensureFormSessionActive() async {
    if (!_isFormSessionExpired &&
        _isDeadlineActive(_stableFormLockDeadlineIso)) {
      return true;
    }

    await _handleFormSessionExpired();
    return false;
  }

  bool _isExpiredFormSessionMessage(String message) {
    final normalized = message.toLowerCase();
    return normalized.contains('sesi konfirmasi pemesanan') &&
        normalized.contains('berakhir');
  }

  Future<void> _handleFormSessionExpired() async {
    if (_createdBooking != null || !mounted || _isFormExpiryDialogVisible) {
      return;
    }

    setState(() {
      _isFormSessionExpired = true;
      _isSubmitting = false;
      _isFormExpiryDialogVisible = true;
      _errorMessage = null;
    });

    await _releaseFormSeatLocks();

    await _clearFormDraft();
    if (!mounted) return;

    final shouldLeave = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Sesi Konfirmasi Pemesanan Berakhir'),
        content: const Text(
          'Waktu pengisian telah habis sehingga pemesanan tidak dibuat dan kursi dilepas kembali. Silakan pilih jadwal dan kursi dari awal.',
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Pesan Ulang'),
          ),
        ],
      ),
    );

    _isFormExpiryDialogVisible = false;

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
    if (_createdBooking == null || !mounted || _isPaymentExpiryDialogVisible) {
      return;
    }

    setState(() {
      _isPaymentSessionExpired = true;
      _isPaymentExpiryDialogVisible = true;
    });

    // Status akhir tetap diminta dari backend setelah countdown mencapai nol.
    await _refreshCreatedBookingStatus();
    if (mounted) {
      _isPaymentExpiryDialogVisible = false;
    }
  }

  Future<void> _confirmLeaveFlow() async {
    if (_isLeavingPage) {
      return;
    }

    _isLeavingPage = true;
    var booking = _createdBooking;
    if (booking != null && !booking.isPaid) {
      TravSBookingResult? refreshedBooking;
      try {
        refreshedBooking = await _refreshCreatedBookingStatus().timeout(
          const Duration(seconds: 15),
        );
      } on TimeoutException {
        if (mounted) {
          setState(() {
            _errorMessage =
                'Status pembayaran belum dapat dipastikan. Periksa koneksi lalu tekan kembali lagi.';
          });
        }
      }
      if (!mounted) {
        return;
      }
      if (refreshedBooking == null) {
        _isLeavingPage = false;
        return;
      }
      booking = refreshedBooking;
    }

    if (booking?.isPaid ?? false) {
      Navigator.of(context).pop(true);
      return;
    }

    final isPaymentStep = booking != null;
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

    if (!mounted) {
      return;
    }

    if (shouldLeave == true && mounted) {
      if (!isPaymentStep) {
        await _releaseFormSeatLocks();
        await _clearFormDraft();
      }
      if (!mounted) {
        return;
      }
      Navigator.of(context).pop(false);
      return;
    }

    _isLeavingPage = false;
  }

  Future<void> _releaseFormSeatLocks() async {
    try {
      await _apiService.releaseFormSeatLocks(
        token: widget.session.token,
        scheduleId: widget.detail.schedule.id,
        seatNumbers: widget.selectedSeatNumbers,
      );
    } catch (_) {
      // Jika perangkat sedang offline, backend tetap melepaskan lock saat
      // batas sesi berakhir agar kursi tidak terkunci permanen.
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
            _createdBooking == null ? 'Konfirmasi Pemesanan' : 'Pembayaran',
          ),
        ),
        body: Container(
          decoration: const BoxDecoration(gradient: TravSTheme.shellGradient),
          child: SafeArea(
            child: _createdBooking == null
                ? _CheckoutFormView(
                    formKey: _formKey,
                    header: _buildHeaderCard(context),
                    locationCard: _buildLocationCard(context),
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
    final schedule = widget.detail.schedule;
    final seatLabel = widget.selectedSeatNumbers
        .map((seat) => 'A$seat')
        .join(', ');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _CheckoutStatusBanner(
          deadlineIso: _stableFormLockDeadlineIso,
          maxDurationMinutes: widget.detail.bookingRules.seatLockFormMinutes,
          seatLabel: seatLabel,
          onExpired: _handleFormSessionExpired,
        ),
        const SizedBox(height: 16),
        _SectionCard(
          title: 'Detail Perjalanan',
          subtitle: '',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _formatRoute('${schedule.asal} - ${schedule.tujuan}'),
                style: theme.textTheme.titleLarge?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 14),
              _CompactTravelRow(
                icon: Icons.calendar_month_rounded,
                value: _formatDepartureDateTime(schedule.waktuBerangkat),
              ),
              _CompactTravelRow(
                icon: Icons.directions_car_filled_rounded,
                value: schedule.kendaraan,
              ),
              _CompactTravelRow(
                icon: Icons.event_seat_rounded,
                value:
                    '${widget.selectedSeatNumbers.length} kursi ($seatLabel)',
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildLocationCard(BuildContext context) {
    return _SectionCard(
      title: 'Lokasi Perjalanan',
      subtitle:
          'Lengkapi titik jemput dan titik tujuan. Isian tetap tersimpan saat tab berpindah.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxWidth < 360;
              return SizedBox(
                width: double.infinity,
                child: SegmentedButton<_CheckoutLocationTab>(
                  segments: [
                    const ButtonSegment(
                      value: _CheckoutLocationTab.pickup,
                      label: Text('Titik Jemput', textAlign: TextAlign.center),
                    ),
                    ButtonSegment(
                      value: _CheckoutLocationTab.dropoff,
                      label: Text(
                        compact ? 'Tujuan / Turun' : 'Titik Tujuan / Turun',
                        textAlign: TextAlign.center,
                        maxLines: 2,
                      ),
                    ),
                  ],
                  selected: {_selectedLocationTab},
                  showSelectedIcon: false,
                  expandedInsets: EdgeInsets.zero,
                  onSelectionChanged: (selection) {
                    setState(() {
                      _selectedLocationTab = selection.first;
                    });
                  },
                ),
              );
            },
          ),
          const SizedBox(height: 18),
          Column(
            children: [
              Offstage(
                offstage: _selectedLocationTab != _CheckoutLocationTab.pickup,
                child: _buildPickupFields(),
              ),
              Offstage(
                offstage: _selectedLocationTab != _CheckoutLocationTab.dropoff,
                child: _buildDropoffFields(context),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPickupFields() {
    return Column(
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
              icon: const Icon(Icons.map_rounded),
              label: const Text('Pilih di Peta'),
            ),
          ],
        ),
        const SizedBox(height: 14),
        _ResponsiveCoordinateFields(
          first: TextFormField(
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
          second: TextFormField(
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
          validator: (value) =>
              _validateRequiredText(value, 'Catatan atau patokan titik jemput'),
        ),
      ],
    );
  }

  Widget _buildDropoffFields(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        OutlinedButton.icon(
          onPressed: _isResolvingDropoff ? null : _pickDropoffFromMap,
          icon: const Icon(Icons.map_rounded),
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
                  'Koordinat',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: TravSTheme.navyMuted,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${_dropoffLatitudeController.text.trim()}, ${_dropoffLongitudeController.text.trim()}',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: TravSTheme.navy,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 14),
        _ResponsiveCoordinateFields(
          first: TextFormField(
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
          second: TextFormField(
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
          validator: (value) =>
              _validateRequiredText(value, 'Catatan atau patokan titik turun'),
        ),
      ],
    );
  }

  Widget _buildPassengerCard(BuildContext context) {
    return _SectionCard(
      title: 'Biodata Penumpang',
      subtitle:
          'Satu penumpang untuk satu kursi. Data ini akan dipakai pada detail pemesanan, tiket, dan manifes perjalanan.',
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
                        inputFormatters: [
                          // Nomor penumpang dibatasi agar tidak melebihi format HP lokal yang dipakai saat pemesanan.
                          FilteringTextInputFormatter.digitsOnly,
                          LengthLimitingTextInputFormatter(12),
                        ],
                        decoration: _passengerInputDecoration(
                          icon: Icons.phone_outlined,
                          label: 'Nomor HP / WhatsApp',
                          hint: 'Contoh: 081234567891',
                        ),
                        validator: _validatePassengerPhone,
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
      subtitle:
          'Catat nama barang yang dibawa. Barang bawaan biasa tidak dikenakan biaya tambahan.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: double.infinity,
            child: SegmentedButton<bool>(
              segments: const [
                ButtonSegment(
                  value: false,
                  label: Text(
                    'Tidak membawa barang',
                    textAlign: TextAlign.center,
                    maxLines: 2,
                  ),
                ),
                ButtonSegment(
                  value: true,
                  label: Text(
                    'Membawa barang',
                    textAlign: TextAlign.center,
                    maxLines: 2,
                  ),
                ),
              ],
              selected: {_hasBaggage},
              showSelectedIcon: false,
              expandedInsets: EdgeInsets.zero,
              onSelectionChanged: (selection) {
                final hasBaggage = selection.first;
                setState(() {
                  _hasBaggage = hasBaggage;
                  if (!hasBaggage) {
                    _selectedBaggagePhotos.clear();
                    _uploadedBaggagePhotos = <TravSUploadedBaggagePhoto>[];
                    _uploadedBaggagePhoto = null;
                  }
                });
                _saveFormDraft();
              },
            ),
          ),
          if (_hasBaggage) ...[
            const SizedBox(height: 14),
            TextFormField(
              controller: _baggageDescriptionController,
              maxLength: 255,
              textCapitalization: TextCapitalization.sentences,
              decoration: _passengerInputDecoration(
                label: 'Nama atau keterangan barang',
                hint: 'Contoh: tas pakaian dan kardus oleh-oleh',
                icon: Icons.inventory_2_outlined,
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Nama atau keterangan barang wajib diisi.'
                  : null,
            ),
          ],
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: TravSTheme.orangeSoft,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Text(
              'Sepeda, sepeda motor, dan barang berukuran besar wajib dikonfirmasi langsung kepada admin sebelum pemesanan. Barang tersebut tidak dihitung atau diterima otomatis oleh aplikasi.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: TravSTheme.navy,
                height: 1.45,
              ),
            ),
          ),
          // Opsi foto tetap tersedia agar penumpang bisa mendokumentasikan
          // barang bawaan sebelum pemesanan dikirim.
          if (_hasBaggage) ...[
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
                          _requiresBaggagePhoto
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
                              : _requiresBaggagePhoto
                              ? 'Ambil foto dari kamera atau pilih dari galeri sebelum submit.'
                              : 'Foto opsional untuk membantu pengecekan barang.',
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
    return _SectionCard(
      title: 'Ringkasan Pemesanan',
      subtitle:
          'Biaya admin dan total pembayaran digital ditentukan setelah metode pembayaran dipilih.',
      child: Column(
        children: [
          _SummaryRow(
            label: 'Subtotal tiket',
            value: 'Rp${_formatCurrency(widget.checkoutDraft.ticketSubtotal)}',
          ),
          const Divider(height: 26),
          _SummaryRow(
            label: 'Nominal pemesanan',
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
    final isAwaitingMethod = payment.isAwaitingMethodSelection;
    final isCashPayment =
        payment.methodFamily == 'cash' || payment.method == 'cash';
    // Deadline pembayaran mengikuti record pembayaran dari backend. Nilai pada
    // pemesanan hanya dipakai sebagai fallback untuk data lama.
    final paymentDeadline = isCashPayment
        ? ''
        : isAwaitingMethod
        ? (payment.selectionExpiresAt.isNotEmpty
              ? payment.selectionExpiresAt
              : booking.batasPembayaran)
        : payment.expiredAt.isNotEmpty
        ? payment.expiredAt
        : booking.batasPembayaran;
    final parsedDeadline = DateTime.tryParse(paymentDeadline)?.toLocal();
    final deadlinePassed =
        parsedDeadline != null && !parsedDeadline.isAfter(DateTime.now());
    final displayStatus = _resolvePaymentDisplayStatus(
      booking,
      deadlinePassed: deadlinePassed,
    );
    final isPaid = displayStatus == _PaymentDisplayStatus.paid;
    final isPending = displayStatus == _PaymentDisplayStatus.pending;
    final showPaymentDeadline =
        isPending && parsedDeadline != null && !_isPaymentSessionExpired;
    final canPay =
        isPending &&
        !isAwaitingMethod &&
        parsedDeadline != null &&
        parsedDeadline.isAfter(DateTime.now());
    final vehicleLabel = booking.kendaraan == null
        ? '-'
        : '${booking.kendaraan!.merk} ${booking.kendaraan!.tipe}'.trim();
    final seatNumbers = booking.passengers.isNotEmpty
        ? booking.passengers.map((item) => item.seatNumber).toList()
        : widget.selectedSeatNumbers;

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
      children: [
        _PaymentStatusBanner(
          status: displayStatus,
          isCashPayment: isCashPayment,
          deadlineIso: showPaymentDeadline ? paymentDeadline : '',
          onExpired: _handlePaymentSessionExpired,
        ),
        const SizedBox(height: 16),
        _SectionCard(
          title: 'Detail Perjalanan',
          subtitle: '',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _formatRoute(booking.rute),
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: TravSTheme.navy,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 14),
              _CompactTravelRow(
                icon: Icons.calendar_month_rounded,
                value: _formatDepartureDateTime(booking.waktuBerangkat),
              ),
              _CompactTravelRow(
                icon: Icons.directions_car_filled_rounded,
                value: vehicleLabel,
              ),
              _CompactTravelRow(
                icon: Icons.event_seat_rounded,
                value: '${booking.jumlahKursi} kursi',
              ),
              if (seatNumbers.isNotEmpty)
                _CompactTravelRow(
                  icon: Icons.airline_seat_recline_normal_rounded,
                  value:
                      'Kursi ${seatNumbers.map((seat) => 'A$seat').join(', ')}',
                ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        if (isAwaitingMethod)
          _buildChoosePaymentCard(context, booking)
        else
          _buildPaymentBreakdownCard(
            context,
            booking,
            displayStatus: displayStatus,
            paymentDeadline: paymentDeadline,
          ),
        if (!isAwaitingMethod && _errorMessage != null) ...[
          const SizedBox(height: 16),
          _MessageBanner(message: _errorMessage!),
        ],
        if (isPaid || (canPay && payment.hasCheckoutUrl)) ...[
          const SizedBox(height: 18),
          ElevatedButton.icon(
            onPressed: _isRefreshingPayment
                ? null
                : isPaid
                ? () => _openRelatedTicket(booking)
                : () => _openPaymentCheckout(payment.checkoutUrl),
            icon: _isRefreshingPayment
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Icon(
                    isPaid
                        ? Icons.confirmation_number_rounded
                        : Icons.open_in_browser_rounded,
                  ),
            label: isPaid
                ? const Text('Lihat Tiket Penumpang')
                : const Text('Lanjutkan Pembayaran'),
          ),
        ],
      ],
    );
  }

  _PaymentDisplayStatus _resolvePaymentDisplayStatus(
    TravSBookingResult booking, {
    required bool deadlinePassed,
  }) {
    final paymentStatus = booking.payment.status;
    if (booking.isPaid) return _PaymentDisplayStatus.paid;
    if (paymentStatus == 'kedaluwarsa' ||
        (_isPaymentSessionExpired && deadlinePassed)) {
      return _PaymentDisplayStatus.expired;
    }
    if (paymentStatus == 'gagal') return _PaymentDisplayStatus.failed;
    if (booking.status == 'dibatalkan') {
      return deadlinePassed
          ? _PaymentDisplayStatus.expired
          : _PaymentDisplayStatus.cancelled;
    }
    if (deadlinePassed &&
        booking.status == 'menunggu_pembayaran' &&
        (booking.payment.canChoose || paymentStatus == 'menunggu')) {
      return _PaymentDisplayStatus.expired;
    }
    return _PaymentDisplayStatus.pending;
  }

  Widget _buildPaymentBreakdownCard(
    BuildContext context,
    TravSBookingResult booking, {
    required _PaymentDisplayStatus displayStatus,
    required String paymentDeadline,
  }) {
    final payment = booking.payment;
    final isPaid = displayStatus == _PaymentDisplayStatus.paid;
    final baseAmount = payment.amount > 0 ? payment.amount : booking.totalHarga;
    final totalPayment = payment.totalPayment;

    return _SectionCard(
      title: 'Rincian Pembayaran',
      subtitle: '',
      child: Column(
        children: [
          _SummaryRow(
            label: isPaid ? 'Total dibayar' : 'Total pembayaran',
            value: totalPayment == null
                ? '-'
                : 'Rp${_formatCurrency(totalPayment)}',
            emphasize: true,
          ),
          const SizedBox(height: 8),
          _SummaryRow(
            label: 'Harga tiket',
            value: 'Rp${_formatCurrency(baseAmount)}',
          ),
          if (payment.providerFee != null)
            _SummaryRow(
              label: 'Biaya admin',
              value: 'Rp${_formatCurrency(payment.providerFee!)}',
            ),
          const Divider(height: 28),
          _SummaryRow(label: 'Metode pembayaran', value: payment.methodLabel),
          if (isPaid && payment.paidAt.isNotEmpty)
            _SummaryRow(
              label: 'Dibayarkan pada',
              value: _formatPaidAt(payment.paidAt),
            )
          else if (displayStatus == _PaymentDisplayStatus.pending &&
              paymentDeadline.isNotEmpty)
            _SummaryRow(
              label: 'Bayar sebelum',
              value: _formatPaymentDeadlineTime(paymentDeadline),
            ),
          if (displayStatus == _PaymentDisplayStatus.pending &&
              payment.usesGateway &&
              !payment.hasCheckoutUrl) ...[
            const SizedBox(height: 14),
            const _PaymentDataError(
              message:
                  'Tautan pembayaran belum tersedia. Periksa kembali status pembayaran.',
            ),
          ],
        ],
      ),
    );
  }
}

class _CheckoutFormView extends StatelessWidget {
  const _CheckoutFormView({
    required this.formKey,
    required this.header,
    required this.locationCard,
    required this.passengerCard,
    required this.baggageCard,
    required this.summaryCard,
    required this.errorMessage,
    required this.isSubmitting,
    required this.onSubmit,
  });

  final GlobalKey<FormState> formKey;
  final Widget header;
  final Widget locationCard;
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
          locationCard,
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

class _ResponsiveCoordinateFields extends StatelessWidget {
  const _ResponsiveCoordinateFields({
    required this.first,
    required this.second,
  });

  final Widget first;
  final Widget second;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 380) {
          return Column(children: [first, const SizedBox(height: 12), second]);
        }

        return Row(
          children: [
            Expanded(child: first),
            const SizedBox(width: 12),
            Expanded(child: second),
          ],
        );
      },
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
            if (subtitle.trim().isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                subtitle,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: TravSTheme.navyMuted,
                ),
              ),
            ],
            const SizedBox(height: 18),
            child,
          ],
        ),
      ),
    );
  }
}

class _PaymentStatusBanner extends StatelessWidget {
  const _PaymentStatusBanner({
    required this.status,
    required this.isCashPayment,
    required this.deadlineIso,
    required this.onExpired,
  });

  final _PaymentDisplayStatus status;
  final bool isCashPayment;
  final String deadlineIso;
  final VoidCallback onExpired;

  @override
  Widget build(BuildContext context) {
    final isCashPending =
        status == _PaymentDisplayStatus.pending && isCashPayment;
    final (title, message, icon, colors) = isCashPending
        ? (
            'Pembayaran Tunai',
            'Bayarkan kepada pengemudi setelah dijemput dan sebelum naik kendaraan.',
            Icons.payments_rounded,
            const [TravSTheme.navy, TravSTheme.navySoft],
          )
        : switch (status) {
            _PaymentDisplayStatus.paid => (
              'Pembayaran Berhasil',
              'Transaksi telah dikonfirmasi. Tiket elektronik siap dibuka.',
              Icons.check_circle_rounded,
              const [Color(0xFF1F9D67), Color(0xFF176D4A)],
            ),
            _PaymentDisplayStatus.expired => (
              'Pembayaran Kedaluwarsa',
              'Batas waktu pembayaran telah berakhir.',
              Icons.timer_off_rounded,
              const [Color(0xFF9F2F38), Color(0xFF691E27)],
            ),
            _PaymentDisplayStatus.failed => (
              'Pembayaran Gagal',
              'Pembayaran tidak dapat diproses. Silakan periksa kembali detail pemesanan.',
              Icons.error_rounded,
              const [Color(0xFF9F2F38), Color(0xFF691E27)],
            ),
            _PaymentDisplayStatus.cancelled => (
              'Pembayaran Dibatalkan',
              'Pemesanan telah dibatalkan dan pembayaran tidak dapat dilanjutkan.',
              Icons.cancel_rounded,
              const [Color(0xFF7C3740), Color(0xFF51242A)],
            ),
            _PaymentDisplayStatus.pending => (
              'Menunggu Pembayaran',
              'Segera selesaikan pembayaran agar pemesanan tidak dibatalkan.',
              Icons.schedule_rounded,
              const [TravSTheme.navy, TravSTheme.navySoft],
            ),
          };

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: colors,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(28),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: Colors.white, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            message,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Colors.white.withValues(alpha: 0.9),
              height: 1.45,
            ),
          ),
          if (status == _PaymentDisplayStatus.pending &&
              !isCashPending &&
              deadlineIso.isNotEmpty) ...[
            const SizedBox(height: 16),
            _CountdownPanel(
              title: _formatPaymentDeadlineSentence(deadlineIso),
              deadlineIso: deadlineIso,
              icon: Icons.timer_outlined,
              onDark: true,
              showStateLabel: false,
              onExpired: onExpired,
            ),
          ],
        ],
      ),
    );
  }
}

class _CheckoutStatusBanner extends StatelessWidget {
  const _CheckoutStatusBanner({
    required this.deadlineIso,
    required this.maxDurationMinutes,
    required this.seatLabel,
    required this.onExpired,
  });

  final String deadlineIso;
  final int maxDurationMinutes;
  final String seatLabel;
  final VoidCallback onExpired;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: TravSTheme.headerGradient,
        borderRadius: BorderRadius.circular(32),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(
                Icons.edit_note_rounded,
                color: Colors.white,
                size: 30,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Lengkapi Data Pemesanan',
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Kursi $seatLabel sudah dikunci sementara. Lengkapi data sebelum waktu habis.',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: Colors.white.withValues(alpha: 0.9),
              height: 1.45,
            ),
          ),
          const SizedBox(height: 18),
          _CountdownPanel(
            title: _formatCheckoutDeadlineSentence(deadlineIso),
            deadlineIso: deadlineIso,
            maxDurationMinutes: maxDurationMinutes,
            icon: Icons.timer_outlined,
            onDark: true,
            showStateLabel: false,
            onExpired: onExpired,
          ),
        ],
      ),
    );
  }
}

class _CompactTravelRow extends StatelessWidget {
  const _CompactTravelRow({required this.icon, required this.value});

  final IconData icon;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 21, color: TravSTheme.navyMuted),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
          ),
        ],
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
            flex: 5,
            child: Text(
              label,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: emphasize ? TravSTheme.navy : TravSTheme.navyMuted,
                fontWeight: emphasize ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
          ),
          const SizedBox(width: 20),
          Expanded(
            flex: 5,
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: TravSTheme.navy,
                fontWeight: emphasize ? FontWeight.w800 : FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PaymentDataError extends StatelessWidget {
  const _PaymentDataError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        message,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          color: TravSTheme.orange,
          fontWeight: FontWeight.w700,
        ),
      ),
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

class _CountdownPanel extends StatefulWidget {
  const _CountdownPanel({
    required this.title,
    required this.deadlineIso,
    required this.icon,
    this.onDark = false,
    this.showStateLabel = true,
    this.maxDurationMinutes,
    this.onExpired,
  });

  final String title;
  final String deadlineIso;
  final IconData icon;
  final bool onDark;
  final bool showStateLabel;
  final int? maxDurationMinutes;
  final VoidCallback? onExpired;

  @override
  State<_CountdownPanel> createState() => _CountdownPanelState();
}

class _CountdownPanelState extends State<_CountdownPanel>
    with WidgetsBindingObserver {
  Timer? _timer;
  late DateTime? _effectiveDeadline;
  late Duration _remaining;
  bool _expiredCallbackSent = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _effectiveDeadline = _resolveEffectiveDeadline();
    _remaining = _calculateRemaining();
    _notifyExpiredIfNeeded();
    _startTimer();
  }

  @override
  void didUpdateWidget(covariant _CountdownPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.deadlineIso != widget.deadlineIso ||
        oldWidget.maxDurationMinutes != widget.maxDurationMinutes) {
      _expiredCallbackSent = false;
      _effectiveDeadline = _resolveEffectiveDeadline();
      _remaining = _calculateRemaining();
      _notifyExpiredIfNeeded();
      _startTimer();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Saat aplikasi kembali aktif, hitung lagi dari timestamp API.
      _updateRemaining();
      _startTimer();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  void _startTimer() {
    _timer?.cancel();
    if (_remaining == Duration.zero) return;

    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      _updateRemaining();
    });
  }

  void _updateRemaining() {
    if (!mounted) return;

    final latest = _calculateRemaining();
    setState(() {
      _remaining = latest;
    });
    if (latest == Duration.zero) {
      // Timer berhenti agar tidak terus membangun angka 00:00.
      _timer?.cancel();
    }
    _notifyExpiredIfNeeded();
  }

  Duration _calculateRemaining() {
    final deadline = _effectiveDeadline;
    if (deadline == null) {
      return Duration.zero;
    }

    final difference = deadline.difference(DateTime.now());
    return difference.isNegative ? Duration.zero : difference;
  }

  DateTime? _resolveEffectiveDeadline() {
    final parsedDeadline = DateTime.tryParse(widget.deadlineIso)?.toLocal();
    final maxMinutes = widget.maxDurationMinutes;
    if (parsedDeadline == null) {
      return null;
    }

    if (maxMinutes == null || maxMinutes <= 0) {
      return parsedDeadline;
    }

    final now = DateTime.now();
    final maxDeadline = now.add(Duration(minutes: maxMinutes));
    // Countdown memakai durasi dari Pengaturan Sistem agar tampilan mobile tidak
    // lebih panjang dari aturan admin jika nilai deadline lama/berbeda timezone terbawa.
    return parsedDeadline.isAfter(maxDeadline) ? maxDeadline : parsedDeadline;
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
          if (widget.showStateLabel)
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
    required this.family,
  });

  final String key;
  final String label;
  final String family;
}

String _formatRoute(String route) {
  return route.replaceFirst(' - ', ' → ');
}

String _formatDepartureDateTime(String isoValue) {
  final parsed = DateTime.tryParse(isoValue)?.toLocal();
  return parsed == null
      ? '-'
      : DateFormat('d MMM yyyy • HH:mm', 'id_ID').format(parsed);
}

String _formatPaidAt(String isoValue) {
  final parsed = DateTime.tryParse(isoValue)?.toLocal();
  return parsed == null
      ? '-'
      : DateFormat('d MMM yyyy, HH:mm', 'id_ID').format(parsed);
}

String _formatPaymentDeadlineTime(String isoValue) {
  final parsed = DateTime.tryParse(isoValue)?.toLocal();
  return parsed == null
      ? '-'
      : '${DateFormat('HH:mm', 'id_ID').format(parsed)} WIB';
}

String _formatPaymentDeadlineSentence(String isoValue) {
  final deadline = DateTime.tryParse(isoValue)?.toLocal();
  if (deadline == null) return 'Batas pembayaran belum tersedia';

  final now = DateTime.now();
  final sameDay =
      deadline.year == now.year &&
      deadline.month == now.month &&
      deadline.day == now.day;
  final time = DateFormat('HH:mm', 'id_ID').format(deadline);

  if (sameDay) {
    return 'Bayar sebelum pukul $time WIB';
  }

  final date = DateFormat('d MMM yyyy', 'id_ID').format(deadline);
  return 'Bayar sebelum $date, pukul $time WIB';
}

String _formatCheckoutDeadlineSentence(String isoValue) {
  final deadline = DateTime.tryParse(isoValue)?.toLocal();
  if (deadline == null) return 'Batas pengisian belum tersedia';

  final now = DateTime.now();
  final sameDay =
      deadline.year == now.year &&
      deadline.month == now.month &&
      deadline.day == now.day;
  final time = DateFormat('HH:mm', 'id_ID').format(deadline);

  if (sameDay) {
    return 'Lengkapi sebelum pukul $time WIB';
  }

  final date = DateFormat('d MMM yyyy', 'id_ID').format(deadline);
  return 'Lengkapi sebelum $date, pukul $time WIB';
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

String _formatCurrency(num value) {
  return NumberFormat.decimalPattern('id_ID').format(value);
}
