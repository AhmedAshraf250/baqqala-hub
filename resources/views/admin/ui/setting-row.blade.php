{{--
    One setting on a settings pane: an icon, its name, what changing it does,
    and its control at the end of the row. Rows stack on a phone.

    `for` names the control's id: the name becomes its label, and the help
    gets the id `{for}-help`, for the control's aria-describedby; `label-id`
    names the label for a control that points at it, such as a search select.
    `wide` gives a long control — a list of time zones — more room.
--}}
@props([
    'label',
    'help' => null,
    'icon' => 'sliders',
    'for' => null,
    'labelId' => null,
    'wide' => false,
])

<div {{ $attributes->class(['setting-row', 'setting-row-wide' => $wide]) }}>
    <span class="setting-row-icon" aria-hidden="true"><i class="bi bi-{{ $icon }}"></i></span>

    <div class="setting-row-text">
        @if ($for)
            <label for="{{ $for }}" @if ($labelId) id="{{ $labelId }}" @endif class="fw-semibold d-block">{{ $label }}</label>
        @else
            <div class="fw-semibold">{{ $label }}</div>
        @endif

        @if ($help)
            <div @if ($for) id="{{ $for }}-help" @endif class="small text-body-secondary">{{ $help }}</div>
        @endif

        {{ $details ?? '' }}
    </div>

    <div class="setting-row-control">
        {{ $slot }}
    </div>
</div>
