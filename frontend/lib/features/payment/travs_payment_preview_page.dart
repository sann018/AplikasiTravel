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

// Bagian besar dipisah per fungsi agar file utama lebih mudah dipelajari.
part 'bagian_pembayaran/widget_form_konfirmasi.dart';
part 'bagian_pembayaran/widget_status_pembayaran.dart';
part 'bagian_pembayaran/widget_detail_pembayaran.dart';
part 'bagian_pembayaran/hitung_mundur.dart';
part 'bagian_pembayaran/model_tampilan_pembayaran.dart';

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
    this.apiService,
    super.key,
  });

  /// Membuka kembali booking tersimpan tanpa membuat booking baru.
  factory TravSPaymentPreviewPage.resume({
    required TravSScheduleDetail detail,
    required AppSettings appSettings,
    required TravSUserSession session,
    required TravSBookingResult booking,
    TravSApiService? apiService,
    Key? key,
  }) {
    final seatNumbers = booking.passengers
        .map((passenger) => passenger.seatNumber)
        .toList(growable: false);

    return TravSPaymentPreviewPage(
      key: key,
      detail: detail,
      appSettings: appSettings,
      checkoutDraft: TravSCheckoutDraft(
        seatCount: booking.jumlahKursi,
        passengerCount: booking.passengers.length,
        baggageMethod: 'ukuran',
        baggagePlacement: 'masuk_bagasi',
        baggageSizeCategory: 'besar',
        largeBaggageType: 'lainnya',
        baggageWeight: 0,
        baggageSize: 0,
        baggageLength: 0,
        baggageWidth: 0,
        baggageItems: booking.baggageItems.length,
        ticketSubtotal: booking.subtotalTiket,
        weightFee: 0,
        sizeFee: 0,
        itemFee: 0,
      ),
      selectedRole: session.rolePreview,
      session: session,
      selectedSeatNumbers: seatNumbers,
      baggagePlacement: 'masuk_bagasi',
      baggageWeight: 0,
      baggageItems: booking.baggageItems.length,
      lockExpiresAt: booking.batasPembayaran,
      initialBooking: booking,
      apiService: apiService,
    );
  }

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
  final TravSApiService? apiService;

  @override
  State<TravSPaymentPreviewPage> createState() =>
      _TravSPaymentPreviewPageState();
}

