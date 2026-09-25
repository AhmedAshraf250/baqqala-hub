{{--
    AdminLTE's headline KPI tile — a big number on a coloured field.

    Use for the few figures that drive a decision; use info-box for the rest.
--}}
@props([
    'value',
    'label',
    'icon' => 'graph-up',
    'variant' => 'primary',
    'route' => null,
])

<div {{ $attributes->class(['small-box', 'text-bg-'.$variant]) }}>
    <div class="inner">
        <h3>{{ $value }}</h3>
        <p>{{ $label }}</p>
    </div>

    <i class="small-box-icon bi bi-{{ $icon }}" aria-hidden="true"></i>

    @if ($route)
        <a href="{{ $route }}" class="small-box-footer link-light link-underline-opacity-0">
            {{ __('shell.actions.view_all') }}
            <i class="bi bi-chevron-{{ is_rtl() ? 'left' : 'right' }} ms-1" aria-hidden="true"></i>
        </a>
    @endif
</div>
