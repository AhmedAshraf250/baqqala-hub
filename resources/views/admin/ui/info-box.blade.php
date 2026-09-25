{{-- AdminLTE's compact stat row: icon, label, figure. --}}
@props([
    'value',
    'label',
    'icon' => 'info-circle',
    'variant' => 'primary',
    'caption' => null,
])

<div {{ $attributes->class(['info-box']) }}>
    <span class="info-box-icon text-bg-{{ $variant }} shadow-sm">
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    </span>

    <div class="info-box-content">
        <span class="info-box-text">{{ $label }}</span>
        <span class="info-box-number">{{ $value }}</span>

        @if ($caption)
            <span class="info-box-caption text-body-secondary">{{ $caption }}</span>
        @endif
    </div>
</div>
