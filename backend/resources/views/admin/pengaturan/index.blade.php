@extends('admin.layouts.app')

@section('title', 'Pengaturan Sistem')
@section('page-title', 'Pengaturan Sistem')
@section('page-description', 'Kelola aturan utama aplikasi.')

@php
    $allSettings = collect($settings)->flatten(1)->keyBy('key');
    $settingValue = fn (string $key, mixed $default = '') => old($key, data_get($allSettings->get($key), 'typed_value', $default));
    $settingEnabled = fn (string $key, bool $default = false) => filter_var($settingValue($key, $default), FILTER_VALIDATE_BOOL);

    $logoPath = (string) $settingValue('app_logo');
    $logoPreviewUrl = null;
    if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
        $logoPreviewUrl = $logoPath;
    } elseif ($logoPath !== '' && file_exists(public_path(ltrim($logoPath, '/')))) {
        $logoPreviewUrl = asset(ltrim($logoPath, '/'));
    } elseif ($logoPath !== '' && file_exists(public_path('storage/'.ltrim($logoPath, '/')))) {
        $logoPreviewUrl = asset('storage/'.ltrim($logoPath, '/'));
    }

    $tabLabels = [
        'account' => 'Akun Admin',
        'general' => 'Umum',
        'booking' => 'Pemesanan',
        'payment' => 'Pembayaran',
        'banner' => 'Banner Beranda',
    ];
@endphp

