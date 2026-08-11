<?php

use App\Http\Controllers\Web\Admin\AuthController;
use App\Http\Controllers\Web\Admin\BiayaOperasionalController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\HomeBannerController;
use App\Http\Controllers\Web\Admin\JadwalController;
use App\Http\Controllers\Web\Admin\KendaraanController;
use App\Http\Controllers\Web\Admin\LaporanController;
use App\Http\Controllers\Web\Admin\NotifikasiController;
use App\Http\Controllers\Web\Admin\PembayaranController;
use App\Http\Controllers\Web\Admin\PetugasLoketController;
use App\Http\Controllers\Web\Admin\PemesananController;
use App\Http\Controllers\Web\Admin\PengaturanController;
use App\Http\Controllers\Web\Admin\PengemudiController;
use App\Http\Controllers\Web\Admin\RuteController;
use App\Http\Controllers\Web\Admin\SettingsController;
use App\Http\Controllers\Web\PasswordResetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'show'])
        ->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:travs-auth')
        ->name('password.update');
    Route::get('/reset-password-selesai', [PasswordResetController::class, 'complete'])
        ->name('password.reset.complete');
});

Route::get('/', function () {
    return Auth::check() && Auth::user()?->isAdmin()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('admin.login');
});

Route::get('/payment/return', function () {
    return view('payment.return');
})->name('payment.return');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('admin.login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:travs-auth')->name('admin.login.store');
    });

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

        Route::get('/banner-beranda', [HomeBannerController::class, 'index'])->name('admin.banner.index');
        Route::post('/banner-beranda', [HomeBannerController::class, 'store'])->middleware('throttle:travs-admin-action')->name('admin.banner.store');
        Route::delete('/banner-beranda/{banner}', [HomeBannerController::class, 'destroy'])->middleware('throttle:travs-admin-action')->name('admin.banner.destroy');

        Route::get('/kendaraan', [KendaraanController::class, 'index'])->name('admin.kendaraan.index');
        Route::get('/kendaraan/create', [KendaraanController::class, 'create'])->name('admin.kendaraan.create');
        Route::post('/kendaraan', [KendaraanController::class, 'store'])->middleware('throttle:travs-admin-action')->name('admin.kendaraan.store');
        Route::get('/kendaraan/{kendaraan}/edit', [KendaraanController::class, 'edit'])->name('admin.kendaraan.edit');
        Route::put('/kendaraan/{kendaraan}', [KendaraanController::class, 'update'])->middleware('throttle:travs-admin-action')->name('admin.kendaraan.update');
        Route::delete('/kendaraan/{kendaraan}', [KendaraanController::class, 'destroy'])->middleware('throttle:travs-admin-action')->name('admin.kendaraan.destroy');

        Route::get('/pengemudi', [PengemudiController::class, 'index'])->name('admin.pengemudi.index');
        Route::get('/pengemudi/create', [PengemudiController::class, 'create'])->name('admin.pengemudi.create');
        Route::post('/pengemudi', [PengemudiController::class, 'store'])->middleware('throttle:travs-admin-action')->name('admin.pengemudi.store');
        Route::get('/pengemudi/{pengemudi}/edit', [PengemudiController::class, 'edit'])->name('admin.pengemudi.edit');
        Route::put('/pengemudi/{pengemudi}', [PengemudiController::class, 'update'])->middleware('throttle:travs-admin-action')->name('admin.pengemudi.update');
        Route::delete('/pengemudi/{pengemudi}', [PengemudiController::class, 'destroy'])->middleware('throttle:travs-admin-action')->name('admin.pengemudi.destroy');

        Route::get('/petugas-loket', [PetugasLoketController::class, 'index'])->name('admin.petugas-loket.index');
        Route::get('/petugas-loket/create', [PetugasLoketController::class, 'create'])->name('admin.petugas-loket.create');
        Route::post('/petugas-loket', [PetugasLoketController::class, 'store'])->middleware('throttle:travs-admin-action')->name('admin.petugas-loket.store');
        Route::get('/petugas-loket/{petugas}', [PetugasLoketController::class, 'show'])->name('admin.petugas-loket.show');
        Route::get('/petugas-loket/{petugas}/edit', [PetugasLoketController::class, 'edit'])->name('admin.petugas-loket.edit');
        Route::put('/petugas-loket/{petugas}', [PetugasLoketController::class, 'update'])->middleware('throttle:travs-admin-action')->name('admin.petugas-loket.update');
        Route::patch('/petugas-loket/{petugas}/status', [PetugasLoketController::class, 'toggleStatus'])->middleware('throttle:travs-admin-action')->name('admin.petugas-loket.status');
        Route::post('/petugas-loket/{petugas}/aktivasi', [PetugasLoketController::class, 'sendActivation'])->middleware('throttle:travs-admin-action')->name('admin.petugas-loket.aktivasi');

        Route::get('/rute', [RuteController::class, 'index'])->name('admin.rute.index');
        Route::get('/rute/create', [RuteController::class, 'create'])->name('admin.rute.create');
        Route::post('/rute', [RuteController::class, 'store'])->middleware('throttle:travs-admin-action')->name('admin.rute.store');
        Route::get('/rute/{rute}/edit', [RuteController::class, 'edit'])->name('admin.rute.edit');
        Route::put('/rute/{rute}', [RuteController::class, 'update'])->middleware('throttle:travs-admin-action')->name('admin.rute.update');
        Route::delete('/rute/{rute}', [RuteController::class, 'destroy'])->middleware('throttle:travs-admin-action')->name('admin.rute.destroy');

        Route::get('/jadwal', [JadwalController::class, 'index'])->name('admin.jadwal.index');
        Route::get('/jadwal/create', [JadwalController::class, 'create'])->name('admin.jadwal.create');
        Route::post('/jadwal', [JadwalController::class, 'store'])->middleware('throttle:travs-admin-action')->name('admin.jadwal.store');
        Route::get('/jadwal/{jadwal}', [JadwalController::class, 'show'])->name('admin.jadwal.show');
        Route::get('/jadwal/{jadwal}/edit', [JadwalController::class, 'edit'])->name('admin.jadwal.edit');
        Route::put('/jadwal/{jadwal}', [JadwalController::class, 'update'])->middleware('throttle:travs-admin-action')->name('admin.jadwal.update');
        Route::delete('/jadwal/{jadwal}', [JadwalController::class, 'destroy'])->middleware('throttle:travs-admin-action')->name('admin.jadwal.destroy');

        Route::get('/pemesanan', [PemesananController::class, 'index'])->name('admin.pemesanan.index');
        Route::delete('/pemesanan/riwayat-lama', [PemesananController::class, 'destroyOld'])->middleware('throttle:travs-admin-action')->name('admin.pemesanan.old.destroy');
        Route::get('/pemesanan/{pemesanan}', [PemesananController::class, 'show'])->name('admin.pemesanan.show');
        Route::put('/pemesanan/{pemesanan}', [PemesananController::class, 'update'])->middleware('throttle:travs-admin-action')->name('admin.pemesanan.update');
        // Monitor tiket web Admin sengaja tidak didaftarkan. Verifikasi tiket
        // tetap dilakukan Pengemudi melalui pemindai QR pada aplikasi mobile.

        Route::get('/pembayaran', [PembayaranController::class, 'index'])->name('admin.pembayaran.index');
        Route::get('/pembayaran/{pembayaran}', [PembayaranController::class, 'show'])->name('admin.pembayaran.show');
        Route::post('/pembayaran/{pemesanan}/confirm-cash', [PembayaranController::class, 'confirmCash'])->middleware('throttle:travs-admin-action')->name('admin.pembayaran.confirm-cash');

        Route::get('/biaya-operasional', [BiayaOperasionalController::class, 'index'])->name('admin.biaya.index');
        Route::get('/biaya-operasional/create', [BiayaOperasionalController::class, 'create'])->name('admin.biaya.create');
        Route::get('/biaya-operasional/export', [BiayaOperasionalController::class, 'export'])->name('admin.biaya.export');
        Route::post('/biaya-operasional/categories', [BiayaOperasionalController::class, 'storeCategory'])->middleware('throttle:travs-admin-action')->name('admin.biaya.categories.store');
        Route::delete('/biaya-operasional/categories', [BiayaOperasionalController::class, 'destroyCategory'])->middleware('throttle:travs-admin-action')->name('admin.biaya.categories.destroy');
        Route::get('/biaya-operasional/{biayaOperasional}/edit', [BiayaOperasionalController::class, 'edit'])->name('admin.biaya.edit');
        Route::post('/biaya-operasional', [BiayaOperasionalController::class, 'store'])->middleware('throttle:travs-admin-action')->name('admin.biaya.store');
        Route::put('/biaya-operasional/{biayaOperasional}', [BiayaOperasionalController::class, 'update'])->middleware('throttle:travs-admin-action')->name('admin.biaya.update');
        Route::delete('/biaya-operasional/{biayaOperasional}', [BiayaOperasionalController::class, 'destroy'])->middleware('throttle:travs-admin-action')->name('admin.biaya.destroy');
        Route::get('/rekap-biaya', [BiayaOperasionalController::class, 'rekap'])->name('admin.biaya.rekap');

        Route::get('/laporan/pemesanan', [LaporanController::class, 'pemesanan'])->name('admin.laporan.pemesanan');
        Route::get('/laporan/transaksi', [LaporanController::class, 'transaksi'])->name('admin.laporan.transaksi');
        Route::get('/laporan/pemesanan/export', [LaporanController::class, 'exportPemesanan'])->name('admin.laporan.pemesanan.export');
        Route::get('/laporan/transaksi/export', [LaporanController::class, 'exportTransaksi'])->name('admin.laporan.transaksi.export');

        Route::get('/notifikasi/admin', [NotifikasiController::class, 'admin'])->name('admin.notifikasi.admin');
        Route::delete('/notifikasi/admin/history', [NotifikasiController::class, 'clearAdminHistory'])->middleware('throttle:travs-admin-action')->name('admin.notifikasi.admin.history.clear');
        Route::get('/notifikasi/user', [NotifikasiController::class, 'users'])->name('admin.notifikasi.user');
        Route::delete('/notifikasi/user/history', [NotifikasiController::class, 'clearUserHistory'])->middleware('throttle:travs-admin-action')->name('admin.notifikasi.user.history.clear');
        Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('admin.notifikasi.index');
        Route::delete('/notifikasi/history', [NotifikasiController::class, 'clearHistory'])->middleware('throttle:travs-admin-action')->name('admin.notifikasi.history.clear');

        Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/settings/general', [SettingsController::class, 'updateGeneral'])->middleware('throttle:travs-admin-action')->name('admin.settings.general.update');
        Route::post('/settings/important-information', [SettingsController::class, 'storeImportantInformation'])->middleware('throttle:travs-admin-action')->name('admin.settings.important-information.store');
        Route::delete('/settings/important-information/{informationId}', [SettingsController::class, 'destroyImportantInformation'])->middleware('throttle:travs-admin-action')->name('admin.settings.important-information.destroy');
        Route::post('/settings/booking', [SettingsController::class, 'updateBooking'])->middleware('throttle:travs-admin-action')->name('admin.settings.booking.update');
        Route::post('/settings/payment', [SettingsController::class, 'updatePayment'])->middleware('throttle:travs-admin-action')->name('admin.settings.payment.update');
        Route::post('/settings/baggage', [SettingsController::class, 'updateBaggage'])->middleware('throttle:travs-admin-action')->name('admin.settings.baggage.update');
        Route::post('/settings/notification', [SettingsController::class, 'updateNotification'])->middleware('throttle:travs-admin-action')->name('admin.settings.notification.update');

        // Alamat lama tetap tersedia agar bookmark dan tautan lama tidak rusak.
        Route::get('/pengaturan', [SettingsController::class, 'index'])->name('admin.pengaturan.index');
        Route::put('/pengaturan', [PengaturanController::class, 'update'])->middleware('throttle:travs-admin-action')->name('admin.pengaturan.update');
        Route::put('/pengaturan/akun', [PengaturanController::class, 'updateAccount'])->middleware('throttle:travs-admin-action')->name('admin.pengaturan.account.update');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:travs-admin-action')->name('admin.logout');
    });
});
