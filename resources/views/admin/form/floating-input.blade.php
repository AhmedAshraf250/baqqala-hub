{{--
    AdminLTE's floating-label input with a trailing icon, used on the
    authentication screens.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'icon' => null,
    'value' => null,
    'required' => false,
])

@php
    $hasError = $errors->has($name);
@endphp

<div class="input-group mb-3">
    <div class="form-floating">
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ $type === 'password' ? '' : old($name, $value) }}"
            placeholder="{{ $label }}"
            @if ($required) required @endif
            {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}
        >
        <label for="{{ $name }}">{{ $label }}</label>
    </div>

    @if ($icon)
        <div class="input-group-text">
            <span class="bi bi-{{ $icon }}" aria-hidden="true"></span>
        </div>
    @endif
</div>
