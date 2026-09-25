<?php

use Livewire\Component;

new class extends Component {
    //
}; ?>

<x-admin::ui.card :title="__('shell.settings.appearance')" icon="palette">
    <p class="text-body-secondary">{{ __('shell.settings.appearance_description') }}</p>

    {{--
        The theme is a per-browser preference, applied before first paint by the
        inline script in the shell. These buttons reuse the same
        `data-bs-theme-value` contract the header dropdown uses, so there is one
        implementation and no server round-trip.
    --}}
    <div class="btn-group" role="group" aria-label="{{ __('shell.actions.toggle_theme') }}">
        @foreach (['light' => 'sun-fill', 'dark' => 'moon-fill', 'auto' => 'circle-half'] as $value => $icon)
            <button type="button" class="btn btn-outline-secondary" data-bs-theme-value="{{ $value }}">
                <i class="bi bi-{{ $icon }} me-1" aria-hidden="true"></i>
                {{ __('shell.actions.theme_'.$value) }}
            </button>
        @endforeach
    </div>
</x-admin::ui.card>
