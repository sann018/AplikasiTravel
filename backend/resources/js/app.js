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
});
