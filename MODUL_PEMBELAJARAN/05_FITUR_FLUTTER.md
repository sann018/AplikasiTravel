# 05 — Fitur Flutter / Aplikasi Mobile (Halaman per Halaman + Kode)

> Tujuan bab ini: kamu paham **tiap halaman aplikasi mobile**, file mana yang membuatnya, dan
> potongan kode kuncinya. Aplikasi mobile dipakai **penumpang** dan **pengemudi** (admin tidak).

---

## 5.1 Konsep Dasar Flutter yang Wajib Dipahami

Sebelum masuk halaman, pahami 5 konsep ini (sering ditanya saat sidang):

1. **Widget** = semua di Flutter adalah widget (tombol, teks, gambar, bahkan layout). Ibarat lego,
   kita menyusun widget kecil jadi tampilan utuh.
2. **StatelessWidget** = widget yang tampilannya tetap (tidak berubah). Contoh: label judul.
3. **StatefulWidget** = widget yang tampilannya bisa berubah (misalnya setelah data dimuat).
   Project ini banyak memakai ini.
4. **setState()** = perintah "gambar ulang tampilan karena ada data berubah".
5. **Future / async / await** = cara Flutter menunggu data dari internet (API) tanpa membuat
   aplikasi macet.

**Cara project ini mengambil data & menampilkan:** memakai `FutureBuilder`. Pola: "panggil API →
selama menunggu tampilkan loading → kalau berhasil tampilkan data → kalau gagal tampilkan pesan
error".

```dart
FutureBuilder<TravSBookingResult>(
  future: _future,          // proses ambil data dari API
  builder: (context, snapshot) {
    if (snapshot.connectionState == ConnectionState.waiting) {
      return const CircularProgressIndicator();   // sedang loading
    }
    if (snapshot.hasError) {
      return const Text('Gagal memuat data');      // error
    }
    final data = snapshot.data!;                    // data siap
    return Text(data.kodeBooking);                  // tampilkan
  },
)
```

---

## 5.2 Titik Awal Aplikasi

**File:** `lib/main.dart` dan `lib/app.dart`

`main.dart` sangat singkat — hanya menyalakan aplikasi dan menyiapkan format tanggal Indonesia:
```dart
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('id_ID');   // format tanggal Indonesia
  runApp(const TravSApp());
}
```

`app.dart` adalah "pengatur lalu lintas halaman awal". Ia menentukan:
- Kalau aplikasi baru dibuka → tampilkan **Splash**.
- Kalau belum login & bukan tamu → tampilkan **halaman Login/Register**.
- Kalau sudah login atau memilih mode tamu → tampilkan **Beranda**.

---

## 5.3 Peta Halaman (Folder `lib/features/`)

| Folder/File | Halaman | Dipakai peran |
|-------------|---------|---------------|
| `launch/travs_splash_page.dart` | Splash screen | Semua |
| `auth/travs_auth_page.dart` | Login & Register | Tamu |
| `home/travs_home_page.dart` | Beranda (3 dashboard + navigasi) | Semua |
| `schedule/travs_schedule_detail_page.dart` | Detail jadwal + denah kursi | Penumpang |
| `payment/travs_payment_preview_page.dart` | Form pemesanan + pembayaran | Penumpang |
| `booking/travs_booking_detail_page.dart` | Detail pemesanan | Penumpang |
| `ticket/travs_ticket_detail_page.dart` | Tiket QR | Penumpang |
| `pickup/travs_pickup_map_picker_page.dart` | Pemilih titik di peta | Penumpang |

---

## HALAMAN 1 — Splash Screen

**File:** `lib/features/launch/travs_splash_page.dart`

**Fungsi:** layar pembuka bergradien dengan logo saat aplikasi memuat sesi login tersimpan.

**Yang bisa diubah saat sidang:** warna gradien, logo, teks. (Detail cara ubah di file `06`.)

---

## HALAMAN 2 — Login & Register

**File:** `lib/features/auth/travs_auth_page.dart`

