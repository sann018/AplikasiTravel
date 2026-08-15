import * as bootstrap from 'bootstrap';
import feather from 'feather-icons';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    feather.replace();

    const closeFlashMessage = (message) => {
        if (!message || message.classList.contains('is-leaving')) {
            return;
        }

        message.classList.add('is-leaving');
        window.setTimeout(() => message.remove(), 200);
    };

    // Pesan berhasil hilang otomatis; pesan gagal tetap tampil sampai ditutup admin.
    document.querySelectorAll('[data-flash-message]').forEach((message) => {
        message.querySelector('[data-flash-close]')?.addEventListener('click', () => {
            closeFlashMessage(message);
        });

        const timeout = Number(message.dataset.flashTimeout ?? 0);
        if (timeout > 0) {
            window.setTimeout(() => closeFlashMessage(message), timeout);
        }
    });

    const confirmBackdrop = document.querySelector('[data-confirm-dialog]');
    const confirmDialog = confirmBackdrop?.querySelector('.travs-confirm-dialog');
    const confirmTitle = confirmBackdrop?.querySelector('[data-confirm-title]');
    const confirmMessage = confirmBackdrop?.querySelector('[data-confirm-message]');
    const confirmSubmit = confirmBackdrop?.querySelector('[data-confirm-submit]');
    const confirmCancel = confirmBackdrop?.querySelector('[data-confirm-cancel]');
    let pendingConfirmForm = null;
    let previousConfirmFocus = null;

    const formatConfirmDate = (value) => {
        if (!value) {
            return '';
        }

        const [year, month, day] = value.split('-');
        return year && month && day ? `${day}/${month}/${year}` : value;
    };

    const closeConfirmDialog = () => {
        if (!confirmBackdrop || confirmBackdrop.hidden) {
            return;
        }

        confirmBackdrop.hidden = true;
        document.body.classList.remove('travs-confirm-open');
        pendingConfirmForm = null;
        previousConfirmFocus?.focus();
        previousConfirmFocus = null;
    };

    const openConfirmDialog = (form) => {
        if (!confirmBackdrop || !confirmTitle || !confirmMessage || !confirmSubmit) {
            form.submit();
            return;
        }

        let message = form.dataset.confirmMessage ?? 'Pastikan data yang dipilih sudah benar.';
        const startField = form.dataset.confirmDateStart;
        const endField = form.dataset.confirmDateEnd;

        if (startField && endField) {
            const startDate = formatConfirmDate(form.elements[startField]?.value);
            const endDate = formatConfirmDate(form.elements[endField]?.value);

            if (startDate && endDate) {
                message = `Data pemesanan tanggal ${startDate} sampai ${endDate} akan dihapus permanen.`;
            }
        }

        pendingConfirmForm = form;
        previousConfirmFocus = document.activeElement;
        confirmTitle.textContent = form.dataset.confirmTitle ?? 'Konfirmasi tindakan';
        confirmMessage.textContent = message;
        confirmSubmit.textContent = form.dataset.confirmAction ?? 'Ya, lanjutkan';
        confirmBackdrop.hidden = false;
        document.body.classList.add('travs-confirm-open');
        window.requestAnimationFrame(() => confirmCancel?.focus());
    };

    document.querySelectorAll('form[data-confirm-title]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmApproved === 'true') {
                return;
            }

            event.preventDefault();
            openConfirmDialog(form);
        });
    });

    confirmCancel?.addEventListener('click', closeConfirmDialog);
    confirmBackdrop?.addEventListener('click', (event) => {
        if (event.target === confirmBackdrop) {
            closeConfirmDialog();
        }
    });
    confirmDialog?.addEventListener('click', (event) => event.stopPropagation());
    confirmSubmit?.addEventListener('click', () => {
        if (!pendingConfirmForm) {
            return;
        }

        const form = pendingConfirmForm;
        form.dataset.confirmApproved = 'true';
        confirmBackdrop.hidden = true;
        document.body.classList.remove('travs-confirm-open');
        form.requestSubmit();
    });

    // Toggle hanya mengubah visibilitas input; nilai dan proses submit tetap sama.
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const targetId = button.dataset.passwordTarget;
        const input = targetId ? document.getElementById(targetId) : null;

        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';

            const label = shouldShow ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', String(shouldShow));
            const iconName = shouldShow ? 'eye-off' : 'eye';
            button.innerHTML = feather.icons[iconName].toSvg({
                'data-password-icon': '',
                'aria-hidden': 'true',
            });
        });
    });

    const sidebar = document.querySelector('[data-admin-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    // Sidebar dibuat mandiri agar navigasi mobile tidak bergantung pada template lain.
    const setSidebarOpen = (isOpen) => {
        sidebar?.classList.toggle('is-open', isOpen);
        backdrop?.toggleAttribute('hidden', !isOpen);
        document.body.classList.toggle('travs-sidebar-open', isOpen);
    };

    document.querySelectorAll('[data-sidebar-open]').forEach((button) => {
        button.addEventListener('click', () => setSidebarOpen(true));
    });

    document.querySelectorAll('[data-sidebar-close], [data-sidebar-backdrop]').forEach((button) => {
        button.addEventListener('click', () => setSidebarOpen(false));
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1200) {
            setSidebarOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && confirmBackdrop && !confirmBackdrop.hidden) {
            closeConfirmDialog();
        }
    });

    document.querySelectorAll('form').forEach((form) => {
        const draftKey = form.dataset.draftKey
            ?? `travs-admin-draft:${window.location.pathname}:${form.getAttribute('action') ?? 'form'}`;

        const restoreDraft = () => {
            const rawDraft = localStorage.getItem(draftKey);
            if (!rawDraft) {
                return;
            }

            try {
                const draft = JSON.parse(rawDraft);
                form.querySelectorAll('input, textarea, select').forEach((field) => {
                    if (!field.name || field.type === 'file' || field.type === 'password' || field.type === 'hidden') {
                        return;
                    }

                    if (!(field.name in draft)) {
                        return;
                    }

                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = Boolean(draft[field.name]);
                        return;
                    }

                    field.value = draft[field.name] ?? '';
                });
            } catch (_) {
                localStorage.removeItem(draftKey);
            }
        };

        const saveDraft = () => {
            const draft = {};

            form.querySelectorAll('input, textarea, select').forEach((field) => {
                if (!field.name || field.type === 'file' || field.type === 'password' || field.type === 'hidden') {
                    return;
                }

                draft[field.name] = field.type === 'checkbox' || field.type === 'radio'
                    ? field.checked
                    : field.value;
            });

            localStorage.setItem(draftKey, JSON.stringify(draft));
        };

        restoreDraft();

        form.querySelectorAll('[data-clear-form-draft]').forEach((link) => {
            link.addEventListener('click', () => {
                // Reset membuang draft agar filter lama tidak muncul kembali.
                localStorage.removeItem(draftKey);
            });
        });

        form.querySelectorAll('input, textarea, select').forEach((field) => {
            if (field.type === 'file' || field.type === 'password' || field.type === 'hidden') {
                return;
            }

            // Draft lokal membantu admin saat halaman tidak sengaja refresh.
            // Password dan file sengaja tidak disimpan agar tetap aman.
            field.addEventListener('input', saveDraft);
            field.addEventListener('change', saveDraft);
        });

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return;
            }

            localStorage.removeItem(draftKey);

            const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');

            submitButtons.forEach((button) => {
                // Tombol dikunci setelah submit supaya klik berulang tidak
                // mengirim aksi yang sama berkali-kali ke server.
                button.disabled = true;

                if (button.tagName === 'BUTTON') {
                    button.dataset.originalText = button.textContent;
                    button.textContent = 'Memproses...';
                }
            });
        });
    });

    document.querySelectorAll('[data-collapsible-section]').forEach((section) => {
        const toggle = section.querySelector(':scope > [data-collapse-header] [data-collapse-toggle]');
        const body = section.querySelector(':scope > [data-collapse-body]');
        const label = toggle?.querySelector('[data-collapse-label]');
        const sectionId = section.dataset.collapseId
            ?? section.id
            ?? section.querySelector('h2, h3, h4')?.textContent?.trim()
            ?? 'section';
        const storageKey = `travs-admin-collapse:${window.location.pathname}:${sectionId}`;

        if (!toggle || !body || toggle.dataset.collapseReady === 'true') {
            return;
        }

        toggle.dataset.collapseReady = 'true';

        const applyCollapsed = (isCollapsed) => {
            const buttonText = isCollapsed ? 'Buka bagian' : 'Tutup bagian';

            section.classList.toggle('is-collapsed', isCollapsed);
            body.toggleAttribute('hidden', isCollapsed);
            toggle.setAttribute('aria-expanded', String(!isCollapsed));
            toggle.title = buttonText;

            if (label) {
                label.textContent = buttonText;
            }
        };

        applyCollapsed(localStorage.getItem(storageKey) === 'closed');

        toggle.addEventListener('click', () => {
            const nextCollapsed = !section.classList.contains('is-collapsed');

            // Kondisi buka/tutup disimpan di browser supaya halaman panjang
            // tetap nyaman saat admin refresh atau kembali ke halaman ini.
            applyCollapsed(nextCollapsed);
            localStorage.setItem(storageKey, nextCollapsed ? 'closed' : 'open');
        });
    });
});
