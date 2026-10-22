{{--
    A monetary input.

    Always LTR and numeric regardless of page direction, and posts a plain
    decimal string that `Money::fromDecimalString()` parses exactly.
--}}
@props([
    'name',
    'label' => null,
    'value' => null,
    'hint' => null,
    'required' => false,
])

<x-admin::form.field :$name :$label :$hint :$required>
    <div class="input-group">
        <input
            type="text"
            inputmode="decimal"
            dir="ltr"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ old($name, $value?->toDecimalString()) }}"
            @if ($required) required @endif
            @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
            {{ $attributes->class(['form-control num', 'is-invalid' => $errors->has($name)]) }}
        >
        <span class="input-group-text">{{ currency_symbol() }}</span>
    </div>
</x-admin::form.field>
