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
