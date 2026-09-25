{{-- A Bootstrap alert with a leading icon. --}}
@props([
    'variant' => 'info',
    'icon' => 'info-circle',
    'dismissible' => false,
])

<div
    {{ $attributes->class([
        'alert d-flex align-items-start gap-2',
        'alert-'.$variant,
        'alert-dismissible fade show' => $dismissible,
    ]) }}
    role="alert"
>
    <i class="bi bi-{{ $icon }} mt-1" aria-hidden="true"></i>

    <div class="flex-grow-1">{{ $slot }}</div>

    @if ($dismissible)
        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="{{ __('shell.actions.dismiss') }}"
        ></button>
    @endif
</div>
