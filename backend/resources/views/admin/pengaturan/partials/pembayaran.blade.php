            <form id="settings-payment" method="POST" action="{{ route('admin.settings.payment.update') }}" class="travs-card travs-settings-group travs-settings-pane collapsible-section card" data-settings-pane="payment" data-collapsible-section @if ($activeTab !== 'payment') hidden @endif>
                @csrf
                <input type="hidden" name="payment_transfer_enabled" value="{{ $settingEnabled('payment_transfer_enabled', true) ? '1' : '0' }}">
                <input type="hidden" name="payment_qris_enabled" value="{{ $settingEnabled('payment_qris_enabled', true) ? '1' : '0' }}">
                <div class="travs-settings-group-header collapsible-section__header" data-collapse-header>
                    <div>
                        <span class="travs-settings-label">Pembayaran</span>
                        <h3>Instruksi Pembayaran Tunai</h3>
                        <p>Kelola petunjuk pembayaran tunai yang ditampilkan kepada penumpang.</p>
                    </div>
                    @include('admin.partials.collapsible-toggle', ['targetId' => 'settings-payment-body'])
                </div>

                <div id="settings-payment-body" class="collapsible-section__body" data-collapse-body>
                {{-- Toggle tunai dan rekening transfer lama tidak diedit lagi dari admin agar sumber pembayaran tetap mengikuti Pakasir. --}}
                <div>
                    <label class="form-label" for="payment_cash_note">Instruksi Pembayaran Tunai</label>
                    <textarea id="payment_cash_note" name="payment_cash_note" rows="3" maxlength="1000" class="form-control @error('payment_cash_note') is-invalid @enderror" data-cash-note>{{ $settingValue('payment_cash_note') }}</textarea>
                    <div class="form-text">Petunjuk yang dibaca penumpang ketika memilih pembayaran tunai.</div>
                    @include('admin.partials.field-error', ['name' => 'payment_cash_note'])
                </div>
                <div class="travs-form-actions mt-4 d-flex justify-content-end">
                    <button type="submit" class="travs-button btn btn-primary">Simpan Pengaturan Pembayaran</button>
                </div>
                </div>
            </form>