class _TravSPaymentPreviewPageState extends State<TravSPaymentPreviewPage>
    with WidgetsBindingObserver {
  late final TravSApiService _apiService;
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
  final TextEditingController _customerNameController = TextEditingController();
  final TextEditingController _customerPhoneController =
      TextEditingController();
  final TextEditingController _customerEmailController =
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
  bool _isSearchingPassengerAccount = false;
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
  _PaymentCheckFeedback? _paymentCheckFeedback;
  String? _passengerAccountMessage;
  Map<String, dynamic>? _linkedPassengerAccount;
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
    _apiService = widget.apiService ?? TravSApiService();
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
          name: index == 0 && !user.isStafPelayanan ? user.nama : '',
          phone: index == 0 && !user.isStafPelayanan ? user.noHp : '',
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
    _customerNameController.dispose();
    _customerPhoneController.dispose();
    _customerEmailController.dispose();
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

  void _applyPickup(TravSPickupPoint pickup) {
    setState(() {
      _pickupLatitudeController.text = pickup.latitude.toStringAsFixed(6);
      _pickupLongitudeController.text = pickup.longitude.toStringAsFixed(6);
      _pickupAddressController.text = pickup.address;
    });
  }

  void _markPickupAddressChanged(String _) {
    if (_pickupLatitudeController.text.isEmpty &&
        _pickupLongitudeController.text.isEmpty) {
      return;
    }

    // Koordinat lama dibuang agar alamat baru tidak tersimpan dengan pin lama.
    setState(() {
      _pickupLatitudeController.clear();
      _pickupLongitudeController.clear();
    });
  }

  Future<void> _reviewPickupFromAddress() async {
    setState(() {
      _isResolvingPickup = true;
      _errorMessage = null;
    });

    try {
      final resolved = await _locationService.resolvePickupFromInput(
        _pickupAddressController.text,
      );
      if (!mounted) return;

      final reviewed = await Navigator.of(context).push<TravSPickupPoint>(
        MaterialPageRoute<TravSPickupPoint>(
          builder: (_) => TravSPickupMapPickerPage(
            initialPickup: resolved,
            title: 'Periksa Titik Jemput',
            locationLabel: 'titik jemput',
          ),
        ),
      );
      if (reviewed != null && mounted) {
        _applyPickup(reviewed);
      }
    } on TravSLocationException catch (error) {
      if (!mounted) return;
      setState(() {
        _errorMessage = error.message;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.message),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isResolvingPickup = false;
        });
      }
    }
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

  void _applyDropoff(TravSPickupPoint dropoff) {
    setState(() {
      _dropoffLatitudeController.text = dropoff.latitude.toStringAsFixed(6);
      _dropoffLongitudeController.text = dropoff.longitude.toStringAsFixed(6);
      _dropoffAddressController.text = dropoff.address;
    });
  }

  void _markDropoffAddressChanged(String _) {
    if (_dropoffLatitudeController.text.isEmpty &&
        _dropoffLongitudeController.text.isEmpty) {
      return;
    }

    // Alamat dan pin harus selalu berasal dari pilihan lokasi yang sama.
    setState(() {
      _dropoffLatitudeController.clear();
      _dropoffLongitudeController.clear();
    });
  }

  Future<void> _reviewDropoffFromAddress() async {
    setState(() {
      _isResolvingDropoff = true;
      _errorMessage = null;
    });

    try {
      final resolved = await _locationService.resolvePickupFromInput(
        _dropoffAddressController.text,
      );
      if (!mounted) return;

      final reviewed = await Navigator.of(context).push<TravSPickupPoint>(
        MaterialPageRoute<TravSPickupPoint>(
          builder: (_) => TravSPickupMapPickerPage(
            initialPickup: resolved,
            title: 'Periksa Titik Turun',
            locationLabel: 'titik turun',
            showCurrentLocationButton: false,
          ),
        ),
      );
      if (reviewed != null && mounted) {
        _applyDropoff(reviewed);
      }
    } on TravSLocationException catch (error) {
      if (!mounted) return;
      setState(() {
        _errorMessage = error.message;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.message),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isResolvingDropoff = false;
        });
      }
    }
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
          stafPelayananMode: widget.session.user.isStafPelayanan,
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
        stafPelayananMode: widget.session.user.isStafPelayanan,
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
          customerName: widget.session.user.isStafPelayanan
              ? _customerNameController.text.trim()
              : null,
          customerPhone: widget.session.user.isStafPelayanan
              ? _customerPhoneController.text.trim()
              : null,
          customerEmail: widget.session.user.isStafPelayanan
              ? _customerEmailController.text.trim()
              : null,
          passengerAccountId: widget.session.user.isStafPelayanan
              ? (_linkedPassengerAccount?['id'] as int?)
              : null,
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
        final refreshedBooking = widget.session.user.isStafPelayanan
            ? await _apiService.loadStafPelayananBookingDetail(
                token: widget.session.token,
                bookingId: booking.id,
              )
            : await _apiService.loadPassengerBookingDetail(
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
        stafPelayananMode: widget.session.user.isStafPelayanan,
      );

      if (!mounted) {
        return refreshedBooking;
      }

      setState(() {
        _createdBooking = refreshedBooking;
        _errorMessage = null;
        if (showSuccessMessage) {
          _paymentCheckFeedback = _feedbackForPayment(refreshedBooking);
        }
      });

      if (showSuccessMessage) {
        final refreshedStatus = refreshedBooking.payment.status;
        final message = refreshedBooking.isPaid
            ? 'Pembayaran berhasil. Tiket sudah aktif.'
            : refreshedStatus == 'kedaluwarsa'
            ? 'Pembayaran telah kedaluwarsa.'
            : refreshedStatus == 'gagal'
            ? 'Pembayaran belum berhasil. Silakan periksa metode pembayaran.'
            : 'Pembayaran masih menunggu konfirmasi.';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
        );
      }
      return refreshedBooking;
    } on TravSApiException catch (error) {
      if (mounted) {
        setState(() {
          _errorMessage = showSuccessMessage ? null : error.message;
          if (showSuccessMessage) {
            _paymentCheckFeedback = _PaymentCheckFeedback(
              message: error.message,
              tone: _PaymentCheckFeedbackTone.error,
            );
          }
        });
      }
      return null;
    } catch (_) {
      if (mounted) {
        setState(() {
          _errorMessage = showSuccessMessage
              ? null
              : 'Status pembayaran belum dapat diperbarui. Silakan coba lagi beberapa saat.';
          if (showSuccessMessage) {
            _paymentCheckFeedback = const _PaymentCheckFeedback(
              message:
                  'Status pembayaran belum dapat diperbarui. Silakan coba lagi beberapa saat.',
              tone: _PaymentCheckFeedbackTone.error,
            );
          }
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

  _PaymentCheckFeedback _feedbackForPayment(TravSBookingResult booking) {
    if (booking.isPaid) {
      return const _PaymentCheckFeedback(
        message: 'Pembayaran berhasil dikonfirmasi. Tiket sudah aktif.',
        tone: _PaymentCheckFeedbackTone.success,
      );
    }
    if (booking.payment.status == 'kedaluwarsa' ||
        booking.status == 'dibatalkan') {
      return const _PaymentCheckFeedback(
        message: 'Batas pembayaran telah berakhir.',
        tone: _PaymentCheckFeedbackTone.error,
      );
    }
    if (booking.payment.status == 'gagal') {
      return const _PaymentCheckFeedback(
        message:
            'Pembayaran belum berhasil. Periksa kembali metode pembayaran.',
        tone: _PaymentCheckFeedbackTone.warning,
      );
    }

    return const _PaymentCheckFeedback(
      message:
          'Pembayaran masih menunggu konfirmasi. Silakan periksa kembali beberapa saat lagi.',
      tone: _PaymentCheckFeedbackTone.info,
    );
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
        stafPelayananMode: widget.session.user.isStafPelayanan,
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

  String? _validateResolvedLocation(
    String? value, {
    required String label,
    required String latitude,
    required String longitude,
  }) {
    final raw = value?.trim() ?? '';
    if (raw.isEmpty) {
      return '$label wajib diisi.';
    }

    if (!_isCoordinateInputValid(latitude, min: -90, max: 90) ||
        !_isCoordinateInputValid(longitude, min: -180, max: 180)) {
      return 'Klik Cari & Tinjau Lokasi atau pilih titik melalui peta.';
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

  String? _validatePassengerName(String? value) {
    final name = value?.trim() ?? '';
    if (name.isEmpty) {
      return 'Nama penumpang wajib diisi.';
    }
    if (!RegExp(r'^[A-Za-z ]+$').hasMatch(name)) {
      return 'Nama penumpang hanya boleh berisi huruf dan spasi.';
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

    // Metode tunai sudah tersimpan di backend dan dibayar saat penjemputan,
    // sehingga pengguna dapat kembali tanpa peringatan pembayaran digital.
    if (_isConfirmedCashPayment(booking)) {
      Navigator.of(context).pop(false);
      return;
    }

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

    if (_isConfirmedCashPayment(booking)) {
      Navigator.of(context).pop(false);
      return;
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

  bool _isConfirmedCashPayment(TravSBookingResult? booking) {
    if (booking == null || booking.payment.isAwaitingMethodSelection) {
      return false;
    }

    return booking.payment.methodFamily == 'cash' ||
        booking.payment.method == 'cash';
  }

  Future<void> _releaseFormSeatLocks() async {
    try {
      await _apiService.releaseFormSeatLocks(
        token: widget.session.token,
        scheduleId: widget.detail.schedule.id,
        seatNumbers: widget.selectedSeatNumbers,
        stafPelayananMode: widget.session.user.isStafPelayanan,
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
          'Gunakan GPS, pilih pin di peta, atau tempel alamat maupun link Google Maps.',
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
                child: _buildDropoffFields(),
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
        TextFormField(
          controller: _pickupAddressController,
          minLines: 2,
          maxLines: 3,
          inputFormatters: [LengthLimitingTextInputFormatter(1000)],
          decoration: InputDecoration(
            labelText: 'Alamat Jemput atau Link Google Maps',
            hintText:
                'Tempel alamat lengkap, koordinat, atau link Google Maps.',
            helperText: _isResolvingPickup
                ? 'Mencari lokasi...'
                : _draftPickupPoint != null
                ? 'Lokasi sudah memiliki pin dan siap digunakan.'
                : 'Setelah mengisi, tekan Cari & Tinjau Lokasi.',
          ),
          onChanged: _markPickupAddressChanged,
          validator: (value) => _validateResolvedLocation(
            value,
            label: 'Alamat jemput',
            latitude: _pickupLatitudeController.text,
            longitude: _pickupLongitudeController.text,
          ),
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            OutlinedButton.icon(
              onPressed: _isResolvingPickup ? null : _reviewPickupFromAddress,
              icon: Icon(
                _isResolvingPickup ? Icons.sync_rounded : Icons.search_rounded,
              ),
              label: const Text('Cari & Tinjau Lokasi'),
            ),
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

  Widget _buildDropoffFields() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextFormField(
          controller: _dropoffAddressController,
          minLines: 2,
          maxLines: 3,
          inputFormatters: [LengthLimitingTextInputFormatter(1000)],
          decoration: InputDecoration(
            labelText: 'Alamat Turun atau Link Google Maps',
            hintText:
                'Tempel alamat lengkap, koordinat, atau link Google Maps.',
            helperText: _isResolvingDropoff
                ? 'Mencari lokasi...'
                : _hasDropoffDraft
                ? 'Lokasi sudah memiliki pin dan siap digunakan.'
                : 'Setelah mengisi, tekan Cari & Tinjau Lokasi.',
          ),
          onChanged: _markDropoffAddressChanged,
          validator: (value) => _validateResolvedLocation(
            value,
            label: 'Alamat tujuan',
            latitude: _dropoffLatitudeController.text,
            longitude: _dropoffLongitudeController.text,
          ),
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            OutlinedButton.icon(
              onPressed: _isResolvingDropoff ? null : _reviewDropoffFromAddress,
              icon: Icon(
                _isResolvingDropoff ? Icons.sync_rounded : Icons.search_rounded,
              ),
              label: const Text('Cari & Tinjau Lokasi'),
            ),
            OutlinedButton.icon(
              onPressed: _isResolvingDropoff ? null : _pickDropoffFromMap,
              icon: const Icon(Icons.map_rounded),
              label: const Text('Pilih di Peta'),
            ),
          ],
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
      title: widget.session.user.isStafPelayanan
          ? 'Data Pemesan dan Penumpang'
          : 'Biodata Penumpang',
      subtitle: widget.session.user.isStafPelayanan
          ? 'Isi kontak utama pemesanan loket, lalu lengkapi nama pemilik setiap kursi.'
          : 'Satu penumpang untuk satu kursi. Data ini akan dipakai pada detail pemesanan, tiket, dan manifes perjalanan.',
      child: Column(
        children: [
          if (widget.session.user.isStafPelayanan) ...[
            _buildStafPelayananCustomerFields(context),
            const SizedBox(height: 18),
          ],
          ..._passengerForms.map(
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
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: TravSTheme.navy,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: passenger.nameController,
                      textCapitalization: TextCapitalization.words,
                      inputFormatters: [
                        // Nama pada tiket hanya menerima huruf dan spasi.
                        FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z ]')),
                        LengthLimitingTextInputFormatter(100),
                      ],
                      decoration: _passengerInputDecoration(
                        icon: Icons.person_outline_rounded,
                        label: 'Nama lengkap penumpang',
                        hint: 'Contoh: Ihsan Penumpang',
                        helperText:
                            'Hanya gunakan huruf dan spasi; angka tidak diperbolehkan.',
                      ),
                      validator: _validatePassengerName,
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
                        helperText:
                            'Masukkan 11–12 digit angka tanpa spasi atau tanda baca.',
                      ),
                      validator: _validatePassengerPhone,
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

  Future<void> _confirmStafPelayananCash(TravSBookingResult booking) async {
    final approved = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Konfirmasi Pembayaran Tunai'),
        content: const Text(
          'Pastikan uang tunai sudah diterima. Setelah dikonfirmasi, tiket akan langsung diterbitkan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Uang Sudah Diterima'),
          ),
        ],
      ),
    );
    if (approved != true || !mounted) return;

    setState(() {
      _isRefreshingPayment = true;
      _errorMessage = null;
    });
    try {
      final updated = await _apiService.confirmStafPelayananCashPayment(
        token: widget.session.token,
        bookingId: booking.id,
      );
      if (!mounted) return;
      setState(() => _createdBooking = updated);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pembayaran tunai dikonfirmasi. Tiket sudah aktif.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } on TravSApiException catch (error) {
      if (mounted) setState(() => _errorMessage = error.message);
    } finally {
      if (mounted) setState(() => _isRefreshingPayment = false);
    }
  }

  Widget _buildStafPelayananCustomerFields(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TravSTheme.orangeSoft,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Kontak Utama Pemesanan Loket',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: TravSTheme.navy,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Data ini menjadi kontak utama. Akun penumpang tidak dibuat otomatis.',
            style: TextStyle(color: TravSTheme.navyMuted, height: 1.4),
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _customerNameController,
            onChanged: (_) => _unlinkPassengerAccount(),
            textCapitalization: TextCapitalization.words,
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z ]')),
              LengthLimitingTextInputFormatter(100),
            ],
            decoration: _passengerInputDecoration(
              icon: Icons.badge_outlined,
              label: 'Nama pemesan',
              hint: 'Contoh: Budi Santoso',
              helperText: 'Gunakan huruf dan spasi, maksimal 100 karakter.',
            ),
            validator: _validatePassengerName,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _customerPhoneController,
            onChanged: (_) => _unlinkPassengerAccount(),
            keyboardType: TextInputType.phone,
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
              LengthLimitingTextInputFormatter(12),
            ],
            decoration: _passengerInputDecoration(
              icon: Icons.phone_outlined,
              label: 'Nomor HP / WhatsApp pemesan',
              hint: 'Contoh: 081234567890',
              helperText: 'Masukkan 11–12 digit angka.',
            ),
            validator: _validatePassengerPhone,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _customerEmailController,
            onChanged: (_) => _unlinkPassengerAccount(),
            keyboardType: TextInputType.emailAddress,
            maxLength: 100,
            decoration: _passengerInputDecoration(
              icon: Icons.email_outlined,
              label: 'Email pemesan (opsional)',
              hint: 'Contoh: pemesan@yahoo.com',
              helperText: 'Boleh dikosongkan dan tidak dibatasi hanya Gmail.',
            ),
            validator: (value) {
              final email = value?.trim() ?? '';
              if (email.isEmpty) return null;
              final valid = RegExp(
                r'^[^\s@]+@[^\s@]+\.[^\s@]+$',
              ).hasMatch(email);
              return valid ? null : 'Format email pemesan belum valid.';
            },
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: _isSearchingPassengerAccount
                ? null
                : _searchPassengerAccount,
            icon: _isSearchingPassengerAccount
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.person_search_rounded),
            label: const Text('Cari Akun Penumpang'),
          ),
          if (_passengerAccountMessage != null) ...[
            const SizedBox(height: 10),
            Text(
              _passengerAccountMessage!,
              style: TextStyle(
                color: _linkedPassengerAccount == null
                    ? TravSTheme.navyMuted
                    : TravSTheme.success,
                fontWeight: FontWeight.w700,
                height: 1.4,
              ),
            ),
          ],
        ],
      ),
    );
  }

  void _unlinkPassengerAccount() {
    if (_linkedPassengerAccount == null && _passengerAccountMessage == null) {
      return;
    }

    setState(() {
      _linkedPassengerAccount = null;
      _passengerAccountMessage = null;
    });
  }

  Future<void> _searchPassengerAccount() async {
    final email = _customerEmailController.text.trim();
    final phone = _customerPhoneController.text.trim();
    final query = email.isNotEmpty ? email : phone;
    if (query.isEmpty) {
      setState(() {
        _passengerAccountMessage =
            'Isi email atau nomor HP lengkap sebelum mencari akun.';
      });
      return;
    }

    setState(() => _isSearchingPassengerAccount = true);
    try {
      final account = await _apiService.searchStafPelayananPassengerAccount(
        token: widget.session.token,
        query: query,
      );
      if (!mounted) return;

      setState(() {
        _linkedPassengerAccount = account;
        if (account == null) {
          _passengerAccountMessage =
              'Akun tidak ditemukan. Pemesanan tetap dapat dilanjutkan tanpa akun.';
          return;
        }

        _passengerAccountMessage =
            'Terhubung ke akun ${account['nama'] ?? 'penumpang'}.';
        _customerNameController.text = account['nama']?.toString() ?? '';
        _customerPhoneController.text = account['no_hp']?.toString() ?? '';
        _customerEmailController.text = account['email']?.toString() ?? '';
      });
    } on TravSApiException catch (error) {
      if (mounted) {
        setState(() => _passengerAccountMessage = error.message);
      }
    } finally {
      if (mounted) {
        setState(() => _isSearchingPassengerAccount = false);
      }
    }
  }

  InputDecoration _passengerInputDecoration({
    required IconData icon,
    required String label,
    required String hint,
    String? helperText,
  }) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: Icon(icon),
      helperText: helperText,
      helperMaxLines: 3,
      errorMaxLines: 3,
      helperStyle: const TextStyle(
        color: TravSTheme.navyMuted,
        fontSize: 10.5,
        height: 1.3,
      ),
      errorStyle: const TextStyle(
        color: TravSTheme.danger,
        fontSize: 10.5,
        height: 1.3,
        fontWeight: FontWeight.w600,
      ),
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
    // Transaksi digital tetap dapat diperiksa setelah berhasil agar pengguna
    // dapat memastikan kembali status terbaru dari provider.
    final canCheckGatewayStatus =
        (isPending || isPaid) && !isAwaitingMethod && payment.usesGateway;
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
        if (canCheckGatewayStatus) ...[
          const SizedBox(height: 10),
          OutlinedButton.icon(
            key: const Key('check-payment-status-button'),
            onPressed: _isRefreshingPayment
                ? null
                : () => _refreshCreatedBookingStatus(showSuccessMessage: true),
            icon: _isRefreshingPayment
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.sync_rounded),
            label: Text(
              _isRefreshingPayment
                  ? 'Memeriksa Pembayaran...'
                  : 'Cek Status Pembayaran',
            ),
          ),
          if (_paymentCheckFeedback != null) ...[
            const SizedBox(height: 10),
            _PaymentStatusCheckBanner(feedback: _paymentCheckFeedback!),
          ],
        ],
        if (widget.session.user.isStafPelayanan &&
            isCashPayment &&
            isPending) ...[
          const SizedBox(height: 10),
          ElevatedButton.icon(
            onPressed: _isRefreshingPayment
                ? null
                : () => _confirmStafPelayananCash(booking),
            icon: const Icon(Icons.payments_outlined),
            label: const Text('Konfirmasi Pembayaran Tunai'),
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
