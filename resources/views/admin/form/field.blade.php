{{--
    The wrapper every form control shares: label, control, error, hint.

    Keeping this in one place is what makes validation display consistent —
    each control component renders its input into this shell.
--}}
@props([
    'name',
    'label' => null,
    'hint' => null,
    'required' => false,
])

@php
    $hasError = $errors->has($name);
@endphp

<div {{ $attributes->class(['mb-3']) }}>
    @if (filled($label))
        <label for="{{ $name }}" class="form-label">
            {{ $label }}
            @if ($required)
                <span class="text-danger" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hasError)
        <div id="{{ $name }}-error" class="invalid-feedback d-block">{{ $errors->first($name) }}</div>
    @elseif (filled($hint))
        <div class="form-text">{{ $hint }}</div>
    @endif
</div>
