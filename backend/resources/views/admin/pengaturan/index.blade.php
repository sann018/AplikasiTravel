@extends('admin.layouts.app')

@section('title', 'Pengaturan Sistem')
@section('page-title', 'Pengaturan Sistem')
@section('page-description', 'Atur identitas aplikasi, logika booking, pembayaran, bagasi, dan template notifikasi dari satu pusat konfigurasi.')

@section('content')
    <form method="POST" action="{{ route('admin.pengaturan.update') }}" class="travs-settings-grid">
        @csrf
        @method('PUT')
        <input type="hidden" name="active_tab" id="active_tab" value="{{ $activeTab }}">

        <div class="travs-settings-tabs">
            <div class="travs-settings-tabs-copy">
                <span class="travs-eyebrow">Control Center</span>
                <h3>Pilih area konfigurasi yang ingin diatur</h3>
                <p>Setiap tab mengelompokkan pengaturan inti agar admin bisa fokus tanpa harus scroll terlalu panjang.</p>
            </div>

            <div class="travs-settings-tab-list" role="tablist" aria-label="Tab pengaturan sistem">
                @foreach ($groupLabels as $groupKey => $groupLabel)
                    <button
                        type="button"
                        class="travs-settings-tab {{ $activeTab === $groupKey ? 'is-active' : '' }}"
                        data-settings-tab="{{ $groupKey }}"
                        role="tab"
                        aria-selected="{{ $activeTab === $groupKey ? 'true' : 'false' }}"
                    >
                        {{ $groupLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        @foreach ($groupLabels as $groupKey => $groupLabel)
            <section
                class="travs-card travs-settings-group travs-settings-pane"
                id="group-{{ $groupKey }}"
                data-settings-pane="{{ $groupKey }}"
                @if ($activeTab !== $groupKey) hidden @endif
            >
                <div class="travs-settings-group-header">
                    <span class="travs-eyebrow">{{ $groupLabel }}</span>
                    <h3>{{ $groupLabel }}</h3>
                    <p>
                        @switch($groupKey)
                            @case('umum')
                                Identitas dan informasi kontak utama yang tampil di seluruh ekosistem RuslyTravel.
                                @break
                            @case('pemesanan')
                                Aturan kursi, batas waktu seat lock, dan kontrol auto cancel booking.
                                @break
                            @case('pembayaran')
                                Aktivasi metode bayar dan informasi rekening resmi travel.
                                @break
                            @case('bagasi')
                                Kontrol perhitungan bagasi berdasarkan berat, ukuran, dan jumlah item.
                                @break
                            @case('notifikasi')
                                Trigger serta template pesan otomatis untuk penumpang.
                                @break
                        @endswitch
                    </p>
                </div>

                <div class="travs-settings-fields">
                    @foreach (($settings[$groupKey] ?? collect()) as $setting)
                        @php
                            $castValue = old('settings.'.$setting->kunci, $setting->castNilai());
                            $isChecked = filter_var($castValue, FILTER_VALIDATE_BOOL);
                        @endphp

                        <div class="travs-field">
                            <label for="{{ $setting->kunci }}">{{ ucwords(str_replace('_', ' ', $setting->kunci)) }}</label>

                            @if ($setting->tipe_data === 'boolean')
                                <input type="hidden" name="settings[{{ $setting->kunci }}]" value="0">
                                <label class="travs-checkbox">
                                    <input
                                        id="{{ $setting->kunci }}"
                                        type="checkbox"
                                        name="settings[{{ $setting->kunci }}]"
                                        value="1"
                                        {{ $isChecked ? 'checked' : '' }}
                                    >
                                    <span>{{ $isChecked ? 'Saat ini aktif' : 'Saat ini nonaktif' }}</span>
                                </label>
                            @elseif ($setting->tipe_data === 'json')
                                <textarea
                                    id="{{ $setting->kunci }}"
                                    name="settings[{{ $setting->kunci }}]"
                                    class="travs-textarea"
                                >{{ is_array($castValue) ? json_encode($castValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $castValue }}</textarea>
                            @elseif (str_contains($setting->kunci, 'template') || str_contains($setting->kunci, 'note') || str_contains($setting->kunci, 'alamat'))
                                <textarea
                                    id="{{ $setting->kunci }}"
                                    name="settings[{{ $setting->kunci }}]"
                                    class="travs-textarea"
                                >{{ $castValue }}</textarea>
                            @else
                                <input
                                    id="{{ $setting->kunci }}"
                                    name="settings[{{ $setting->kunci }}]"
                                    type="{{ in_array($setting->tipe_data, ['integer', 'decimal'], true) ? 'number' : 'text' }}"
                                    step="{{ $setting->tipe_data === 'decimal' ? '0.01' : '1' }}"
                                    class="travs-input"
                                    value="{{ $castValue }}"
                                >
                            @endif

                            <div class="travs-field-meta">
                                {{ $setting->deskripsi }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="travs-form-actions">
            <button type="submit" class="travs-button">Simpan Semua Pengaturan</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabButtons = document.querySelectorAll('[data-settings-tab]');
            const panes = document.querySelectorAll('[data-settings-pane]');
            const hiddenInput = document.getElementById('active_tab');

            const activateTab = (tabKey) => {
                tabButtons.forEach((button) => {
                    const isActive = button.dataset.settingsTab === tabKey;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                panes.forEach((pane) => {
                    pane.hidden = pane.dataset.settingsPane !== tabKey;
                });

                if (hiddenInput) {
                    hiddenInput.value = tabKey;
                }
            };

            tabButtons.forEach((button) => {
                button.addEventListener('click', () => activateTab(button.dataset.settingsTab));
            });
        });
    </script>
@endpush
