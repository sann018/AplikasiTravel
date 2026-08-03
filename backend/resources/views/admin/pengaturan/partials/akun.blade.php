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
                    {{-- Akun admin diletakkan di pusat pengaturan agar semua konfigurasi sistem berada dalam satu area. --}}
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
                                    <input id="current_password" name="current_password" type="password" class="travs-input @error('current_password') is-invalid @enderror form-control" required autocomplete="current-password" maxlength="72" placeholder="Masukkan password lama">
                                    <button type="button" class="travs-password-toggle" data-password-toggle data-password-target="current_password" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi" aria-pressed="false">
                                        <i data-feather="eye" data-password-icon aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="travs-field-meta">Diperlukan untuk mengonfirmasi perubahan email atau password.</div>
                                @include('admin.partials.field-error', ['name' => 'current_password'])
                            </div>
                        </div>
                        <div class="travs-admin-password-panel">
                            <div class="travs-admin-password-copy">
                                <h4>Ganti Password</h4>
                                <p>Gunakan minimal 6 karakter berisi huruf dan minimal satu angka atau simbol.</p>
                            </div>
                            <div class="travs-form-grid-2 d-grid">
                                <div class="travs-field">
                                    <label for="password" class="form-label">Password Baru</label>
                                    <div class="travs-password-control">
                                        <input id="password" name="password" type="password" class="travs-input @error('password') is-invalid @enderror form-control" autocomplete="new-password" minlength="6" maxlength="72" placeholder="Minimal 6 karakter">
                                        <button type="button" class="travs-password-toggle" data-password-toggle data-password-target="password" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi" aria-pressed="false">
                                            <i data-feather="eye" data-password-icon aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    @include('admin.partials.field-error', ['name' => 'password'])
                                </div>
                                <div class="travs-field">
                                    <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
                                    <div class="travs-password-control">
                                        <input id="password_confirmation" name="password_confirmation" type="password" class="travs-input form-control" autocomplete="new-password" minlength="6" maxlength="72" placeholder="Ulangi password baru">
                                        <button type="button" class="travs-password-toggle" data-password-toggle data-password-target="password_confirmation" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi" aria-pressed="false">
                                            <i data-feather="eye" data-password-icon aria-hidden="true"></i>
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
