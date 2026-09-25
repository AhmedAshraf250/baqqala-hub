{{--
    Renders a Money value.

    Amounts stay LTR inside Arabic text — that is standard Unicode bidi
    behaviour for digits, and forcing otherwise makes figures unreadable.
--}}
@props([
    'amount',
    'colored' => false,
])

@php
    // When colouring an outstanding total: owing is red, in credit is green.
    $variant = match (true) {
        ! $colored => null,
        $amount->isPositive() => 'text-danger',
        $amount->isNegative() => 'text-success',
        default => 'text-body-secondary',
    };
@endphp

<span {{ $attributes->class(['font-monospace', 'text-nowrap', $variant]) }} dir="ltr">
    {{ $amount->format() }}
</span>
