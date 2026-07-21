@extends('admin.layouts.app')

@section('title', 'Edit Jadwal')
@section('page-title', 'Edit Jadwal Perjalanan')
@section('page-description', 'Perbarui data jadwal perjalanan untuk menjaga informasi tetap akurat.')

@section('content')
    <div class="travs-page-frame card">
        <div class="travs-panel-header d-flex align-items-start justify-content-between">
            <div class="travs-panel-title">
                <h3>Form Edit Jadwal</h3>
                <p>Perbarui data jadwal perjalanan.</p>
            </div>
            <a href="{{ route('admin.jadwal.index') }}" class="travs-button-secondary btn btn-secondary">Kembali</a>
        </div>

        @if ($errors->any())
            <div class="travs-alert travs-alert-danger alert alert-danger">
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.jadwal.update', $jadwal) }}" class="travs-form-grid d-grid">
            @csrf
            @method('PUT')

            <div class="travs-form-grid-2 d-grid">
                <div class="travs-field">
                    <label for="rute_id" class="form-label">Rute Perjalanan</label>
                    <select id="rute_id" name="rute_id" class="travs-select @error('rute_id') is-invalid @enderror form-select" required>
                        <option value="">Pilih rute</option>
                        @foreach ($ruteOptions as $rute)
                            <option
                                value="{{ $rute->id }}"
                                data-duration="{{ (int) $rute->estimasi_durasi_menit }}"
                                @selected(old('rute_id', $jadwal->rute_id) == $rute->id)
                            >
                                {{ $rute->kota_asal }} - {{ $rute->kota_tujuan }} (Rp{{ number_format($rute->harga_tiket, 0, ',', '.') }})
                            </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'rute_id'])
                </div>
                <div class="travs-field">
                    <label for="kendaraan_id" class="form-label">Kendaraan</label>
                    <select id="kendaraan_id" name="kendaraan_id" class="travs-select @error('kendaraan_id') is-invalid @enderror form-select" required>
                        <option value="">Pilih kendaraan</option>
                        @foreach ($kendaraanOptions as $kendaraan)
                            <option
                                value="{{ $kendaraan->id }}"
                                data-capacity="{{ (int) $kendaraan->kapasitas_kursi }}"
                                data-layout='@json($kendaraan->getSeatLayoutConfig())'
                                @selected(old('kendaraan_id', $jadwal->kendaraan_id) == $kendaraan->id)
                            >
                                {{ $kendaraan->label_armada }} - {{ $kendaraan->kapasitas_kursi }} kursi
                            </option>
                        @endforeach
                    </select>
                    @include('admin.partials.field-error', ['name' => 'kendaraan_id'])
                </div>
                <div class="travs-field travs-field-span-2">
                    <label class="form-label">Kursi yang Ditutup</label>
                    <div class="travs-field-meta">Pengaturan ini khusus untuk jadwal ini. Kursi yang ditutup tampil abu-abu di aplikasi dan tidak bisa dipilih penumpang.</div>
                    <div id="closed-seat-options" class="travs-seat-toggle-grid" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:12px;"></div>
                    <div id="closed-seat-empty" class="travs-field-meta" style="margin-top:10px;">Pilih kendaraan terlebih dahulu untuk menampilkan nomor kursi.</div>
                    @include('admin.partials.field-error', ['name' => 'kursi_ditutup'])
                </div>
                <div class="travs-field">
                    <label for="pengemudi_id" class="form-label">Pengemudi</label>
                    <select id="pengemudi_id" name="pengemudi_id" class="travs-select @error('pengemudi_id') is-invalid @enderror form-select" required>
                        <option value="">Pilih pengemudi</option>
                        @foreach ($pengemudiOptions as $pengemudi)
                            @php
                                $availability = $pengemudiAvailability[$pengemudi->id] ?? ['locked' => false, 'reason' => ''];
                                $isDriverLocked = (bool) $availability['locked'];
                            @endphp
                            <option
                                value="{{ $pengemudi->id }}"
                                @selected(old('pengemudi_id', $jadwal->pengemudi_id) == $pengemudi->id)
                                @disabled($isDriverLocked)
                            >
                                {{ $pengemudi->pengguna->nama }} (SIM {{ $pengemudi->nomor_sim }}){{ $isDriverLocked ? ' - Terkunci' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <small style="color:#64748b;">Pengemudi terkunci jika masih punya jadwal aktif pada tanggal yang sama.</small>
                    @include('admin.partials.field-error', ['name' => 'pengemudi_id'])
                </div>
                <div class="travs-field">
                    <label for="status" class="form-label">Status Jadwal</label>
                    @php($statusSelesaiTerkunci = $jadwal->status === 'selesai')
                    <select id="status" name="status" class="travs-select @error('status') is-invalid @enderror form-select" required @disabled($statusSelesaiTerkunci)>
                        @foreach (['draft' => 'Draft', 'dibuka' => 'Dibuka', 'ditutup' => 'Ditutup', 'berangkat' => 'Berangkat', 'selesai' => 'Selesai'] as $value => $label)
                            <option value="{{ $value }}" @selected($statusSelesaiTerkunci ? $value === 'selesai' : old('status', $jadwal->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($statusSelesaiTerkunci)
                        <input type="hidden" name="status" value="selesai">
                        <small style="color:#64748b;">Jadwal telah selesai sehingga statusnya tidak dapat diubah kembali.</small>
                    @endif
                    @include('admin.partials.field-error', ['name' => 'status'])
                </div>
                <div class="travs-field">
                    <label for="waktu_berangkat" class="form-label">Waktu Berangkat</label>
                    <input id="waktu_berangkat" name="waktu_berangkat" type="hidden" value="{{ old('waktu_berangkat', $jadwal->waktu_berangkat?->format('Y-m-d H:i')) }}">
                    <div class="travs-form-grid-2 d-grid" style="gap: 12px;">
                        <input id="tanggal_berangkat_display" type="date" class="travs-input @error('waktu_berangkat') is-invalid @enderror form-control" required>
                        <input id="jam_berangkat_display" type="text" class="travs-input @error('waktu_berangkat') is-invalid @enderror form-control" inputmode="numeric" maxlength="5" placeholder="17:00" required>
                    </div>
                    <small style="color:#64748b;">Gunakan format jam 24 jam, contoh 17:00 atau 01:30.</small>
                    @include('admin.partials.field-error', ['name' => 'waktu_berangkat'])
                </div>
                <div class="travs-field">
                    <label for="estimasi_tiba" class="form-label">Estimasi Tiba</label>
                    <input id="estimasi_tiba" name="estimasi_tiba" type="hidden" value="{{ old('estimasi_tiba', $jadwal->estimasi_tiba?->format('Y-m-d H:i')) }}">
                    <input id="estimasi_tiba_display" type="text" class="travs-input @error('estimasi_tiba') is-invalid @enderror form-control" value="" readonly placeholder="Akan dihitung otomatis">
                    <small style="color:#64748b;">Estimasi dihitung otomatis dari waktu berangkat dan durasi rute. Backend tetap menghitung ulang saat disimpan.</small>
                    @include('admin.partials.field-error', ['name' => 'estimasi_tiba'])
                </div>
            </div>

            <div class="travs-form-actions travs-inline d-flex justify-content-end align-items-center flex-wrap">
                <a href="{{ route('admin.jadwal.index') }}" class="travs-button-secondary btn btn-secondary">Batal</a>
                <button type="submit" class="travs-button btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const routeSelect = document.getElementById('rute_id');
            const vehicleSelect = document.getElementById('kendaraan_id');
            const closedSeatOptions = document.getElementById('closed-seat-options');
            const closedSeatEmpty = document.getElementById('closed-seat-empty');
            const departureInput = document.getElementById('waktu_berangkat');
            const departureDateInput = document.getElementById('tanggal_berangkat_display');
            const departureTimeInput = document.getElementById('jam_berangkat_display');
            const arrivalInput = document.getElementById('estimasi_tiba');
            const arrivalDisplayInput = document.getElementById('estimasi_tiba_display');

            if (!routeSelect || !vehicleSelect || !closedSeatOptions || !closedSeatEmpty || !departureInput || !departureDateInput || !departureTimeInput || !arrivalInput || !arrivalDisplayInput) {
                return;
            }

            const selectedClosedSeats = new Set(@json($closedSeatNumbers ?? []));
            const pad = (value) => String(value).padStart(2, '0');

            const renderClosedSeatOptions = () => {
                closedSeatOptions.innerHTML = '';
                const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
                const capacity = Number(selectedOption?.dataset?.capacity || 0);

                if (!capacity) {
                    closedSeatEmpty.style.display = 'block';
                    return;
                }

                closedSeatEmpty.style.display = 'none';

                for (let seatNumber = 1; seatNumber <= capacity; seatNumber += 1) {
                    const id = `kursi_ditutup_${seatNumber}`;
                    const label = document.createElement('label');
                    label.setAttribute('for', id);
                    label.style.display = 'inline-flex';
                    label.style.alignItems = 'center';
                    label.style.gap = '8px';
                    label.style.padding = '10px 14px';
                    label.style.border = '1px solid #d8e1ef';
                    label.style.borderRadius = '14px';
                    label.style.background = '#f8fafc';
                    label.style.fontWeight = '700';
                    label.style.cursor = 'pointer';

                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.id = id;
                    input.name = 'kursi_ditutup[]';
                    input.value = String(seatNumber);
                    input.checked = selectedClosedSeats.has(seatNumber);

                    input.addEventListener('change', () => {
                        if (input.checked) {
                            selectedClosedSeats.add(seatNumber);
                        } else {
                            selectedClosedSeats.delete(seatNumber);
                        }
                    });

                    label.appendChild(input);
                    label.append(`Kursi ${seatNumber}`);
                    closedSeatOptions.appendChild(label);
                }
            };

            const toDateTimeValue = (date) => {
                return [
                    date.getFullYear(),
                    pad(date.getMonth() + 1),
                    pad(date.getDate()),
                ].join('-') + ' ' + [pad(date.getHours()), pad(date.getMinutes())].join(':');
            };

            const toDateTimeDisplay = (date) => {
                return [
                    date.getFullYear(),
                    pad(date.getMonth() + 1),
                    pad(date.getDate()),
                ].join('-') + ' ' + [pad(date.getHours()), pad(date.getMinutes())].join(':');
            };

            const parseDateTimeValue = (value) => {
                if (!value) {
                    return null;
                }

                const normalized = value.replace('T', ' ');
                const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/);
                if (!match) {
                    return null;
                }

                return new Date(
                    Number(match[1]),
                    Number(match[2]) - 1,
                    Number(match[3]),
                    Number(match[4]),
                    Number(match[5]),
                );
            };

            const splitInitialDeparture = () => {
                const initialDate = parseDateTimeValue(departureInput.value);
                if (!initialDate) {
                    return;
                }

                departureDateInput.value = [
                    initialDate.getFullYear(),
                    pad(initialDate.getMonth() + 1),
                    pad(initialDate.getDate()),
                ].join('-');
                departureTimeInput.value = [pad(initialDate.getHours()), pad(initialDate.getMinutes())].join(':');
            };

            const normalizeTimeInput = () => {
                const value = departureTimeInput.value.trim();
                const match = value.match(/^(\d{1,2})[:.](\d{2})$/);
                if (!match) {
                    return false;
                }

                const hour = Number(match[1]);
                const minute = Number(match[2]);
                if (hour < 0 || hour > 23 || minute < 0 || minute > 59) {
                    return false;
                }

                departureTimeInput.value = [pad(hour), pad(minute)].join(':');

                return true;
            };

            const syncDepartureValue = () => {
                if (!departureDateInput.value || !normalizeTimeInput()) {
                    departureInput.value = '';
                    return false;
                }

                departureInput.value = `${departureDateInput.value} ${departureTimeInput.value}`;

                return true;
            };

            const clearArrival = () => {
                arrivalInput.value = '';
                arrivalDisplayInput.value = '';
            };

            const refreshArrivalEstimate = () => {
                syncDepartureValue();
                const selectedOption = routeSelect.options[routeSelect.selectedIndex];
                const duration = Number(selectedOption?.dataset?.duration || 0);
                const departureDate = parseDateTimeValue(departureInput.value);

                if (!duration || !departureDate) {
                    clearArrival();
                    return;
                }

                // Preview ini membantu admin melihat waktu tiba, tetapi backend tetap jadi penentu final.
                departureDate.setMinutes(departureDate.getMinutes() + duration);
                arrivalInput.value = toDateTimeValue(departureDate);
                arrivalDisplayInput.value = toDateTimeDisplay(departureDate);
            };

            splitInitialDeparture();
            renderClosedSeatOptions();

            routeSelect.addEventListener('change', refreshArrivalEstimate);
            vehicleSelect.addEventListener('change', renderClosedSeatOptions);
            departureDateInput.addEventListener('input', refreshArrivalEstimate);
            departureTimeInput.addEventListener('input', refreshArrivalEstimate);
            departureTimeInput.addEventListener('blur', refreshArrivalEstimate);
            refreshArrivalEstimate();
        });
    </script>
@endpush
