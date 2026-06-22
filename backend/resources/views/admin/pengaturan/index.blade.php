@extends('admin.layouts.app')

@section('title', 'Pengaturan Sistem')
@section('page-title', 'Pengaturan Sistem')
@section('page-description', 'Kelola aturan utama aplikasi dari satu halaman.')

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
        'general' => 'Umum',
        'booking' => 'Pemesanan',
        'payment' => 'Pembayaran',
        'baggage' => 'Bagasi',
    ];
@endphp

@section('content')
    <div class="travs-settings-page">
        <section id="akun-admin" class="travs-card travs-admin-account-card mb-4">
            <div class="travs-admin-account-head">
                <div>
                    <span class="travs-eyebrow">Akun Admin</span>
                    <h3>Email dan Password</h3>
                    <p>Perbarui email login atau ganti password dashboard admin.</p>
                </div>
                <div class="travs-admin-account-badge">
                    <span>Akun aktif</span>
                    <strong>{{ $adminUser->email }}</strong>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.pengaturan.account.update') }}" class="travs-admin-account-form">
                @csrf
                @method('PUT')
                <div class="travs-admin-account-primary">
                    <div class="travs-field">
                        <label for="admin_email">Email Admin</label>
                        <input id="admin_email" name="email" type="email" class="travs-input @error('email') is-invalid @enderror" value="{{ old('email', $adminUser->email) }}" required autocomplete="email">
                        @include('admin.partials.field-error', ['name' => 'email'])
                    </div>
                    <div class="travs-field">
                        <label for="current_password">Password Lama</label>
                        <input id="current_password" name="current_password" type="password" class="travs-input @error('current_password') is-invalid @enderror" required autocomplete="current-password" placeholder="Masukkan password lama">
                        @include('admin.partials.field-error', ['name' => 'current_password'])
                    </div>
                </div>
                <div class="travs-admin-password-panel">
                    <div class="travs-admin-password-copy">
                        <h4>Ganti Password</h4>
                        <p>Isi dua kolom ini hanya jika ingin mengganti password.</p>
                    </div>
                    <div class="travs-form-grid-2">
                        <div class="travs-field">
                            <label for="password">Password Baru</label>
                            <input id="password" name="password" type="password" class="travs-input @error('password') is-invalid @enderror" autocomplete="new-password" placeholder="Minimal 8 karakter">
                            @include('admin.partials.field-error', ['name' => 'password'])
                        </div>
                        <div class="travs-field">
                            <label for="password_confirmation">Konfirmasi Password Baru</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="travs-input" autocomplete="new-password" placeholder="Ulangi password baru">
                        </div>
                    </div>
                </div>
                <div class="travs-form-actions travs-admin-account-actions">
                    <button type="submit" class="travs-button">Simpan Perubahan Akun</button>
                </div>
            </form>
        </section>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Pengaturan belum dapat disimpan.</strong>
                <div>Periksa kembali kolom yang diberi tanda merah.</div>
            </div>
        @endif

        <section class="travs-settings-tabs mb-3">
            <div class="travs-settings-tabs-copy">
                <span class="travs-eyebrow">Control Center</span>
                <h3>Pilih bagian yang ingin diatur</h3>
                <p>Setiap tab disimpan secara terpisah agar perubahan tidak mengenai bagian lain.</p>
            </div>

            {{-- Tombol simpan dipisah per tab supaya admin tidak mengubah semua konfigurasi tanpa sengaja. --}}
            <div class="travs-settings-tab-list" role="tablist" aria-label="Tab pengaturan sistem">
                @foreach ($tabLabels as $tabKey => $tabLabel)
                    <button type="button" class="travs-settings-tab {{ $activeTab === $tabKey ? 'is-active' : '' }}" data-settings-tab="{{ $tabKey }}" role="tab" aria-controls="settings-{{ $tabKey }}" aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}">
                        {{ $tabLabel }}
                    </button>
                @endforeach
            </div>
        </section>

        <div class="travs-settings-grid">
            <form id="settings-general" method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data" class="travs-card travs-settings-group travs-settings-pane" data-settings-pane="general" @if ($activeTab !== 'general') hidden @endif>
                @csrf
                <div class="travs-settings-group-header">
                    <span class="travs-eyebrow">Umum</span>
                    <h3>Identitas dan Kontak Aplikasi</h3>
                    <p>Informasi utama yang digunakan pada web admin dan aplikasi mobile.</p>
                </div>

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
                        <div class="travs-settings-logo-preview" data-logo-preview-wrapper @if (! $logoPreviewUrl) hidden @endif>
                            <span>Preview logo</span>
                            <img src="{{ $logoPreviewUrl ?? '' }}" alt="Preview logo aplikasi" data-logo-preview>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin_contact">Kontak Admin</label>
                        <input id="admin_contact" name="admin_contact" type="tel" class="form-control @error('admin_contact') is-invalid @enderror" value="{{ $settingValue('admin_contact') }}" required>
                        <div class="form-text">Nomor yang dapat dihubungi untuk kebutuhan operasional.</div>
                        @include('admin.partials.field-error', ['name' => 'admin_contact'])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="service_whatsapp">WhatsApp Layanan</label>
                        <input id="service_whatsapp" name="service_whatsapp" type="tel" class="form-control @error('service_whatsapp') is-invalid @enderror" value="{{ $settingValue('service_whatsapp') }}" required>
                        <div class="form-text">Nomor WhatsApp layanan pelanggan.</div>
                        @include('admin.partials.field-error', ['name' => 'service_whatsapp'])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="system_email">Email Sistem</label>
                        <input id="system_email" name="system_email" type="email" class="form-control @error('system_email') is-invalid @enderror" value="{{ $settingValue('system_email') }}" required>
                        <div class="form-text">Email resmi untuk komunikasi sistem.</div>
                        @include('admin.partials.field-error', ['name' => 'system_email'])
                    </div>
                </div>

                <div class="travs-form-actions mt-4">
                    <button type="submit" class="travs-button">Simpan Pengaturan Umum</button>
                </div>
            </form>

            <form id="settings-booking" method="POST" action="{{ route('admin.settings.booking.update') }}" class="travs-card travs-settings-group travs-settings-pane" data-settings-pane="booking" @if ($activeTab !== 'booking') hidden @endif>
                @csrf
                <div class="travs-settings-group-header">
                    <span class="travs-eyebrow">Pemesanan</span>
                    <h3>Aturan Kursi dan Waktu Pemesanan</h3>
                    <p>Atur jumlah kursi dan lama penguncian selama proses pemesanan.</p>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        {{-- Switch dipakai karena pengaturan ini hanya memiliki kondisi aktif atau nonaktif. --}}
                        <div class="travs-settings-switch-row">
                            <div>
                                <strong>Auto Cancel Booking</strong>
                                <p>Pemesanan dibatalkan otomatis setelah batas waktunya habis.</p>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="auto_cancel_booking" value="0">
                                <input id="auto_cancel_booking" class="form-check-input" type="checkbox" name="auto_cancel_booking" value="1" {{ $settingEnabled('auto_cancel_booking') ? 'checked' : '' }}>
                                <label class="form-check-label" for="auto_cancel_booking">Aktif</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="max_seat_per_booking">Maksimal Kursi per Booking</label>
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
                <div class="travs-form-actions mt-4">
                    <button type="submit" class="travs-button">Simpan Pengaturan Pemesanan</button>
                </div>
            </form>

            <form id="settings-payment" method="POST" action="{{ route('admin.settings.payment.update') }}" class="travs-card travs-settings-group travs-settings-pane" data-settings-pane="payment" @if ($activeTab !== 'payment') hidden @endif>
                @csrf
                <input type="hidden" name="payment_transfer_enabled" value="{{ $settingEnabled('payment_transfer_enabled', true) ? '1' : '0' }}">
                <input type="hidden" name="payment_qris_enabled" value="{{ $settingEnabled('payment_qris_enabled', true) ? '1' : '0' }}">
                <div class="travs-settings-group-header">
                    <span class="travs-eyebrow">Pembayaran</span>
                    <h3>Metode dan Informasi Pembayaran</h3>
                    <p>Kelola rekening transfer dan pilihan pembayaran tunai. Biaya kanal mengikuti Pakasir.</p>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <div class="travs-settings-switch-row h-100">
                            <div>
                                <strong>Pembayaran Tunai</strong>
                                <p>Metode tunai hanya muncul di mobile saat status ini aktif.</p>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input type="hidden" name="payment_cash_enabled" value="0">
                                <input id="payment_cash_enabled" class="form-check-input" type="checkbox" name="payment_cash_enabled" value="1" {{ $settingEnabled('payment_cash_enabled') ? 'checked' : '' }} data-cash-toggle>
                                <label class="form-check-label" for="payment_cash_enabled">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="travs-settings-subsection mt-4">
                    <div class="travs-settings-subsection-head">
                        <h4>Rekening Transfer</h4>
                        <p>Informasi rekening yang ditampilkan sebagai tujuan pembayaran transfer.</p>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="bank_name">Nama Bank</label>
                            <input id="bank_name" name="bank_name" type="text" maxlength="100" class="form-control @error('bank_name') is-invalid @enderror" value="{{ $settingValue('bank_name') }}">
                            @include('admin.partials.field-error', ['name' => 'bank_name'])
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="bank_account_number">Nomor Rekening</label>
                            <input id="bank_account_number" name="bank_account_number" type="text" inputmode="numeric" maxlength="20" class="form-control @error('bank_account_number') is-invalid @enderror" value="{{ $settingValue('bank_account_number') }}">
                            @include('admin.partials.field-error', ['name' => 'bank_account_number'])
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="bank_account_name">Nama Pemilik Rekening</label>
                            <input id="bank_account_name" name="bank_account_name" type="text" maxlength="100" class="form-control @error('bank_account_name') is-invalid @enderror" value="{{ $settingValue('bank_account_name') }}">
                            @include('admin.partials.field-error', ['name' => 'bank_account_name'])
                        </div>
                    </div>
                </section>

                <div class="mt-3">
                    <label class="form-label" for="payment_cash_note">Catatan Pembayaran Tunai</label>
                    <textarea id="payment_cash_note" name="payment_cash_note" rows="3" maxlength="1000" class="form-control @error('payment_cash_note') is-invalid @enderror" data-cash-note>{{ $settingValue('payment_cash_note') }}</textarea>
                    <div class="form-text">Petunjuk yang dibaca penumpang ketika memilih pembayaran tunai.</div>
                    @include('admin.partials.field-error', ['name' => 'payment_cash_note'])
                </div>
                <div class="travs-form-actions mt-4">
                    <button type="submit" class="travs-button">Simpan Pengaturan Pembayaran</button>
                </div>
            </form>

            <form id="settings-baggage" method="POST" action="{{ route('admin.settings.baggage.update') }}" class="travs-card travs-settings-group travs-settings-pane" data-settings-pane="baggage" @if ($activeTab !== 'baggage') hidden @endif>
                @csrf
                <div class="travs-settings-group-header">
                    <span class="travs-eyebrow">Bagasi</span>
                    <h3>Aturan Barang Bawaan</h3>
                    <p>Atur metode hitung, batas gratis, biaya tambahan, dan kapasitas kendaraan.</p>
                </div>

                <section class="travs-settings-subsection">
                    <div class="travs-settings-subsection-head">
                        <h4>Status Perhitungan Bagasi</h4>
                        <p>Switch memudahkan admin mengaktifkan aturan yang digunakan.</p>
                    </div>
                    <div class="row g-3">
                        @foreach ([
                            'baggage_weight_enabled' => ['Perhitungan Berat', 'Aturan kilogram'],
                            'baggage_size_enabled' => ['Perhitungan Ukuran', 'Aturan sentimeter'],
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

                <section class="travs-settings-subsection" data-rule-section="size">
                    <div class="travs-settings-subsection-head"><h4>Aturan Ukuran</h4></div>
                    <div class="row g-3">
                        @foreach ([
                            'baggage_free_size_cm' => ['Ukuran Gratis', false],
                            'baggage_max_size_per_person_cm' => ['Ukuran Maksimal per Orang', false],
                            'baggage_extra_fee_per_cm' => ['Biaya Tambahan per Cm', true],
                        ] as $key => [$label, $currency])
                            <div class="col-md-4">
                                <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                                <div class="input-group">
                                    @if ($currency)<span class="input-group-text">Rp</span>@endif
                                    <input id="{{ $key }}" name="{{ $key }}" type="number" min="0" step="0.01" class="form-control @error($key) is-invalid @enderror" value="{{ $settingValue($key) }}" data-rule-input="size" required>
                                    @if (! $currency)<span class="input-group-text">cm</span>@endif
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
                            <label class="form-label" for="simulation_size">Ukuran Bagasi</label>
                            <div class="input-group"><input id="simulation_size" type="number" min="0" step="0.1" class="form-control" value="0" data-simulation-size><span class="input-group-text">cm</span></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="simulation_item">Jumlah Item</label>
                            <div class="input-group"><input id="simulation_item" type="number" min="0" step="1" class="form-control" value="0" data-simulation-item><span class="input-group-text">item</span></div>
                        </div>
                        <div class="col-md-3">
                            <div class="travs-simulation-result"><span>Estimasi tambahan</span><strong data-simulation-result>Rp0</strong></div>
                        </div>
                    </div>
                    <div class="form-text mt-2">Simulasi ini hanya bantuan tampilan admin. Perhitungan final tetap dilakukan backend.</div>
                </section>

                <div class="travs-form-actions mt-4">
                    <button type="submit" class="travs-button">Simpan Pengaturan Bagasi</button>
                </div>
            </form>

        </div>
    </div>
@endsection

@push('scripts')
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

            const cashToggle = document.querySelector('[data-cash-toggle]');
            const cashNote = document.querySelector('[data-cash-note]');
            const syncCashNote = () => {
                if (cashNote && cashToggle) cashNote.disabled = !cashToggle.checked;
            };
            cashToggle?.addEventListener('change', syncCashNote);
            syncCashNote();

            const syncRuleSection = (toggle) => {
                const rule = toggle.dataset.ruleToggle;
                const section = document.querySelector(`[data-rule-section="${rule}"]`);
                section?.classList.toggle('is-disabled', !toggle.checked);
                section?.querySelectorAll(`[data-rule-input="${rule}"]`).forEach((input) => input.disabled = !toggle.checked);
            };
            document.querySelectorAll('[data-rule-toggle]').forEach((toggle) => {
                toggle.addEventListener('change', () => syncRuleSection(toggle));
                syncRuleSection(toggle);
            });

            // Field yang redup diaktifkan sesaat sebelum submit agar nilainya tetap diterima backend.
            document.querySelectorAll('.travs-settings-pane').forEach((form) => {
                form.addEventListener('submit', () => form.querySelectorAll(':disabled').forEach((field) => field.disabled = false));
            });

            const numberValue = (selector) => Number.parseFloat(document.querySelector(selector)?.value || '0') || 0;
            const calculateBaggage = () => {
                const weightCost = document.querySelector('#baggage_weight_enabled')?.checked
                    ? Math.max(0, numberValue('[data-simulation-weight]') - numberValue('#baggage_free_weight_kg')) * numberValue('#baggage_extra_fee_per_kg')
                    : 0;
                const sizeCost = document.querySelector('#baggage_size_enabled')?.checked
                    ? Math.max(0, numberValue('[data-simulation-size]') - numberValue('#baggage_free_size_cm')) * numberValue('#baggage_extra_fee_per_cm')
                    : 0;
                const itemCost = document.querySelector('#baggage_item_enabled')?.checked
                    ? Math.max(0, numberValue('[data-simulation-item]') - numberValue('#baggage_free_item_count')) * numberValue('#baggage_extra_fee_per_item')
                    : 0;
                const total = weightCost + sizeCost + itemCost;
                const output = document.querySelector('[data-simulation-result]');
                if (output) output.textContent = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(total);
            };
            // Simulasi ini hanya untuk tampilan admin; perhitungan final tetap dilakukan backend.
            document.querySelectorAll('[data-baggage-simulator] input').forEach((input) => input.addEventListener('input', calculateBaggage));
            document.querySelectorAll('[data-rule-toggle]').forEach((input) => input.addEventListener('change', calculateBaggage));
            calculateBaggage();

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
                image.src = temporaryLogoUrl;
            });
        });
    </script>
@endpush
