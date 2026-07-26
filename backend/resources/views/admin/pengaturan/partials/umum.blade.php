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
                            <span>Pratinjau Logo</span>
                            <img src="{{ $logoPreviewUrl ?? '' }}" alt="Pratinjau logo aplikasi" data-logo-preview>
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
