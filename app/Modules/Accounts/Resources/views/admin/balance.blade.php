{{--
    Where a balance stands, in the counter's words: «عليه 1,250.00»,
    «له 50.00», «خالص». Never a minus sign: the word says which way it goes,
    and the colour lets the eye find it first — red owing, green owed.

    `<x-accounts::admin.balance :amount="$outstanding" />`; `size="lg"` for the
    figure a screen is about. Word and figure are written on one line, so the
    text reads as the counter says it.
--}}
@props([
    'amount',
    'size' => null,
])

@php
    $word = match (true) {
        $amount->isPositive() => 'owes',
        $amount->isNegative() => 'in_credit',
        default => 'settled',
    };
    $figure = $amount->isZero() ? null : ($amount->isNegative() ? $amount->negated() : $amount)->toDisplayString();
@endphp

<span {{ $attributes->class(['balance d-inline-flex align-items-baseline gap-1 text-nowrap', 'balance-lg' => $size === 'lg', 'flow-debit' => $word === 'owes', 'flow-credit' => $word === 'in_credit']) }}>@if ($figure === null)<i class="bi bi-check-circle text-success-emphasis" aria-hidden="true"></i> @endif<span class="balance-word">{{ __('accounts::module.balance.'.$word) }}</span>@if ($figure) <span class="balance-figure num" dir="ltr">{{ $figure }}</span>@endif</span>
