{{--
    An initials avatar.

    The shop has no profile photos, and a coloured circle with initials reads
    better in a dense list of people than a generic silhouette would.
--}}
@props([
    'name',
    // sm (menu bar), md (lists), lg (menu header), xl (profile page).
    'size' => 'md',
    'variant' => 'primary',
])

@php
    $initials = str($name)->squish()->explode(' ')
        ->take(2)
        ->map(fn (string $part): string => str($part)->substr(0, 1)->upper()->toString())
        ->implode('');
@endphp

<span
    {{ $attributes->class(['avatar avatar-'.$size, 'rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0', 'bg-'.$variant.'-subtle', 'text-'.$variant.'-emphasis']) }}
    aria-hidden="true"
>{{ $initials }}</span>
