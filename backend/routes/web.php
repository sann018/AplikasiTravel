<?php

use App\Http\Controllers\Web\Admin\AuthController;
use App\Http\Controllers\Web\Admin\BiayaOperasionalController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\JadwalController;
use App\Http\Controllers\Web\Admin\KendaraanController;
use App\Http\Controllers\Web\Admin\LaporanController;
use App\Http\Controllers\Web\Admin\NotifikasiController;
use App\Http\Controllers\Web\Admin\PembayaranController;
use App\Http\Controllers\Web\Admin\PemesananController;
use App\Http\Controllers\Web\Admin\PengemudiController;
use App\Http\Controllers\Web\Admin\PengaturanController;
use App\Http\Controllers\Web\Admin\RuteController;
use App\Http\Controllers\Web\Admin\SettingsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
        Route::post('/login', [AuthController::class, 'login'])->name('admin.login.store');
    });

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

        Route::get('/kendaraan', [KendaraanController::class, 'index'])->name('admin.kendaraan.index');
        Route::get('/kendaraan/create', [KendaraanController::class, 'create'])->name('admin.kendaraan.create');
        Route::post('/kendaraan', [KendaraanController::class, 'store'])->name('admin.kendaraan.store');
        Route::get('/kendaraan/{kendaraan}/edit', [KendaraanController::class, 'edit'])->name('admin.kendaraan.edit');
        Route::put('/kendaraan/{kendaraan}', [KendaraanController::class, 'update'])->name('admin.kendaraan.update');
        Route::delete('/kendaraan/{kendaraan}', [KendaraanController::class, 'destroy'])->name('admin.kendaraan.destroy');

        Route::get('/pengemudi', [PengemudiController::class, 'index'])->name('admin.pengemudi.index');
        Route::get('/pengemudi/create', [PengemudiController::class, 'create'])->name('admin.pengemudi.create');
        Route::post('/pengemudi', [PengemudiController::class, 'store'])->name('admin.pengemudi.store');
        Route::get('/pengemudi/{pengemudi}/edit', [PengemudiController::class, 'edit'])->name('admin.pengemudi.edit');
        Route::put('/pengemudi/{pengemudi}', [PengemudiController::class, 'update'])->name('admin.pengemudi.update');
        Route::delete('/pengemudi/{pengemudi}', [PengemudiController::class, 'destroy'])->name('admin.pengemudi.destroy');

        Route::get('/rute', [RuteController::class, 'index'])->name('admin.rute.index');
        Route::get('/rute/create', [RuteController::class, 'create'])->name('admin.rute.create');
        Route::post('/rute', [RuteController::class, 'store'])->name('admin.rute.store');
        Route::get('/rute/{rute}/edit', [RuteController::class, 'edit'])->name('admin.rute.edit');
        Route::put('/rute/{rute}', [RuteController::class, 'update'])->name('admin.rute.update');
        Route::delete('/rute/{rute}', [RuteController::class, 'destroy'])->name('admin.rute.destroy');

        Route::get('/jadwal', [JadwalController::class, 'index'])->name('admin.jadwal.index');
        Route::get('/jadwal/create', [JadwalController::class, 'create'])->name('admin.jadwal.create');
        Route::post('/jadwal', [JadwalController::class, 'store'])->name('admin.jadwal.store');
        Route::get('/jadwal/{jadwal}/edit', [JadwalController::class, 'edit'])->name('admin.jadwal.edit');
        Route::put('/jadwal/{jadwal}', [JadwalController::class, 'update'])->name('admin.jadwal.update');
        Route::delete('/jadwal/{jadwal}', [JadwalController::class, 'destroy'])->name('admin.jadwal.destroy');

        Route::get('/pemesanan', [PemesananController::class, 'index'])->name('admin.pemesanan.index');
        Route::get('/pemesanan/{pemesanan}', [PemesananController::class, 'show'])->name('admin.pemesanan.show');
        Route::put('/pemesanan/{pemesanan}', [PemesananController::class, 'update'])->name('admin.pemesanan.update');

        Route::get('/pembayaran', [PembayaranController::class, 'index'])->name('admin.pembayaran.index');
        Route::post('/pembayaran/{pemesanan}/confirm-cash', [PembayaranController::class, 'confirmCash'])->name('admin.pembayaran.confirm-cash');

        Route::get('/biaya-operasional', [BiayaOperasionalController::class, 'index'])->name('admin.biaya.index');
        Route::post('/biaya-operasional', [BiayaOperasionalController::class, 'store'])->name('admin.biaya.store');
        Route::put('/biaya-operasional/{biayaOperasional}', [BiayaOperasionalController::class, 'update'])->name('admin.biaya.update');
        Route::delete('/biaya-operasional/{biayaOperasional}', [BiayaOperasionalController::class, 'destroy'])->name('admin.biaya.destroy');
        Route::get('/rekap-biaya', [BiayaOperasionalController::class, 'rekap'])->name('admin.biaya.rekap');

        Route::get('/laporan/pemesanan', [LaporanController::class, 'pemesanan'])->name('admin.laporan.pemesanan');
        Route::get('/laporan/transaksi', [LaporanController::class, 'transaksi'])->name('admin.laporan.transaksi');
        Route::get('/laporan/pemesanan/export', [LaporanController::class, 'exportPemesanan'])->name('admin.laporan.pemesanan.export');
        Route::get('/laporan/transaksi/export', [LaporanController::class, 'exportTransaksi'])->name('admin.laporan.transaksi.export');

        Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('admin.notifikasi.index');
        Route::delete('/notifikasi/history', [NotifikasiController::class, 'clearHistory'])->name('admin.notifikasi.history.clear');

        Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/settings/general', [SettingsController::class, 'updateGeneral'])->name('admin.settings.general.update');
        Route::post('/settings/booking', [SettingsController::class, 'updateBooking'])->name('admin.settings.booking.update');
        Route::post('/settings/payment', [SettingsController::class, 'updatePayment'])->name('admin.settings.payment.update');
        Route::post('/settings/baggage', [SettingsController::class, 'updateBaggage'])->name('admin.settings.baggage.update');
        Route::post('/settings/notification', [SettingsController::class, 'updateNotification'])->name('admin.settings.notification.update');

        // Alamat lama tetap tersedia agar bookmark dan tautan lama tidak rusak.
        Route::get('/pengaturan', [SettingsController::class, 'index'])->name('admin.pengaturan.index');
        Route::put('/pengaturan', [PengaturanController::class, 'update'])->name('admin.pengaturan.update');
        Route::put('/pengaturan/akun', [PengaturanController::class, 'updateAccount'])->name('admin.pengaturan.account.update');
        Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');
    });
});
