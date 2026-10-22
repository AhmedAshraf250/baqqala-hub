{{--
    A select with a search box in it, for a list too long to scroll through —
    a shop's two hundred shelves, every time zone.

    The real <select> stays in the form, hidden, so wire:model and validation
    see an ordinary select; resources/js/admin/modules/search-select.js opens
    the list, filters it as the person types («ارز» finds «أرز»), and sets the
    select. The caller writes the label, as `<label id="{id}-label"
    for="{id}-toggle">`.

    `options` are value => label; `selected` is the current value; `empty`,
    when given, is offered as the first choice with no value («all shelves»,
    «none»); otherwise `placeholder` shows until something is chosen. `small`
    draws it at the size of a filter bar's controls.
--}}
@props([
    'id',
    'options' => [],
    'selected' => null,
    'placeholder' => '',
    'empty' => null,
    'invalid' => false,
    'describedby' => null,
    'small' => false,
])

@php
    $current = $selected === null || $selected === '' ? null : (string) $selected;
    $chosen = $current !== null && array_key_exists($current, $options) ? $options[$current] : null;
    $shown = $chosen ?? $empty ?? $placeholder;
@endphp

<div class="search-select" data-search-select>
    <select
        id="{{ $id }}"
        {{ $attributes->class(['visually-hidden']) }}
        tabindex="-1"
        aria-hidden="true"
        aria-labelledby="{{ $id }}-label"
        data-search-select-source
    >
        <option value="">{{ $empty ?? $placeholder }}</option>
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" wire:key="{{ $id }}-source-{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>

    <button
        type="button"
        id="{{ $id }}-toggle"
        @class(['form-select search-select-toggle', 'form-select-sm' => $small, 'is-invalid' => $invalid])
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-controls="{{ $id }}-panel"
        @if ($describedby) aria-describedby="{{ $describedby }}" @endif
        @if ($invalid) aria-invalid="true" @endif
        data-search-select-toggle
    >
        <span @class(['text-body-secondary' => $chosen === null && $empty === null]) dir="auto" data-search-select-current>{{ $shown }}</span>
    </button>

    <div id="{{ $id }}-panel" class="search-select-panel" data-search-select-panel hidden>
        <input
            type="search"
            class="form-control form-control-sm"
            placeholder="{{ __('shell.search_select.search') }}"
            aria-label="{{ __('shell.search_select.search') }}"
            aria-controls="{{ $id }}-listbox"
            autocomplete="off"
            dir="auto"
            data-search-select-input
        >

        <ul id="{{ $id }}-listbox" class="search-select-options" role="listbox" aria-labelledby="{{ $id }}-label">
            @if ($empty !== null)
                <li role="option" id="{{ $id }}-option-none" data-value="" aria-selected="{{ $current === null ? 'true' : 'false' }}">{{ $empty }}</li>
            @endif
            @foreach ($options as $value => $label)
                <li role="option" id="{{ $id }}-option-{{ $value }}" data-value="{{ $value }}" aria-selected="{{ $current === (string) $value ? 'true' : 'false' }}" dir="auto" wire:key="{{ $id }}-option-{{ $value }}">{{ $label }}</li>
            @endforeach
        </ul>

        <p class="small text-body-secondary mb-0 mt-2 px-2" data-search-select-none hidden>{{ __('shell.search_select.no_match') }}</p>
    </div>
</div>
