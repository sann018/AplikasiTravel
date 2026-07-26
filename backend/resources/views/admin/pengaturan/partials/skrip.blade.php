    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabButtons = document.querySelectorAll('[data-settings-tab]');
            const panes = document.querySelectorAll('[data-settings-pane]');

            tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const tabKey = button.dataset.settingsTab;
                    tabButtons.forEach((item) => {
                        const active = item.dataset.settingsTab === tabKey;
                        item.classList.toggle('is-active', active);
                        item.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    panes.forEach((pane) => pane.hidden = pane.dataset.settingsPane !== tabKey);
                    window.history.replaceState({}, '', `${window.location.pathname}?tab=${tabKey}`);
                });
            });

            // Field yang redup diaktifkan sesaat sebelum submit agar nilainya tetap diterima backend.
            // Hanya field data (input/select/textarea) yang diaktifkan kembali. Tombol submit
            // sengaja tidak disentuh agar penguncian anti klik-ganda dari app.js tetap berlaku.
            document.querySelectorAll('.travs-settings-pane').forEach((form) => {
                form.addEventListener('submit', () => form
                    .querySelectorAll('input:disabled, select:disabled, textarea:disabled')
                    .forEach((field) => field.disabled = false));
            });

            const logoInput = document.querySelector('#app_logo');
            let temporaryLogoUrl = null;
            logoInput?.addEventListener('change', () => {
                const wrapper = document.querySelector('[data-logo-preview-wrapper]');
                const image = document.querySelector('[data-logo-preview]');
                if (!wrapper || !image) return;
                const file = logoInput.files?.[0];
                if (!file) return;
                if (temporaryLogoUrl) URL.revokeObjectURL(temporaryLogoUrl);
                temporaryLogoUrl = URL.createObjectURL(file);
                wrapper.hidden = false;
                wrapper.dataset.previewSrc = temporaryLogoUrl;
                image.src = temporaryLogoUrl;
            });

            const bannerInput = document.querySelector('[data-banner-image-input]');
            const bannerPreview = document.querySelector('[data-banner-image-preview]');
            bannerInput?.addEventListener('change', () => {
                const file = bannerInput.files?.[0];
                if (!file || !bannerPreview) return;
                bannerPreview.src = URL.createObjectURL(file);
                bannerPreview.classList.remove('d-none');
            });
        });
    </script>
