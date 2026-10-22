{{--
    A figure worth seeing first — AdminLTE's info-box: an icon in its colour,
    what it counts, the figure, and a line under it.

    Given `wire:click` it is a button that narrows the screen to what it
    counts, marked `pressed` while that narrowing is in force; given `href`,
    a link. Otherwise it only shows. `soft` draws the icon on its colour's
    pale tint — for a step on a scale, where only the far end should shout.
--}}
@props([
    'label',
    'value',
    'icon' => 'info-circle',
    'variant' => 'primary',
    'soft' => false,
    'caption' => null,
    'pressed' => null,
    'href' => null,
])

@php
    $tag = $href !== null ? 'a' : ($attributes->has('wire:click') ? 'button' : 'div');
@endphp

<{{ $tag }}
    {{ $attributes->class(['info-box stat-box', 'stat-box-action' => $tag !== 'div', 'is-pressed' => $pressed === true]) }}
    @if ($tag === 'button') type="button" aria-pressed="{{ $pressed ? 'true' : 'false' }}" @endif
    @if ($href !== null) href="{{ $href }}" @endif
>
    <span @class(['info-box-icon', "text-bg-{$variant} shadow-sm" => ! $soft, "bg-{$variant}-subtle text-{$variant}-emphasis" => $soft]) aria-hidden="true">
        <i class="bi bi-{{ $icon }}"></i>
    </span>

    <span class="info-box-content">
        <span class="info-box-text">{{ $label }}</span>
        <span class="info-box-number num" dir="ltr">{{ $value }}</span>
        @if ($caption)
            <span class="stat-caption small text-body-secondary">{{ $caption }}</span>
        @endif
    </span>
</{{ $tag }}>
