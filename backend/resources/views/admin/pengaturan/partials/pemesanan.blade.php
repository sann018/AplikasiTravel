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
                        <label class="form-label" for="seat_lock_form_minutes">Durasi Pengisian Form</label>
                        <div class="input-group">
                            <input id="seat_lock_form_minutes" name="seat_lock_form_minutes" type="number" min="1" class="form-control @error('seat_lock_form_minutes') is-invalid @enderror" value="{{ $settingValue('seat_lock_form_minutes') }}" required>
                            <span class="input-group-text">menit</span>
                        </div>
                        <div class="form-text">Durasi kursi dikunci saat penumpang mengisi form.</div>
                        @include('admin.partials.field-error', ['name' => 'seat_lock_form_minutes'])
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="seat_lock_payment_minutes">Batas Waktu Pembayaran</label>
                        <div class="input-group">
                            <input id="seat_lock_payment_minutes" name="seat_lock_payment_minutes" type="number" min="1" class="form-control @error('seat_lock_payment_minutes') is-invalid @enderror" value="{{ $settingValue('seat_lock_payment_minutes') }}" required>
                            <span class="input-group-text">menit</span>
                        </div>
                        <div class="form-text">Durasi kursi dikunci saat menunggu pembayaran.</div>
                        @include('admin.partials.field-error', ['name' => 'seat_lock_payment_minutes'])
                    </div>
                </div>

                <div class="alert alert-info mt-3 mb-0" role="alert">
                    Perubahan hanya berlaku untuk pemesanan baru dan tidak mengubah pemesanan yang sedang berjalan.
                    Timer pada aplikasi mobile membantu pengguna melihat sisa waktu. Keputusan akhir masa berlaku tetap diperiksa backend.
                </div>
                <div class="travs-form-actions mt-4 d-flex justify-content-end">
                    <button type="submit" class="travs-button btn btn-primary">Simpan Pengaturan Pemesanan</button>
                </div>
                </div>
            </form>
