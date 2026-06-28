<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-collapsible-section]').forEach((section) => {
            const toggle = section.querySelector(':scope > [data-collapse-header] [data-collapse-toggle]');
            const body = section.querySelector(':scope > [data-collapse-body]');
            const label = toggle?.querySelector('[data-collapse-label]');

            if (!toggle || !body || toggle.dataset.collapseReady === 'true') {
                return;
            }

            toggle.dataset.collapseReady = 'true';

            // Collapse ini hanya mengatur tampilan yang sudah ada di browser, jadi tidak mengirim request baru.
            toggle.addEventListener('click', async () => {
                if (toggle.dataset.collapseAnimating === 'true') {
                    return;
                }

                const willCollapse = toggle.getAttribute('aria-expanded') === 'true';
                const buttonText = willCollapse ? 'Buka bagian' : 'Tutup bagian';
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                toggle.dataset.collapseAnimating = 'true';

                section.classList.toggle('is-collapsed', willCollapse);
                toggle.setAttribute('aria-expanded', willCollapse ? 'false' : 'true');
                toggle.title = buttonText;

                if (label) {
                    label.textContent = buttonText;
                }

                if (reduceMotion || typeof body.animate !== 'function') {
                    body.hidden = willCollapse;
                    toggle.dataset.collapseAnimating = 'false';
                    return;
                }

                if (!willCollapse) {
                    body.hidden = false;
                }

                // Animasi pendek menjaga perpindahan tetap halus tanpa plugin atau request tambahan.
                const animation = body.animate(
                    willCollapse
                        ? [{ opacity: 1, transform: 'translateY(0)' }, { opacity: 0, transform: 'translateY(-6px)' }]
                        : [{ opacity: 0, transform: 'translateY(-6px)' }, { opacity: 1, transform: 'translateY(0)' }],
                    { duration: 180, easing: 'ease-out' },
                );

                try {
                    await animation.finished;
                } catch (_) {
                    // Jika animasi dibatalkan browser, kondisi buka-tutup tetap diselesaikan di bawah.
                }

                body.hidden = willCollapse;
                toggle.dataset.collapseAnimating = 'false';
            });
        });
    });
</script>