**Fungsi:** satu halaman yang bisa berganti mode antara **Masuk** dan **Daftar**, plus alur lupa
password dan verifikasi OTP.

**Fitur menarik:**
- Ada **pilihan peran** (penumpang/pengemudi) saat login.
- Data registrasi disimpan sementara (draft) di HP, jadi tidak hilang kalau tak sengaja tertutup.
- Semua pesan error tampil dalam bahasa Indonesia.

**Alur teknis login:** halaman ini memanggil `TravSAuthService.login()` yang ada di
`lib/core/services/travs_auth_service.dart`:
```dart
Future<TravSUserSession> login({required String login, required String password}) async {
  final response = await TravSApiService.postWithFallback(
    '/api/auth/login',
    headers: TravSApiService.jsonHeaders(),
    body: jsonEncode({'login': login.trim(), 'password': password, 'device_name': 'travelku-mobile'}),
  );
  return _handleAuthResponse(response);   // simpan token & data user
}
```

**Penolakan akun admin di mobile:**
```dart
if (session.user.isAdmin) {
  await _revokeUnsupportedAdminSession(session.token);
  throw const TravSAuthException(
    'Akun admin dikelola lewat web admin, bukan lewat aplikasi mobile.',
  );
}
```

---

## HALAMAN 3 — Beranda (Home) ⭐ File Terbesar

**File:** `lib/features/home/travs_home_page.dart` (paling besar, ~1500+ baris)

**Fungsi:** halaman pusat setelah login. Ia menampilkan **dashboard berbeda** tergantung peran, dan
punya **navigasi bawah** (bottom navigation).

**Kenapa file ini besar?** Karena dalam satu file ada: beranda tamu, beranda penumpang, beranda
pengemudi, halaman profil, halaman notifikasi, scanner QR, Manifes pengemudi, dan riwayat. Semua
disatukan dan ditampilkan sesuai peran & tab yang dipilih.

**Cara membacanya:** jangan baca dari atas ke bawah. Cari nama widget/method spesifik pakai Ctrl+F.
Contoh: cari `_buildPassengerDashboard` untuk bagian beranda penumpang.

**Potongan kode — memilih dashboard sesuai peran:**
```dart
AppRolePreview get _role =>
    widget.session?.rolePreview ?? AppRolePreview.guest;

// Saat memuat data, cabang berbeda per peran:
if (_role == AppRolePreview.passenger && widget.session != null) {
  // muat dashboard penumpang, daftar pemesanan, notifikasi
}
if (_role == AppRolePreview.driver && widget.session != null) {
  // muat dashboard pengemudi, daftar perjalanan
}
```

**Potongan kode — membuka detail jadwal saat kartu jadwal ditekan:**
```dart
Future<void> _openScheduleDetail(TravSSchedule schedule) async {
  final result = await Navigator.of(context).push<Object>(
    MaterialPageRoute<Object>(
      builder: (_) => TravSScheduleDetailPage(
        schedule: schedule,
        selectedRole: _role,
        session: widget.session,
        appSettings: _appSettings,
      ),
    ),
  );
  // kalau kembali dengan hasil "berhasil pesan", refresh beranda
  if (result == true) { await _refresh(); }
}
```

> **Poin sidang:** perpindahan halaman di Flutter pakai `Navigator.push` (buka halaman baru) dan
> `Navigator.pop` (kembali). Data bisa dibawa saat kembali (misalnya "pemesanan berhasil").

---

## HALAMAN 4 — Detail Jadwal & Denah Kursi

**File:** `lib/features/schedule/travs_schedule_detail_page.dart`

**Fungsi:** menampilkan info jadwal lengkap (rute, armada, sopir, harga) dan **denah kursi** yang
bisa dipilih penumpang.

**Alur:** penumpang memilih kursi → tekan lanjut → kursi dikunci (memanggil API lock) → pindah ke
halaman pembayaran.

**Yang bisa diubah saat sidang:** warna kursi (tersedia/dipilih/terisi), jumlah kolom denah kursi,
ukuran kotak kursi.

---

