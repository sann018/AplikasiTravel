<?php

use App\Services\BookingService;
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

Artisan::command('travs:close-near-departure-schedules', function (ScheduleLifecycleService $scheduleLifecycleService) {
    $closed = $scheduleLifecycleService->closeOpenSchedulesNearDeparture();

    $this->info("Jadwal yang ditutup otomatis: {$closed}.");
})->purpose('Close published schedules one hour before departure.');

Schedule::command('travs:release-expired-bookings')->everyMinute();
Schedule::command('travs:close-near-departure-schedules')->everyMinute();
