import QRCode from 'qrcode';

const roundedRect = (context, x, y, width, height, radius, fill) => {
    context.beginPath();
    context.roundRect(x, y, width, height, radius);
    context.fillStyle = fill;
    context.fill();
};

const drawLabelValue = (context, label, value, x, y, maxWidth = 500) => {
    context.fillStyle = '#667899';
    context.font = '500 24px Arial, sans-serif';
    context.fillText(label, x, y, maxWidth);
    context.fillStyle = '#10233f';
    context.font = '700 31px Arial, sans-serif';
    context.fillText(value || '—', x, y + 42, maxWidth);
};

const createTicketImage = async (payload) => {
    const canvas = document.createElement('canvas');
    canvas.width = 1600;
    canvas.height = 900;
    const context = canvas.getContext('2d');

    context.fillStyle = '#eef3f9';
    context.fillRect(0, 0, canvas.width, canvas.height);
    roundedRect(context, 45, 45, 1510, 810, 38, '#ffffff');
    roundedRect(context, 45, 45, 455, 810, 38, '#102e53');

    context.fillStyle = '#ffffff';
    context.font = '700 32px Arial, sans-serif';
    context.fillText('RUSLY TRAVEL', 95, 115);
    context.font = '700 46px Arial, sans-serif';
    context.fillText(payload.rute, 95, 225, 350);
    context.fillStyle = '#bfd0e5';
    context.font = '500 25px Arial, sans-serif';
    context.fillText('Tanggal keberangkatan', 95, 360);
    context.fillStyle = '#ffffff';
    context.font = '700 32px Arial, sans-serif';
    context.fillText(payload.tanggal, 95, 405, 350);
    context.fillStyle = '#bfd0e5';
    context.font = '500 25px Arial, sans-serif';
    context.fillText('Waktu keberangkatan', 95, 500);
    context.fillStyle = '#ffffff';
    context.font = '700 42px Arial, sans-serif';
    context.fillText(`${payload.waktu} WIB`, 95, 550);
    roundedRect(context, 95, 680, 250, 58, 29, '#36577d');
    context.fillStyle = '#ffffff';
    context.font = '700 24px Arial, sans-serif';
    context.fillText(payload.status_pindai, 125, 717);

    context.fillStyle = '#10233f';
    context.font = '700 42px Arial, sans-serif';
    context.fillText(payload.penumpang, 560, 135, 520);
    context.fillStyle = '#667899';
    context.font = '500 24px Arial, sans-serif';
    context.fillText(payload.kode_tiket, 560, 178, 520);
    drawLabelValue(context, 'Nomor kursi', payload.nomor_kursi, 560, 285, 220);
    drawLabelValue(context, 'Kode pemesanan', payload.kode_pemesanan, 815, 285, 330);
    drawLabelValue(context, 'Kendaraan', payload.kendaraan, 560, 440, 480);
    drawLabelValue(context, 'Nomor polisi', payload.nomor_plat, 560, 595, 350);
    drawLabelValue(context, 'Pengemudi', payload.pengemudi, 560, 735, 480);

    const qrCanvas = document.createElement('canvas');
    await QRCode.toCanvas(qrCanvas, payload.qr_token, {
        width: 330,
        margin: 2,
        color: { dark: '#10233fff', light: '#ffffffff' },
        errorCorrectionLevel: 'M',
    });
    roundedRect(context, 1165, 155, 350, 430, 30, '#f6f9fc');
    context.drawImage(qrCanvas, 1175, 175, 330, 330);
    context.fillStyle = '#10233f';
    context.font = '700 25px Arial, sans-serif';
    context.textAlign = 'center';
    context.fillText('QR verifikasi tiket', 1340, 550);
    context.fillStyle = '#667899';
    context.font = '500 21px Arial, sans-serif';
    context.fillText('Tunjukkan saat keberangkatan', 1340, 630);
    context.textAlign = 'start';

    return canvas;
};

const saveCanvas = (canvas, filename) => {
    canvas.toBlob((blob) => {
        if (!blob) {
            return;
        }

        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    }, 'image/png');
};

export const initializeAdminTicket = async () => {
    const root = document.querySelector('[data-admin-ticket]');
    const payloadElement = document.querySelector('[data-admin-ticket-payload]');

    if (!root || !payloadElement) {
        return;
    }

    const errorMessage = document.querySelector('[data-ticket-error]');

    try {
        const payload = JSON.parse(payloadElement.textContent);
        const qrCanvas = root.querySelector('[data-ticket-qr]');

        await QRCode.toCanvas(qrCanvas, payload.qr_token, {
            width: 260,
            margin: 2,
            color: { dark: '#10233fff', light: '#ffffffff' },
            errorCorrectionLevel: 'M',
        });

        const download = async () => {
            const ticketCanvas = await createTicketImage(payload);
            saveCanvas(ticketCanvas, payload.nama_berkas);
        };

        document.querySelectorAll('[data-ticket-download]').forEach((button) => {
            button.addEventListener('click', () => download().catch(() => {
                errorMessage?.removeAttribute('hidden');
            }));
        });

        if (root.dataset.autoDownload === 'true') {
            await download();
        }
    } catch {
        errorMessage?.removeAttribute('hidden');
    }
};
