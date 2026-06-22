{{-- Tombol ini dipakai ulang agar ikon dan aksesibilitas section tetap konsisten. --}}
<button
    type="button"
    class="collapsible-section__toggle"
    data-collapse-toggle
    aria-expanded="true"
    aria-controls="{{ $targetId }}"
    title="Tutup bagian"
>
    <span class="collapsible-section__icon collapsible-section__icon-open" aria-hidden="true">
        <i data-feather="chevron-up"></i>
    </span>
    <span class="collapsible-section__icon collapsible-section__icon-closed" aria-hidden="true">
        <i data-feather="chevron-down"></i>
    </span>
    <span class="visually-hidden" data-collapse-label>Tutup bagian</span>
</button>
