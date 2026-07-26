<?php

namespace Database\Seeders;

use App\Models\DetailPemesanan;
use App\Models\BiayaOperasionalPerjalanan;
use App\Models\Jadwal;
use App\Models\Kendaraan;
use App\Models\KursiJadwal;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\Pengemudi;
use App\Models\Rute;
use App\Models\TiketElektronik;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class UpcomingRouteBookingSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $routes = Rute::query()
                ->where('status_aktif', true)
                ->orderBy('id')
                ->get();
            $drivers = Pengemudi::query()
                ->with('pengguna')
                ->where('status', 'aktif')
                ->where('status_verifikasi', 'terverifikasi')
                ->whereHas('pengguna', fn ($query) => $query
                    ->where('status_aktif', true)
                    ->where('email', 'not like', 'pengemudi.rute.%@ruslytravel.test'))
                ->orderBy('id')
                ->get();
            $vehicles = Kendaraan::query()
                ->where('status_kendaraan', 'aktif')
                ->where('merk_kendaraan', 'not like', 'Toyota Hiace Uji %')
                ->orderBy('id')
                ->get();
            $existingPassengers = User::query()
                ->where('peran', 'penumpang')
                ->where('status_aktif', true)
                ->where('email', 'not like', '%@ruslytravel.test')
                ->orderBy('id')
                ->get();

            if ($drivers->isEmpty() || $vehicles->isEmpty() || $existingPassengers->isEmpty()) {
                throw new RuntimeException(
                    'Jadwal uji membutuhkan rute, pengemudi terverifikasi, armada aktif, dan penumpang yang sudah tersedia.',
                );
            }

            // Pemesanan asli dipindahkan dulu agar tidak ikut hilang bersama jadwal uji lama.
            $this->preserveUserBookingsFromGeneratedSchedules($drivers, $vehicles);
            $this->removePreviouslyGeneratedSchedulesAndDrivers();

            $scheduleCountByDay = [1 => 0, 2 => 0];

            foreach ($routes as $routeIndex => $route) {
                $routeAlreadyScheduled = Jadwal::query()
                    ->where('rute_id', $route->id)
                    ->whereIn('status', ['draft', 'dibuka', 'ditutup', 'berangkat'])
                    ->whereBetween('waktu_berangkat', [
                        now()->addDay()->startOfDay(),
                        now()->addDays(2)->endOfDay(),
                    ])
                    ->exists();

                if ($routeAlreadyScheduled) {
                    continue;
                }

                $preferredDay = ($routeIndex % 2) + 1;
                $assignment = $this->findAvailableAssignment(
                    drivers: $drivers,
                    vehicles: $vehicles,
                    preferredDay: $preferredDay,
                );

                if ($assignment === null) {
                    throw new RuntimeException(
                        "Tidak ada pengemudi atau armada yang tersedia untuk rute {$route->kota_asal} - {$route->kota_tujuan} dalam dua hari ke depan.",
                    );
                }

                $daysAhead = $assignment['days_ahead'];
                $departureHour = 7 + ($scheduleCountByDay[$daysAhead] * 2);
                $scheduleCountByDay[$daysAhead]++;

                $this->createPaidBooking(
                    route: $route,
                    driver: $assignment['driver'],
                    vehicle: $assignment['vehicle'],
                    passenger: $existingPassengers[$routeIndex % $existingPassengers->count()],
                    daysAhead: $daysAhead,
                    departureHour: $departureHour,
                );
            }
        });
    }

    private function preserveUserBookingsFromGeneratedSchedules($drivers, $vehicles): void
    {
        $bookingsBySchedule = Pemesanan::query()
            ->with(['jadwal', 'detailPemesanan'])
            ->where('kode_booking', 'not like', 'TRAVS-UJI-%')
            ->whereHas('jadwal.pengemudi.pengguna', fn ($query) => $query
                ->where('email', 'like', 'pengemudi.rute.%@ruslytravel.test'))
            ->get()
            ->groupBy('jadwal_id');

        foreach ($bookingsBySchedule as $bookings) {
            $oldSchedule = $bookings->first()?->jadwal;
            if (! $oldSchedule) {
                continue;
            }

            $departureDate = $oldSchedule->waktu_berangkat->toDateString();
            $largestSeatNumber = (int) $bookings
                ->flatMap(fn (Pemesanan $booking) => $booking->detailPemesanan)
                ->max('nomor_kursi');
            $driver = $drivers->first(fn (Pengemudi $item) => ! Jadwal::query()
                ->where('pengemudi_id', $item->id)
                ->whereIn('status', ['draft', 'dibuka', 'ditutup', 'berangkat'])
                ->whereDate('waktu_berangkat', $departureDate)
                ->exists());
            $vehicle = $vehicles->first(fn (Kendaraan $item) =>
                $item->kapasitas_kursi >= $largestSeatNumber
                && ! Jadwal::query()
                    ->where('kendaraan_id', $item->id)
                    ->whereIn('status', ['draft', 'dibuka', 'ditutup', 'berangkat'])
                    ->whereDate('waktu_berangkat', $departureDate)
                    ->exists());

            if (! $driver || ! $vehicle) {
                throw new RuntimeException(
                    'Pemesanan pengguna pada jadwal uji lama belum dapat dipindahkan karena pengemudi atau armada tidak tersedia.',
                );
            }

            $replacementSchedule = Jadwal::query()->create([
                'rute_id' => $oldSchedule->rute_id,
                'pengemudi_id' => $driver->id,
                'kendaraan_id' => $vehicle->id,
                'waktu_berangkat' => $oldSchedule->waktu_berangkat,
                'estimasi_tiba' => $oldSchedule->estimasi_tiba,
                'status' => $oldSchedule->status,
            ]);
            $this->seedSeats($replacementSchedule, (int) $vehicle->kapasitas_kursi);

            foreach ($bookings as $booking) {
                foreach ($booking->detailPemesanan as $detail) {
                    $replacementSeat = KursiJadwal::query()
                        ->where('jadwal_id', $replacementSchedule->id)
                        ->where('nomor_kursi', $detail->nomor_kursi)
                        ->firstOrFail();

                    $detail->update(['kursi_jadwal_id' => $replacementSeat->id]);
                    $replacementSeat->update([
                        'status_kursi' => 'dipesan',
                        'updated_at' => now(),
                    ]);
                }

                $booking->update(['jadwal_id' => $replacementSchedule->id]);
            }
        }
    }

    private function removePreviouslyGeneratedSchedulesAndDrivers(): void
    {
        $generatedDriverUserIds = User::query()
            ->where('email', 'like', 'pengemudi.rute.%@ruslytravel.test')
            ->pluck('id');
        $generatedDriverIds = Pengemudi::query()
            ->whereIn('pengguna_id', $generatedDriverUserIds)
            ->pluck('id');
        $generatedBookingScheduleIds = Pemesanan::query()
            ->where('kode_booking', 'like', 'TRAVS-UJI-%')
            ->pluck('jadwal_id');
        $generatedScheduleIds = Jadwal::query()
            ->whereIn('pengemudi_id', $generatedDriverIds)
            ->pluck('id')
            ->merge($generatedBookingScheduleIds)
            ->unique()
            ->values();

        Pemesanan::query()
            ->where('kode_booking', 'like', 'TRAVS-UJI-%')
            ->delete();
        BiayaOperasionalPerjalanan::query()
            ->whereIn('jadwal_id', $generatedScheduleIds)
            ->delete();
        KursiJadwal::query()
            ->whereIn('jadwal_id', $generatedScheduleIds)
            ->delete();
        Jadwal::query()
            ->whereIn('id', $generatedScheduleIds)
            ->delete();
        User::query()
            ->whereIn('id', $generatedDriverUserIds)
            ->delete();
    }

    /**
     * @return array{driver: Pengemudi, vehicle: Kendaraan, days_ahead: int}|null
     */
    private function findAvailableAssignment($drivers, $vehicles, int $preferredDay): ?array
    {
        $candidateDays = [$preferredDay, $preferredDay === 1 ? 2 : 1];

        foreach ($candidateDays as $daysAhead) {
            $departureDate = now()->addDays($daysAhead)->toDateString();
            $driver = $drivers->first(fn (Pengemudi $item) => ! Jadwal::query()
                ->where('pengemudi_id', $item->id)
                ->whereIn('status', ['draft', 'dibuka', 'ditutup', 'berangkat'])
                ->whereDate('waktu_berangkat', $departureDate)
                ->exists());
            $vehicle = $vehicles->first(fn (Kendaraan $item) => ! Jadwal::query()
                ->where('kendaraan_id', $item->id)
                ->whereIn('status', ['draft', 'dibuka', 'ditutup', 'berangkat'])
                ->whereDate('waktu_berangkat', $departureDate)
                ->exists());

            if ($driver && $vehicle) {
                return [
                    'driver' => $driver,
                    'vehicle' => $vehicle,
                    'days_ahead' => $daysAhead,
                ];
            }
        }

        return null;
    }

    private function createPaidBooking(
        Rute $route,
        Pengemudi $driver,
        Kendaraan $vehicle,
        User $passenger,
        int $daysAhead,
        int $departureHour,
    ): void {
        $departureAt = now()
            ->addDays($daysAhead)
            ->startOfDay()
            ->addHours($departureHour);
        $arrivalAt = $departureAt->copy()->addMinutes(
            max(60, (int) $route->estimasi_durasi_menit),
        );

        $schedule = Jadwal::query()->updateOrCreate(
            [
                'rute_id' => $route->id,
                'pengemudi_id' => $driver->id,
                'waktu_berangkat' => $departureAt,
            ],
            [
                'kendaraan_id' => $vehicle->id,
                'estimasi_tiba' => $arrivalAt,
                'status' => 'dibuka',
            ],
        );

        $this->seedSeats($schedule, (int) $vehicle->kapasitas_kursi);

        $seat = KursiJadwal::query()
            ->where('jadwal_id', $schedule->id)
            ->whereDoesntHave('detailPemesanan')
            ->orderBy('nomor_kursi')
            ->firstOrFail();
        $ticketPrice = (int) $route->harga_tiket;
        $bookingCode = sprintf(
            'TRAVS-UJI-R%d-%s',
            $route->id,
            $departureAt->format('Ymd'),
        );

        $booking = Pemesanan::query()->updateOrCreate(
            ['kode_booking' => $bookingCode],
            [
                'pengguna_id' => $passenger->id,
                'jadwal_id' => $schedule->id,
                'jumlah_kursi' => 1,
                'batas_kunci_kursi' => now()->addMinutes(30),
                'batas_pembayaran' => now()->addMinutes(5),
                'lintang_jemput' => -0.0263,
                'bujur_jemput' => 109.3425,
                'alamat_jemput' => "Titik jemput uji {$route->kota_asal}",
                'lintang_turun' => -0.0200,
                'bujur_turun' => 109.3300,
                'alamat_turun' => "Pusat Kota {$route->kota_tujuan}",
                'subtotal_tiket' => $ticketPrice,
                'total_harga' => $ticketPrice,
                'status_pemesanan' => 'terbayar',
            ],
        );

        $detail = DetailPemesanan::query()->updateOrCreate(
            ['kursi_jadwal_id' => $seat->id],
            [
                'pemesanan_id' => $booking->id,
                'nomor_kursi' => $seat->nomor_kursi,
                'nama_penumpang' => $passenger->nama,
                'no_hp_penumpang' => $passenger->no_hp,
                'status_kehadiran' => 'menunggu',
            ],
        );

        Pembayaran::query()->updateOrCreate(
            ['pemesanan_id' => $booking->id],
            [
                'order_id_gateway' => "CASH-{$bookingCode}",
                'gateway_reference' => "UJI-{$bookingCode}",
                'metode_bayar' => 'cash',
                'paid_channel' => 'Data Uji Lokal',
                'nominal_transaksi' => $ticketPrice,
                'biaya_admin_provider' => 0,
                'total_dibayar_penumpang' => $ticketPrice,
                'payment_number' => null,
                'jumlah_bayar' => $ticketPrice,
                'status_bayar' => 'berhasil',
                'gateway_response' => 'Pembayaran data uji sudah dikonfirmasi.',
                'dibayar_pada' => now(),
            ],
        );

        TiketElektronik::query()->firstOrCreate(
            ['detail_pemesanan_id' => $detail->id],
            [
                'kode_qr' => "QR-{$bookingCode}-{$seat->nomor_kursi}",
                'token_unik_qr' => Str::uuid()->toString(),
                'sudah_dipindai' => false,
                'diterbitkan_pada' => now(),
            ],
        );

        $seat->update([
            'status_kursi' => 'dipesan',
            'dikunci_oleh' => null,
            'locked_at' => null,
            'lock_expires_at' => null,
            'updated_at' => now(),
        ]);
    }

    private function seedSeats(Jadwal $schedule, int $capacity): void
    {
        for ($seatNumber = 1; $seatNumber <= $capacity; $seatNumber++) {
            KursiJadwal::query()->firstOrCreate(
                [
                    'jadwal_id' => $schedule->id,
                    'nomor_kursi' => $seatNumber,
                ],
                [
                    'status_kursi' => 'tersedia',
                    'updated_at' => now(),
                ],
            );
        }
    }
}
