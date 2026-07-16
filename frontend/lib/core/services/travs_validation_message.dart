String travsFriendlyValidationMessage(String fieldName, String message) {
  final normalizedMessage = message.trim().toLowerCase();

  if (normalizedMessage == 'validation.unique') {
    if (fieldName == 'email') {
      return 'Email sudah terdaftar. Silakan masuk atau gunakan email lain.';
    }
    if (fieldName == 'no_hp') {
      return 'Nomor HP sudah terdaftar. Silakan masuk atau gunakan nomor lain.';
    }
    return 'Data tersebut sudah terdaftar. Silakan gunakan data lain.';
  }

  if (normalizedMessage == 'validation.exists') {
    if (fieldName == 'jadwal_id') {
      return 'Jadwal yang dipilih sudah tidak tersedia. Silakan pilih jadwal lain.';
    }
    return 'Data yang dipilih sudah tidak tersedia. Silakan muat ulang halaman.';
  }

  const translatedMessages = <String, String>{
    'validation.confirmed': 'Konfirmasi kata sandi belum sama.',
    'validation.email': 'Format email belum valid.',
    'validation.required': 'Data wajib masih ada yang belum diisi.',
    'validation.required_if': 'Data wajib masih ada yang belum diisi.',
    'validation.required_with': 'Data terkait masih ada yang belum diisi.',
    'validation.in': 'Pilihan yang dikirim tidak valid.',
    'validation.array': 'Format daftar data belum valid.',
    'validation.string': 'Data harus berupa teks.',
    'validation.integer': 'Data harus berupa bilangan bulat.',
    'validation.numeric': 'Data harus berupa angka.',
    'validation.boolean': 'Pilihan harus bernilai aktif atau nonaktif.',
    'validation.date': 'Format tanggal belum valid.',
    'validation.regex': 'Format data belum sesuai.',
    'validation.image': 'File harus berupa gambar.',
    'validation.file': 'Data yang dipilih harus berupa file.',
    'validation.mimes':
        'Format file belum didukung. Gunakan JPG, JPEG, PNG, atau WEBP.',
    'validation.uploaded': 'File gagal diunggah. Silakan pilih ulang file.',
    'validation.max.file': 'Ukuran file terlalu besar.',
    'validation.max.string': 'Jumlah karakter melebihi batas yang diizinkan.',
    'validation.max.array': 'Jumlah data melebihi batas yang diizinkan.',
    'validation.max.numeric': 'Nilai melebihi batas yang diizinkan.',
    'validation.min.string': 'Jumlah karakter masih kurang.',
    'validation.min.array': 'Jumlah data masih kurang.',
    'validation.min.numeric': 'Nilai masih di bawah batas yang diizinkan.',
    'validation.between.numeric':
        'Nilai berada di luar rentang yang diizinkan.',
    'validation.digits_between': 'Jumlah digit belum sesuai.',
  };

  final translated = translatedMessages[normalizedMessage];
  if (translated != null) return translated;

  // Key validasi tidak boleh ditampilkan mentah kepada pengguna.
  if (normalizedMessage.startsWith('validation.')) {
    return 'Data yang dikirim belum valid. Periksa kembali isian Anda.';
  }

  return message;
}
