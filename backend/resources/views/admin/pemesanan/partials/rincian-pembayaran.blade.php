        <section class="travs-section-card card">
            <div class="travs-panel-header d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="travs-panel-title">
                    <h3>Rincian Pembayaran</h3>
                    <p>Snapshot nominal dan waktu yang tersimpan pada transaksi ini.</p>
                </div>
            </div>

            <dl class="travs-detail-data-list travs-detail-payment-list">
                <div>
                    <dt>Metode Pembayaran</dt>
                    <dd>{{ $paymentMethodLabel }}</dd>
                </div>
                <div>
                    <dt>Status Pembayaran</dt>
                    <dd>
                        <span class="travs-status-pill travs-status-{{ $pembayaran?->status_bayar ?? 'draft' }} badge rounded-pill">
                            {{ $paymentStatusLabel }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt>Nominal Transaksi</dt>
                    <dd class="travs-detail-value-end">{{ $pembayaran?->nominal_transaksi === null ? '—' : $formatRupiah($pembayaran->nominal_transaksi) }}</dd>
                </div>
                <div>
                    <dt>Biaya Admin Provider</dt>
                    <dd class="travs-detail-value-end">{{ $pembayaran?->biaya_admin_provider === null ? '—' : $formatRupiah($pembayaran->biaya_admin_provider) }}</dd>
                </div>
                <div class="is-total">
                    <dt>Total Dibayar Penumpang</dt>
                    <dd class="travs-detail-value-end">{{ $pembayaran?->total_dibayar_penumpang === null ? '—' : $formatRupiah($pembayaran->total_dibayar_penumpang) }}</dd>
                </div>
                <div>
                    <dt>Batas Pembayaran</dt>
                    <dd>{{ $paymentDeadline?->translatedFormat('d F Y, H:i') ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Dibayar Pada</dt>
                    <dd>{{ $pembayaran?->dibayar_pada?->translatedFormat('d F Y, H:i') ?: '—' }}</dd>
                </div>
            </dl>

            @if ($cashVerificationReady)
                <div class="travs-subsection">
                    <strong>Konfirmasi Pembayaran Tunai</strong>
                    <p class="travs-empty-inline">Gunakan aksi ini setelah pembayaran tunai benar-benar diterima.</p>
                    <form method="POST" action="{{ route('admin.pembayaran.confirm-cash', $pemesanan) }}">
                        @csrf
                        <button type="submit" class="travs-button btn btn-primary">Konfirmasi Pembayaran Tunai</button>
                    </form>
                </div>
            @endif
        </section>