@section('content')
    <div class="travs-settings-page">
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Pengaturan belum dapat disimpan.</strong>
                <div>Periksa kembali kolom yang diberi tanda merah.</div>
            </div>
        @endif

        <section class="travs-settings-tabs collapsible-section mb-3" data-collapsible-section>
            <div class="collapsible-section__header" data-collapse-header>
                <div class="travs-settings-tabs-copy">
                    <span class="travs-settings-label">Control Center</span>
                </div>
                @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-control-center-body'])
            </div>

            <div id="settings-control-center-body" class="collapsible-section__body" data-collapse-body>
                {{-- Tombol simpan dipisah per tab supaya admin tidak mengubah semua konfigurasi tanpa sengaja. --}}
                <div class="travs-settings-tab-list" role="tablist" aria-label="Tab pengaturan sistem">
                    @foreach ($tabLabels as $tabKey => $tabLabel)
                        <button type="button" class="travs-settings-tab {{ $activeTab === $tabKey ? 'is-active' : '' }}" data-settings-tab="{{ $tabKey }}" role="tab" aria-controls="settings-{{ $tabKey }}" aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}">
                            {{ $tabLabel }}
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="travs-settings-grid">
            <form id="settings-account" method="POST" action="{{ route('admin.pengaturan.account.update') }}" class="travs-card travs-admin-account-card travs-settings-group travs-settings-pane collapsible-section card" data-settings-pane="account" data-collapsible-section @if ($activeTab !== 'account') hidden @endif>
                @csrf
                @method('PUT')
                <div class="collapsible-section__header" data-collapse-header>
                    <div class="travs-admin-account-head flex-grow-1">
                        <div>
                            <span class="travs-settings-label">Akun Admin</span>
                            <h3>Email dan Password</h3>
                            <p>Perbarui email login atau ganti password dashboard admin.</p>
                        </div>
                        <div class="travs-admin-account-badge">
                            <span>Akun aktif</span>
                            <strong>{{ $adminUser->email }}</strong>
                        </div>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-admin-account-body'])
                </div>

                <div id="settings-admin-account-body" class="collapsible-section__body" data-collapse-body>
                    {{-- Akun admin diletakkan di Control Center agar semua pengaturan sistem berada dalam satu area. --}}
                    <div class="travs-admin-account-form">
                        <div class="travs-admin-account-primary">
                            <div class="travs-field">
                                <label for="admin_email" class="form-label">Email Admin</label>
                                <input id="admin_email" name="email" type="email" class="travs-input @error('email') is-invalid @enderror form-control" value="{{ old('email', $adminUser->email) }}" required autocomplete="email" pattern="[A-Za-z0-9._%+-]+@gmail\.com">
                                <div class="travs-field-meta">Gunakan alamat email @gmail.com.</div>
                                @include('admin.partials.field-error', ['name' => 'email'])
                            </div>
                            <div class="travs-field">
                                <label for="current_password" class="form-label">Password Lama</label>
                                <div class="travs-password-control">
                                    <input id="current_password" name="current_password" type="password" class="travs-input @error('current_password') is-invalid @enderror form-control" required autocomplete="current-password" placeholder="Masukkan password lama">
                                    <button type="button" class="travs-password-toggle" data-password-toggle data-password-target="current_password" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi" aria-pressed="false">
                                        <span data-password-show-icon><i data-feather="eye"></i></span>
                                        <span class="d-none" data-password-hide-icon><i data-feather="eye-off"></i></span>
                                    </button>
                                </div>
                                @include('admin.partials.field-error', ['name' => 'current_password'])
                            </div>
                        </div>
                        <div class="travs-admin-password-panel">
                            <div class="travs-admin-password-copy">
                                <h4>Ganti Password</h4>
                                <p>Gunakan 6-8 karakter berisi huruf dan minimal satu angka atau simbol.</p>
                            </div>
                            <div class="travs-form-grid-2 d-grid">
                                <div class="travs-field">
                                    <label for="password" class="form-label">Password Baru</label>
                                    <div class="travs-password-control">
                                        <input id="password" name="password" type="password" class="travs-input @error('password') is-invalid @enderror form-control" autocomplete="new-password" minlength="6" maxlength="8" placeholder="6-8 karakter">
                                        <button type="button" class="travs-password-toggle" data-password-toggle data-password-target="password" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi" aria-pressed="false">
                                            <span data-password-show-icon><i data-feather="eye"></i></span>
                                            <span class="d-none" data-password-hide-icon><i data-feather="eye-off"></i></span>
                                        </button>
                                    </div>
                                    @include('admin.partials.field-error', ['name' => 'password'])
                                </div>
                                <div class="travs-field">
                                    <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
                                    <div class="travs-password-control">
                                        <input id="password_confirmation" name="password_confirmation" type="password" class="travs-input form-control" autocomplete="new-password" minlength="6" maxlength="8" placeholder="Ulangi password baru">
                                        <button type="button" class="travs-password-toggle" data-password-toggle data-password-target="password_confirmation" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi" aria-pressed="false">
                                            <span data-password-show-icon><i data-feather="eye"></i></span>
                                            <span class="d-none" data-password-hide-icon><i data-feather="eye-off"></i></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="travs-form-actions travs-admin-account-actions d-flex justify-content-end">
                            <button type="submit" class="travs-button btn btn-primary">Simpan Perubahan Akun</button>
                        </div>
                    </div>
                </div>
            </form>

            <form id="settings-general" method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data" class="travs-card travs-settings-group travs-settings-pane collapsible-section card" data-settings-pane="general" data-collapsible-section @if ($activeTab !== 'general') hidden @endif>
                @csrf
                <div class="travs-settings-group-header collapsible-section__header" data-collapse-header>
                    <div>
                        <span class="travs-settings-label">Umum</span>
                        <h3>Identitas dan Kontak Aplikasi</h3>
                        <p>Informasi utama yang digunakan pada web admin dan aplikasi mobile.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-general-body'])
                </div>

                <div id="settings-general-body" class="collapsible-section__body" data-collapse-body>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="app_name">Nama Aplikasi</label>
                        <input id="app_name" name="app_name" type="text" maxlength="100" class="form-control @error('app_name') is-invalid @enderror" value="{{ $settingValue('app_name') }}" required>
                        <div class="form-text">Nama aplikasi yang tampil di web admin dan mobile.</div>
                        @include('admin.partials.field-error', ['name' => 'app_name'])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="app_logo">Logo Aplikasi</label>
                        <input id="app_logo" name="app_logo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('app_logo') is-invalid @enderror">
                        <div class="form-text">Pilih JPG, JPEG, PNG, atau WEBP. Maksimal 2 MB.</div>
                        @include('admin.partials.field-error', ['name' => 'app_logo'])
                    </div>
                    <div class="col-12">
                        <button
                            type="button"
                            class="travs-settings-logo-preview travs-photo-preview-trigger"
                            data-logo-preview-wrapper
                            data-preview-src="{{ $logoPreviewUrl ?? '' }}"
                            aria-label="Lihat logo aplikasi"
                            @if (! $logoPreviewUrl) hidden @endif
                        >
                            <span>Preview logo</span>
                            <img src="{{ $logoPreviewUrl ?? '' }}" alt="Preview logo aplikasi" data-logo-preview>
                        </button>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin_contact">Kontak Admin</label>
                        <input id="admin_contact" name="admin_contact" type="text" inputmode="numeric" minlength="11" maxlength="12" pattern="[0-9]{11,12}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12)" class="form-control @error('admin_contact') is-invalid @enderror" value="{{ $settingValue('admin_contact') }}" required>
                        <div class="form-text">Masukkan 11 sampai 12 digit angka.</div>
                        @include('admin.partials.field-error', ['name' => 'admin_contact'])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="service_whatsapp">WhatsApp Layanan</label>
                        <input id="service_whatsapp" name="service_whatsapp" type="text" inputmode="numeric" minlength="11" maxlength="12" pattern="[0-9]{11,12}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12)" class="form-control @error('service_whatsapp') is-invalid @enderror" value="{{ $settingValue('service_whatsapp') }}" required>
                        <div class="form-text">Masukkan 11 sampai 12 digit angka.</div>
                        @include('admin.partials.field-error', ['name' => 'service_whatsapp'])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="system_email">Email Sistem</label>
                        <input id="system_email" name="system_email" type="email" class="form-control @error('system_email') is-invalid @enderror" value="{{ $settingValue('system_email') }}" required>
                        <div class="form-text">Email resmi untuk komunikasi sistem.</div>
                        @include('admin.partials.field-error', ['name' => 'system_email'])
                    </div>
                </div>

                <div class="travs-form-actions mt-4 d-flex justify-content-end">
                    <button type="submit" class="travs-button btn btn-primary">Simpan Pengaturan Umum</button>
                </div>
                </div>
            </form>

            <section id="settings-important-information" class="travs-card travs-settings-group travs-settings-pane collapsible-section card" data-settings-pane="general" data-collapsible-section @if ($activeTab !== 'general') hidden @endif>
                <div class="travs-settings-group-header collapsible-section__header" data-collapse-header>
                    <div>
                        <span class="travs-settings-label">Informasi Penting</span>
                        <h3>Informasi Beranda Aplikasi</h3>
                        <p>Tambahkan pengumuman singkat yang perlu dibaca tamu dan penumpang.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-important-information-body'])
                </div>

                <div id="settings-important-information-body" class="collapsible-section__body" data-collapse-body>
                    <form method="POST" action="{{ route('admin.settings.important-information.store') }}" class="travs-settings-subsection mt-0">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="important_title">Judul Informasi</label>
                                <input id="important_title" name="title" type="text" maxlength="100" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                                @include('admin.partials.field-error', ['name' => 'title'])
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="important_description">Isi Informasi</label>
                                <textarea id="important_description" name="description" rows="3" maxlength="1000" class="form-control @error('description') is-invalid @enderror" required>{{ old('description') }}</textarea>
                                @include('admin.partials.field-error', ['name' => 'description'])
                            </div>
                        </div>
                        <div class="travs-form-actions mt-3 d-flex justify-content-end">
                            <button type="submit" class="travs-button btn btn-primary">Tambah Informasi</button>
                        </div>
                    </form>

                    <div class="travs-settings-subsection mt-3">
                        <div class="travs-settings-subsection-head">
                            <h4>Informasi yang Sedang Tampil</h4>
                            <p>Informasi ini dibaca aplikasi dari API saat beranda dimuat ulang.</p>
                        </div>
                        @forelse ($importantInformation as $information)
                            <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 border rounded-3 p-3 mb-2">
                                <div>
                                    <strong>{{ $information['title'] }}</strong>
                                    <p class="mb-0 mt-1 text-muted">{{ $information['description'] }}</p>
                                </div>
                                <form method="POST" action="{{ route('admin.settings.important-information.destroy', $information['id']) }}" onsubmit="return confirm('Hapus informasi penting ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Belum ada informasi penting yang ditampilkan.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <form id="settings-booking" method="POST" action="{{ route('admin.settings.booking.update') }}" class="travs-card travs-settings-group travs-settings-pane collapsible-section card" data-settings-pane="booking" data-collapsible-section @if ($activeTab !== 'booking') hidden @endif>
                @csrf
                <div class="travs-settings-group-header collapsible-section__header" data-collapse-header>
                    <div>
                        <span class="travs-settings-label">Pemesanan</span>
                        <h3>Aturan Kursi dan Waktu Pemesanan</h3>
                        <p>Atur jumlah kursi dan lama penguncian selama proses pemesanan.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-booking-body'])
                </div>

                <div id="settings-booking-body" class="collapsible-section__body" data-collapse-body>
                <div class="row g-3">
                    {{-- Nilai lama tetap dikirim agar penyederhanaan UI tidak mengubah proses kedaluwarsa di backend. --}}
                    <input type="hidden" name="auto_cancel_booking" value="{{ $settingEnabled('auto_cancel_booking') ? '1' : '0' }}">
                    <div class="col-md-4">
                        <label class="form-label" for="max_seat_per_booking">Maksimal Kursi per Pemesanan</label>
                        <div class="input-group">
                            <input id="max_seat_per_booking" name="max_seat_per_booking" type="number" min="1" class="form-control @error('max_seat_per_booking') is-invalid @enderror" value="{{ $settingValue('max_seat_per_booking') }}" required>
                            <span class="input-group-text">kursi</span>
                        </div>
                        @include('admin.partials.field-error', ['name' => 'max_seat_per_booking'])
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="seat_lock_form_minutes">Seat Lock Form</label>
                        <div class="input-group">
                            <input id="seat_lock_form_minutes" name="seat_lock_form_minutes" type="number" min="1" class="form-control @error('seat_lock_form_minutes') is-invalid @enderror" value="{{ $settingValue('seat_lock_form_minutes') }}" required>
                            <span class="input-group-text">menit</span>
                        </div>
                        <div class="form-text">Durasi kursi dikunci saat penumpang mengisi form.</div>
                        @include('admin.partials.field-error', ['name' => 'seat_lock_form_minutes'])
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="seat_lock_payment_minutes">Seat Lock Payment</label>
                        <div class="input-group">
                            <input id="seat_lock_payment_minutes" name="seat_lock_payment_minutes" type="number" min="1" class="form-control @error('seat_lock_payment_minutes') is-invalid @enderror" value="{{ $settingValue('seat_lock_payment_minutes') }}" required>
                            <span class="input-group-text">menit</span>
                        </div>
                        <div class="form-text">Durasi kursi dikunci saat menunggu pembayaran.</div>
                        @include('admin.partials.field-error', ['name' => 'seat_lock_payment_minutes'])
                    </div>
                </div>

                <div class="alert alert-info mt-3 mb-0" role="alert">
                    Timer pada aplikasi mobile membantu pengguna melihat sisa waktu. Keputusan akhir masa berlaku tetap diperiksa backend.
                </div>
                <div class="travs-form-actions mt-4 d-flex justify-content-end">
                    <button type="submit" class="travs-button btn btn-primary">Simpan Pengaturan Pemesanan</button>
                </div>
                </div>
            </form>

            <form id="settings-payment" method="POST" action="{{ route('admin.settings.payment.update') }}" class="travs-card travs-settings-group travs-settings-pane collapsible-section card" data-settings-pane="payment" data-collapsible-section @if ($activeTab !== 'payment') hidden @endif>
                @csrf
                <input type="hidden" name="payment_transfer_enabled" value="{{ $settingEnabled('payment_transfer_enabled', true) ? '1' : '0' }}">
                <input type="hidden" name="payment_qris_enabled" value="{{ $settingEnabled('payment_qris_enabled', true) ? '1' : '0' }}">
                <div class="travs-settings-group-header collapsible-section__header" data-collapse-header>
                    <div>
                        <span class="travs-settings-label">Pembayaran</span>
                        <h3>Catatan Pembayaran</h3>
                        <p>Kelola petunjuk pembayaran tunai yang ditampilkan kepada penumpang.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-payment-body'])
                </div>

                <div id="settings-payment-body" class="collapsible-section__body" data-collapse-body>
                {{-- Toggle tunai dan rekening transfer lama tidak diedit lagi dari admin agar sumber pembayaran tetap mengikuti Pakasir. --}}
                <div>
                    <label class="form-label" for="payment_cash_note">Catatan Pembayaran Tunai</label>
                    <textarea id="payment_cash_note" name="payment_cash_note" rows="3" maxlength="1000" class="form-control @error('payment_cash_note') is-invalid @enderror" data-cash-note>{{ $settingValue('payment_cash_note') }}</textarea>
                    <div class="form-text">Petunjuk yang dibaca penumpang ketika memilih pembayaran tunai.</div>
                    @include('admin.partials.field-error', ['name' => 'payment_cash_note'])
                </div>
                <div class="travs-form-actions mt-4 d-flex justify-content-end">
                    <button type="submit" class="travs-button btn btn-primary">Simpan Pengaturan Pembayaran</button>
                </div>
                </div>
            </form>

            {{-- Form tarif bagasi lama tidak dirender. Key database dipertahankan agar transaksi lama tetap kompatibel. --}}
            @if (false)
            <form id="settings-baggage" method="POST" action="{{ route('admin.settings.baggage.update') }}" class="travs-card travs-settings-group travs-settings-pane collapsible-section card" data-settings-pane="baggage" data-collapsible-section hidden>
                @csrf
                <div class="travs-settings-group-header collapsible-section__header" data-collapse-header>
                    <div>
                        <span class="travs-settings-label">Bagasi</span>
                        <h3>Aturan Barang Bawaan</h3>
                        <p>Atur metode hitung, batas gratis, biaya tambahan, dan kapasitas kendaraan.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-baggage-body'])
                </div>

                <div id="settings-baggage-body" class="collapsible-section__body" data-collapse-body>
                <section class="travs-settings-subsection">
                    <div class="travs-settings-subsection-head">
                        <h4>Status Perhitungan Bagasi</h4>
                        <p>Switch memudahkan admin mengaktifkan aturan yang digunakan.</p>
                    </div>
                    <div class="row g-3">
                        @foreach ([
                            'baggage_weight_enabled' => ['Perhitungan Berat', 'Aturan kilogram'],
                            'baggage_item_enabled' => ['Perhitungan Item', 'Aturan jumlah barang'],
                            'baggage_photo_required' => ['Foto Bagasi Wajib', 'Bukti foto barang bawaan'],
                        ] as $switchKey => [$switchLabel, $switchHelp])
                            <div class="col-md-6 col-xl-3">
                                <div class="travs-settings-switch-card">
                                    <div>
                                        <strong>{{ $switchLabel }}</strong>
                                        <span>{{ $switchHelp }}</span>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input type="hidden" name="{{ $switchKey }}" value="0">
                                        <input id="{{ $switchKey }}" class="form-check-input" type="checkbox" name="{{ $switchKey }}" value="1" {{ $settingEnabled($switchKey) ? 'checked' : '' }} data-rule-toggle="{{ str_replace(['baggage_', '_enabled'], '', $switchKey) }}">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Satuan ditampilkan agar nilai angka tidak mudah disalahartikan admin. --}}
                <section class="travs-settings-subsection" data-rule-section="weight">
                    <div class="travs-settings-subsection-head"><h4>Aturan Berat</h4></div>
                    <div class="row g-3">
                        @foreach ([
                            'baggage_free_weight_kg' => ['Berat Gratis', 'kg', false],
                            'baggage_max_weight_per_person_kg' => ['Berat Maksimal per Orang', 'kg', false],
                            'baggage_extra_fee_per_kg' => ['Biaya Tambahan per Kg', 'Rp', true],
                        ] as $key => [$label, $unit, $currency])
                            <div class="col-md-4">
                                <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                                <div class="input-group">
                                    @if ($currency)<span class="input-group-text">Rp</span>@endif
                                    <input id="{{ $key }}" name="{{ $key }}" type="number" min="0" step="0.01" class="form-control @error($key) is-invalid @enderror" value="{{ $settingValue($key) }}" data-rule-input="weight" required>
                                    @if (! $currency)<span class="input-group-text">{{ $unit }}</span>@endif
                                </div>
                                @include('admin.partials.field-error', ['name' => $key])
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="travs-settings-subsection" data-rule-section="item">
                    <div class="travs-settings-subsection-head"><h4>Aturan Jumlah Item</h4></div>
                    <div class="row g-3">
                        @foreach ([
                            'baggage_free_item_count' => ['Jumlah Item Gratis', false],
                            'baggage_max_item_per_person' => ['Jumlah Item Maksimal', false],
                            'baggage_extra_fee_per_item' => ['Biaya Tambahan per Item', true],
                        ] as $key => [$label, $currency])
                            <div class="col-md-4">
                                <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                                <div class="input-group">
                                    @if ($currency)<span class="input-group-text">Rp</span>@endif
                                    <input id="{{ $key }}" name="{{ $key }}" type="number" min="0" step="{{ $currency ? '0.01' : '1' }}" class="form-control @error($key) is-invalid @enderror" value="{{ $settingValue($key) }}" data-rule-input="item" required>
                                    @if (! $currency)<span class="input-group-text">item</span>@endif
                                </div>
                                @include('admin.partials.field-error', ['name' => $key])
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Tarif kecil/besar tidak ditampilkan lagi karena versi ini memakai aturan berat + item agar lebih mudah dipahami admin. --}}
                <section class="travs-settings-subsection">
                    <div class="travs-settings-subsection-head">
                        <h4>Barang Besar / Kendaraan</h4>
                        <p>Tarif ini dipakai untuk barang khusus yang masuk bagasi, seperti sepeda, motor, atau beko.</p>
                    </div>
                    <div class="travs-switch-grid mb-3">
                        <div class="travs-switch-card">
                            <div>
                                <strong>Tarif Barang Besar</strong>
                                <p>{{ $settingValue('baggage_large_vehicle_enabled') ? 'Tarif khusus sedang aktif.' : 'Tarif khusus sedang nonaktif.' }}</p>
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="baggage_large_vehicle_enabled" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="baggage_large_vehicle_enabled" name="baggage_large_vehicle_enabled" value="1" @checked($settingValue('baggage_large_vehicle_enabled'))>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        @foreach ([
                            'baggage_bicycle_fee' => 'Sepeda',
                            'baggage_motorcycle_fee' => 'Sepeda Motor',
                            'baggage_heavy_item_fee' => 'Beko / Barang Berat',
                        ] as $key => $label)
                            <div class="col-md-3">
                                <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input id="{{ $key }}" name="{{ $key }}" type="number" min="0" step="0.01" class="form-control @error($key) is-invalid @enderror" value="{{ $settingValue($key) }}" required>
                                </div>
                                @include('admin.partials.field-error', ['name' => $key])
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="travs-settings-subsection">
                    <div class="travs-settings-subsection-head"><h4>Batas Kendaraan</h4></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="baggage_max_total_weight_per_vehicle">Berat Maksimal Bagasi Kendaraan</label>
                            <div class="input-group">
                                <input id="baggage_max_total_weight_per_vehicle" name="baggage_max_total_weight_per_vehicle" type="number" min="0.01" step="0.01" class="form-control @error('baggage_max_total_weight_per_vehicle') is-invalid @enderror" value="{{ $settingValue('baggage_max_total_weight_per_vehicle') }}" required>
                                <span class="input-group-text">kg</span>
                            </div>
                            @include('admin.partials.field-error', ['name' => 'baggage_max_total_weight_per_vehicle'])
                        </div>
                    </div>
                </section>

                <section class="travs-settings-subsection travs-baggage-simulator" data-baggage-simulator>
                    <div class="travs-settings-subsection-head">
                        <h4>Simulasi Bagasi</h4>
                        <p>Masukkan contoh barang untuk memperkirakan biaya berdasarkan nilai form saat ini.</p>
                    </div>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label" for="simulation_weight">Berat Bagasi</label>
                            <div class="input-group"><input id="simulation_weight" type="number" min="0" step="0.1" class="form-control" value="0" data-simulation-weight><span class="input-group-text">kg</span></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="simulation_item">Jumlah Item</label>
                            <div class="input-group"><input id="simulation_item" type="number" min="0" step="1" class="form-control" value="0" data-simulation-item><span class="input-group-text">item</span></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="simulation_large_type">Barang Besar</label>
                            <select id="simulation_large_type" class="form-control" data-simulation-large-type>
                                <option value="">Tidak ada</option>
                                <option value="baggage_bicycle_fee">Sepeda</option>
                                <option value="baggage_motorcycle_fee">Sepeda motor</option>
                                <option value="baggage_heavy_item_fee">Beko / barang berat</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="travs-simulation-result"><span>Estimasi tambahan</span><strong data-simulation-result>Rp0</strong></div>
                        </div>
                    </div>
                    <div class="form-text mt-2">Simulasi ini hanya bantuan tampilan admin. Perhitungan final tetap dilakukan backend.</div>
                </section>

                <div class="travs-form-actions mt-4 d-flex justify-content-end">
                    <button type="submit" class="travs-button btn btn-primary">Simpan Pengaturan Bagasi</button>
                </div>
                </div>
            </form>

            @endif

            <section id="settings-banner" class="travs-card travs-settings-group travs-settings-pane collapsible-section card" data-settings-pane="banner" data-collapsible-section @if ($activeTab !== 'banner') hidden @endif>
                <div class="travs-settings-group-header collapsible-section__header" data-collapse-header>
                    <div>
                        <span class="travs-settings-label">Banner Beranda</span>
                        <h3>Informasi Beranda Aplikasi</h3>
                        <p>Kelola gambar promo atau informasi yang tampil di beranda tamu dan penumpang.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-banner-body'])
                </div>

                <div id="settings-banner-body" class="collapsible-section__body" data-collapse-body>
                    <div class="row g-4 travs-banner-manager-row">
                        <div class="col-xl-4">
                            <form method="POST" action="{{ route('admin.banner.store') }}" enctype="multipart/form-data" class="travs-settings-subsection mt-0">
                                @csrf
                                <div class="travs-settings-subsection-head">
                                    <h4>Tambah Banner</h4>
                                    <p>Banner aktif akan dibaca API yang sama oleh aplikasi mobile.</p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="banner_judul">Judul</label>
                                    <input id="banner_judul" name="judul" class="form-control @error('judul') is-invalid @enderror" value="{{ old('judul') }}" maxlength="120" required>
                                    @include('admin.partials.field-error', ['name' => 'judul'])
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="banner_subjudul">Keterangan Singkat</label>
                                    <textarea id="banner_subjudul" name="subjudul" class="form-control @error('subjudul') is-invalid @enderror" rows="3" maxlength="255">{{ old('subjudul') }}</textarea>
                                    @include('admin.partials.field-error', ['name' => 'subjudul'])
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="banner_gambar">Gambar Banner</label>
                                    <input id="banner_gambar" name="gambar" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('gambar') is-invalid @enderror" data-banner-image-input required>
                                    <div class="form-text">JPG, JPEG, PNG, atau WEBP. Maksimal 3 MB.</div>
                                    @include('admin.partials.field-error', ['name' => 'gambar'])
                                    <img class="travs-banner-preview d-none" alt="Preview banner" data-banner-image-preview>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="banner_urutan">Urutan Tampil</label>
                                    <input id="banner_urutan" name="urutan" type="number" min="0" max="999" class="form-control @error('urutan') is-invalid @enderror" value="{{ old('urutan', 0) }}" required>
                                    @include('admin.partials.field-error', ['name' => 'urutan'])
                                </div>
                                <button class="travs-button w-100 btn btn-primary" type="submit">Simpan Banner</button>
                            </form>
                        </div>

                        <div class="col-xl-8">
                            <section class="travs-settings-subsection mt-0">
                                <div class="travs-settings-subsection-head">
                                    <h4>Daftar Banner</h4>
                                    <p>Urutan terkecil tampil lebih awal pada carousel mobile.</p>
                                </div>
                                <div class="row g-3 travs-banner-list-grid">
                                    @forelse ($banners as $banner)
                                        <div class="col-auto" style="width: 260px; max-width: 100%;">
                                            <article class="travs-banner-card" style="width: 260px; max-width: 100%; height: auto;">
                                                <img src="{{ asset('storage/'.$banner->gambar_path) }}" class="travs-banner-card-image travs-photo-preview-trigger" alt="{{ $banner->judul }}" data-preview-src="{{ asset('storage/'.$banner->gambar_path) }}" style="display: block; width: 232px; max-width: 100%; height: 131px; object-fit: cover; border-radius: 12px;">
                                                <div class="travs-banner-card-body">
                                                    <div>
                                                        <strong>{{ $banner->judul }}</strong>
                                                        <p>{{ $banner->subjudul ?: 'Tanpa keterangan' }}</p>
                                                    </div>
                                                    <span class="travs-status-pill travs-status-{{ $banner->aktif ? 'aktif' : 'nonaktif' }} badge rounded-pill">{{ $banner->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                                                </div>
                                                <form method="POST" action="{{ route('admin.banner.destroy', $banner) }}" onsubmit="return confirm('Hapus banner ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="travs-button-link travs-button-link-danger btn btn-link" type="submit">Hapus</button>
                                                </form>
                                            </article>
                                        </div>
                                    @empty
                                        <div class="col-12">
                                            <p class="travs-empty-inline">Belum ada banner beranda.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.partials.collapsible-section-script')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabButtons = document.querySelectorAll('[data-settings-tab]');
            const panes = document.querySelectorAll('[data-settings-pane]');

            tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const tabKey = button.dataset.settingsTab;
                    tabButtons.forEach((item) => {
                        const active = item.dataset.settingsTab === tabKey;
                        item.classList.toggle('is-active', active);
                        item.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    panes.forEach((pane) => pane.hidden = pane.dataset.settingsPane !== tabKey);
                    window.history.replaceState({}, '', `${window.location.pathname}?tab=${tabKey}`);
                });
            });

            // Field yang redup diaktifkan sesaat sebelum submit agar nilainya tetap diterima backend.
            // Hanya field data (input/select/textarea) yang diaktifkan kembali. Tombol submit
            // sengaja tidak disentuh agar penguncian anti klik-ganda dari app.js tetap berlaku.
            document.querySelectorAll('.travs-settings-pane').forEach((form) => {
                form.addEventListener('submit', () => form
                    .querySelectorAll('input:disabled, select:disabled, textarea:disabled')
                    .forEach((field) => field.disabled = false));
            });

            const logoInput = document.querySelector('#app_logo');
            let temporaryLogoUrl = null;
            logoInput?.addEventListener('change', () => {
                const wrapper = document.querySelector('[data-logo-preview-wrapper]');
                const image = document.querySelector('[data-logo-preview]');
                if (!wrapper || !image) return;
                const file = logoInput.files?.[0];
                if (!file) return;
                if (temporaryLogoUrl) URL.revokeObjectURL(temporaryLogoUrl);
                temporaryLogoUrl = URL.createObjectURL(file);
                wrapper.hidden = false;
                wrapper.dataset.previewSrc = temporaryLogoUrl;
                image.src = temporaryLogoUrl;
            });

            const bannerInput = document.querySelector('[data-banner-image-input]');
            const bannerPreview = document.querySelector('[data-banner-image-preview]');
            bannerInput?.addEventListener('change', () => {
                const file = bannerInput.files?.[0];
                if (!file || !bannerPreview) return;
                bannerPreview.src = URL.createObjectURL(file);
                bannerPreview.classList.remove('d-none');
            });
        });
    </script>
@endpush