## HALAMAN 5 — Form Pemesanan & Pembayaran ⭐

**File:** `lib/features/payment/travs_payment_preview_page.dart` (~2900 baris)

**Fungsi:** halaman terpanjang. Berisi: form data penumpang tiap kursi, pemilihan titik jemput &
turun (dengan peta), input barang bawaan + foto, pilih metode bayar, dan proses submit pemesanan.

**Fitur menarik:**
- **Draft otomatis** — data form disimpan di HP, jadi kalau HP tertutup, data tidak hilang.
- **Peta jemput/turun** — memakai OpenStreetMap lewat `flutter_map`.
- **Deteksi lokasi GPS** — bisa ambil lokasi otomatis.

**Potongan kode — menyimpan draft form ke HP:**
```dart
Future<void> _saveFormDraft() async {
  final prefs = await SharedPreferences.getInstance();
  await prefs.setString(_draftKey, jsonEncode({
    'pickup_address': _pickupAddressController.text,
    'dropoff_address': _dropoffAddressController.text,
    'payment_method': _selectedMethod,
    'passengers': _passengerForms.map((p) => {
      'name': p.nameController.text, 'phone': p.phoneController.text,
    }).toList(),
  }));
}
```

**Potongan kode — membuat pemesanan ke backend (kirim data):**
Payload dibentuk lewat model `TravSCreateBookingPayload` (`lib/core/models/travs_booking_contract.dart`):
```dart
Map<String, dynamic> toJson() {
  return {
    'jadwal_id': jadwalId,
    'seat_numbers': seatNumbers,
    'pickup': {'latitude': pickupLatitude, 'longitude': pickupLongitude, 'address': pickupAddress},
    'dropoff': {'latitude': dropoffLatitude, 'longitude': dropoffLongitude, 'address': dropoffAddress},
    'payment_method': paymentMethod,
    'passengers': passengers.map((item) => item.toJson()).toList(),
    'baggage_items': baggageItems.map((item) => item.toJson()).toList(),
  };
}
```

---

## HALAMAN 6 — Detail Pemesanan

**File:** `lib/features/booking/travs_booking_detail_page.dart`

**Fungsi:** menampilkan ringkasan satu pemesanan: status, rincian harga, penumpang, status
pembayaran, dan tombol untuk membuka tiket.

---

## HALAMAN 7 — Tiket QR ⭐

**File:** `lib/features/ticket/travs_ticket_detail_page.dart`

**Fungsi:** menampilkan tiket ber-QR untuk tiap kursi. QR ini yang di-scan pengemudi.

**Potongan kode — menampilkan QR (pakai package `qr_flutter`):**
```dart
QrImageView(
  data: passenger.ticket!.token,   // token unik dari backend
  version: QrVersions.auto,
  size: 200,
)
```

**Logika penting:** QR hanya muncul kalau pembayaran sudah berhasil. Kalau belum, tampil pesan
"QR tiket belum aktif karena pembayaran belum terverifikasi":
```dart
Text(
  booking.isPaid
      ? 'Tiket belum diterbitkan untuk pemesanan ini.'
      : 'QR tiket belum aktif karena pembayaran belum terverifikasi.',
)
```

---

## HALAMAN 8 — Pemilih Titik di Peta

**File:** `lib/features/pickup/travs_pickup_map_picker_page.dart`

**Fungsi:** membuka peta OpenStreetMap agar penumpang menandai titik jemput/turun secara akurat.
Memakai package `flutter_map` + `latlong2`.

---

## 5.4 Bagian Inti: Cara Mobile Bicara dengan Backend

**File:** `lib/core/services/travs_api_service.dart` (~900+ baris)

Ini "jembatan" antara aplikasi dan backend. Semua panggilan API lewat sini.

**Fitur cerdas — coba beberapa alamat backend otomatis:**
```dart
// Aplikasi mencoba 127.0.0.1:8000 dulu (web/HP), lalu 10.0.2.2:8000 (emulator Android).
// Alamat yang berhasil disimpan agar panggilan berikutnya langsung pakai itu.
```

