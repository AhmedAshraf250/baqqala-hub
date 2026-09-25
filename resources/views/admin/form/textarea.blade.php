{{-- A multi-line text control. --}}
@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 3,
    'hint' => null,
    'required' => false,
])

<x-admin::form.field :$name :$label :$hint :$required>
    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
    >{{ old($name, $value) }}</textarea>
</x-admin::form.field>
