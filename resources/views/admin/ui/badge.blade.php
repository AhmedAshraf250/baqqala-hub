{{-- A contextual pill, used for statuses and counts. --}}
@props([
    'variant' => 'secondary',
    'icon' => null,
])

<span {{ $attributes->class(['badge d-inline-flex align-items-center gap-1', 'text-bg-'.$variant]) }}>
    @if ($icon)
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    @endif

    {{ $slot }}
</span>
