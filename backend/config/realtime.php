<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Konfigurasi real-time untuk aplikasi mobile
    |--------------------------------------------------------------------------
    |
    | Host publik boleh dikosongkan saat lokal. Backend akan memakai host dari
    | request API, misalnya 10.0.2.2 pada emulator Android.
    |
    */
    'seat_updates_enabled' => env('REALTIME_SEAT_UPDATES_ENABLED', true),
    'public_host' => env('REVERB_PUBLIC_HOST'),
    'public_port' => env('REVERB_PUBLIC_PORT', env('REVERB_SERVER_PORT', 6001)),
    'public_scheme' => env('REVERB_PUBLIC_SCHEME', 'ws'),
];
