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
