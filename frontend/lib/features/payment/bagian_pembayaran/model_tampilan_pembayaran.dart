part of '../travs_payment_preview_page.dart';

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
