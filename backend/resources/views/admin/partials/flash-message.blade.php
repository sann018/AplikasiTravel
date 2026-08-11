@php
    // Pesan dari controller ditampilkan seragam tanpa mengubah proses CRUD.
    $flashMessages = collect([
        ['key' => 'status', 'type' => 'success', 'title' => 'Berhasil', 'icon' => 'check-circle'],
        ['key' => 'success', 'type' => 'success', 'title' => 'Berhasil', 'icon' => 'check-circle'],
        ['key' => 'warning', 'type' => 'warning', 'title' => 'Perlu diperhatikan', 'icon' => 'alert-triangle'],
        ['key' => 'error', 'type' => 'danger', 'title' => 'Tindakan belum berhasil', 'icon' => 'alert-circle'],
    ])->filter(fn (array $message) => session()->has($message['key']));
@endphp

@if ($flashMessages->isNotEmpty())
    <div class="travs-feedback-stack" aria-live="polite" aria-atomic="true">
        @foreach ($flashMessages as $message)
            <section
                class="travs-feedback travs-feedback-{{ $message['type'] }}"
                role="{{ $message['type'] === 'danger' ? 'alert' : 'status' }}"
                data-flash-message
                @if ($message['type'] === 'success') data-flash-timeout="6500" @endif
            >
                <span class="travs-feedback-icon" aria-hidden="true">
                    <i data-feather="{{ $message['icon'] }}"></i>
                </span>
                <div class="travs-feedback-copy">
                    <strong>{{ $message['title'] }}</strong>
                    <p>{{ session($message['key']) }}</p>
                </div>
                <button
                    type="button"
                    class="travs-feedback-close"
                    data-flash-close
                    aria-label="Tutup pesan"
                    title="Tutup pesan"
                >
                    <i data-feather="x" aria-hidden="true"></i>
                </button>
            </section>
        @endforeach
    </div>
@endif
