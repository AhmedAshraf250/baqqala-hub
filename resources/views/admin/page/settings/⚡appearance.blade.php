<?php

use Livewire\Component;

new class extends Component {
    //
}; ?>

<x-admin::ui.card :title="__('shell.settings.appearance')" icon="palette">
    <p class="text-body-secondary">{{ __('shell.settings.appearance_description') }}</p>

    <div class="settings-rows">
        {{--
            The theme is a per-browser preference, applied before first paint by the
            inline script in the shell. These buttons reuse the same
            `data-bs-theme-value` contract the header dropdown uses, so there is one
            implementation and no server round-trip.
        --}}
        <x-admin::ui.setting-row :label="__('shell.settings.theme')" :help="__('shell.settings.theme_description')" icon="circle-half">
            <div class="btn-group w-100" role="group" aria-label="{{ __('shell.actions.toggle_theme') }}">
                @foreach (['light' => 'sun-fill', 'dark' => 'moon-fill', 'auto' => 'circle-half'] as $value => $icon)
                    <button type="button" class="btn btn-outline-secondary" data-bs-theme-value="{{ $value }}">
                        <i class="bi bi-{{ $icon }} me-1" aria-hidden="true"></i>
                        {{ __('shell.actions.theme_'.$value) }}
                    </button>
                @endforeach
            </div>
        </x-admin::ui.setting-row>

        {{--
            Keyboard shortcuts, for this browser like the theme: the switch is read
            and written by resources/js/admin/modules/shortcuts.js, and applied
            before the first paint by the same inline script.
        --}}
        <x-admin::ui.setting-row :label="__('shell.settings.shortcuts')" :help="__('shell.settings.shortcuts_description')" icon="keyboard" for="shortcuts-toggle">
            <div class="form-check form-switch setting-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="shortcuts-toggle" data-shortcuts-toggle checked aria-describedby="shortcuts-toggle-help" wire:ignore>
            </div>
        </x-admin::ui.setting-row>
    </div>
</x-admin::ui.card>
