import * as bootstrap from 'bootstrap';
import feather from 'feather-icons';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    feather.replace();

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
