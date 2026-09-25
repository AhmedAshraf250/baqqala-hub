{{-- Shown where a list, table, or panel has nothing to display yet. --}}
@props([
    'icon' => 'inbox',
    'title' => null,
    'description' => null,
])

<div {{ $attributes->class(['empty-state text-center py-5']) }}>
    <i class="empty-state-icon bi bi-{{ $icon }} text-body-secondary mb-3 d-block" aria-hidden="true"></i>

    @if (filled($title))
        <h5 class="mb-2">{{ $title }}</h5>
    @endif

    @if (filled($description))
        <p class="text-body-secondary mb-3">{{ $description }}</p>
    @endif

    {{ $slot }}
</div>