**Contoh method memanggil API daftar jadwal:**
```dart
Future<TravSHomePayload> loadHomePayload() async {
  final response = await getWithFallback('/api/guest/jadwal');
  final decoded = jsonDecode(response.body);
  // ubah JSON jadi objek Dart yang siap ditampilkan
  return TravSHomePayload.fromJson(decoded['data']);
}
```

---

## 5.5 Bagian Inti: Tema & Warna Mobile

**File:** `lib/core/theme/travs_theme.dart`

Semua warna, font, dan gaya default aplikasi mobile ada di sini. Ini file yang kamu buka kalau
diminta **ubah warna aplikasi mobile** saat sidang.

**Potongan — daftar warna utama:**
```dart
class TravSTheme {
  static const Color navy = Color(0xFF10233F);     // warna utama (biru tua)
  static const Color orange = Color(0xFFF58A2A);   // warna aksen (oranye)
  static const Color success = Color(0xFF1F9D67);  // hijau (berhasil)
  static const Color danger  = Color(0xFFD94F4F);  // merah (bahaya)
  static const Color background = Color(0xFFF4F7FB);
  // ...
}
```

Cara ubah warna dan detail lain ada lengkap di `06_PANDUAN_UBAH_TAMPILAN.md`.

---

## 5.6 Model Data (Bentuk Data dari API)

**Folder:** `lib/core/models/`

Model adalah "cetakan" untuk mengubah JSON dari backend menjadi objek Dart. Contoh:
`travs_booking_contract.dart` berisi `TravSBookingResult` yang membaca respons pemesanan:
```dart
factory TravSBookingResult.fromJson(Map<String, dynamic> json) {
  return TravSBookingResult(
    id: _readInt(json['id']),
    kodeBooking: json['kode_booking'] as String? ?? '-',
    status: json['status'] as String? ?? '-',
    subtotalTiket: _readDouble(json['subtotal_tiket']),
    totalHarga: _readDouble(json['total_harga']),
    // ...
  );
}
```
`fromJson` = mengubah data mentah (JSON) dari backend menjadi objek yang enak dipakai di layar.

---

## Ringkasan Peta Halaman → File (Cheat Sheet Mobile)

| Kalau diminta ubah... | Buka file... |
|-----------------------|--------------|
| Warna/font seluruh aplikasi | `lib/core/theme/travs_theme.dart` |
| Halaman login/register | `lib/features/auth/travs_auth_page.dart` |
| Beranda / dashboard | `lib/features/home/travs_home_page.dart` |
| Denah kursi & detail jadwal | `lib/features/schedule/travs_schedule_detail_page.dart` |
| Form pesan & pembayaran | `lib/features/payment/travs_payment_preview_page.dart` |
| Halaman tiket QR | `lib/features/ticket/travs_ticket_detail_page.dart` |
| Cara ambil data dari server | `lib/core/services/travs_api_service.dart` |

## 5.7 Pembaruan Mobile per 25 Juli 2026

Halaman konfirmasi dan pembayaran mengikuti deadline dari backend. Timer Flutter hanya memberi informasi; API tetap menjadi keputusan akhir. Jika form atau pembayaran sudah kedaluwarsa, aplikasi tidak boleh mempertahankan booking sebagai `menunggu_pembayaran`.

Tampilan pembayaran membedakan nominal transaksi, biaya admin Pakasir, dan total pembayaran bila provider mengirimkannya. Batas pembayaran diletakkan pada informasi perjalanan bagian atas agar mudah terlihat.

Detail pemesanan memakai bahasa netral untuk titik jemput/turun dan barang bawaan. Istilah **Manifes** dipakai pada aplikasi pengemudi. Validasi registrasi/edit profil juga mengikuti backend: email `@gmail.com`, nomor HP hanya 11–12 angka, dan kata sandi 6–8 karakter dengan huruf serta angka/simbol.

Lanjut ke `06_PANDUAN_UBAH_TAMPILAN.md` — ini bab paling penting untuk sidang.
