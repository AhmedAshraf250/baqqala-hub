{{-- A single boolean switch, with a hidden field so "off" is submitted too. --}}
@props([
    'name',
    'label' => null,
    'checked' => false,
    'hint' => null,
])

<div {{ $attributes->class(['form-check form-switch mb-3']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $name }}"
        value="1"
        class="form-check-input"
        @checked(old($name, $checked))
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
    >

    @if (filled($label))
        <label class="form-check-label" for="{{ $name }}">{{ $label }}</label>
    @endif

    @if ($errors->has($name))
        <div id="{{ $name }}-error" class="invalid-feedback d-block">{{ $errors->first($name) }}</div>
    @elseif (filled($hint))
        <div class="form-text">{{ $hint }}</div>
    @endif
</div>
