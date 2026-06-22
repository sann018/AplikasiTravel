<?php

use App\Services\BookingService;
use App\Services\DepartureReminderService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('travs:release-expired-bookings', function (BookingService $bookingService) {
    $released = $bookingService->releaseExpiredResources();

    $this->info("Cleanup selesai. Total kursi/form lock yang dilepas: {$released}.");
})->purpose('Release expired seat locks and cancel expired pending bookings.');

Artisan::command('booking:auto-cancel-expired', function (BookingService $bookingService) {
    // Proses berkala ini mencegah kursi tertahan selamanya saat auto cancel aktif.
    $released = $bookingService->releaseExpiredResources();

    $this->info("Pengecekan pemesanan selesai. Total data yang dilepas: {$released}.");
})->purpose('Cancel expired pending bookings when auto cancel is enabled.');

Artisan::command('travs:close-near-departure-schedules', function (ScheduleLifecycleService $scheduleLifecycleService) {
    $closed = $scheduleLifecycleService->closeOpenSchedulesNearDeparture();

    $this->info("Jadwal yang ditutup otomatis: {$closed}.");
})->purpose('Close published schedules one hour before departure.');

Artisan::command('notifications:departure-reminders', function (DepartureReminderService $reminderService) {
    $sent = $reminderService->sendUpcomingReminders();

    $this->info("Pengingat keberangkatan yang diproses: {$sent}.");
})->purpose('Send reminders for paid bookings departing within one hour.');

Schedule::command('booking:auto-cancel-expired')->everyMinute();
Schedule::command('travs:close-near-departure-schedules')->everyMinute();
Schedule::command('notifications:departure-reminders')->everyFifteenMinutes();
