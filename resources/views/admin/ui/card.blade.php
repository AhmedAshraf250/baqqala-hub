{{--
    An AdminLTE card.

    Slots: $header (extra header content), $tools (header right side),
    $footer. Set `collapsible` or `maximizable` to get AdminLTE's CardWidget
    buttons without writing the markup.
--}}
@props([
    'title' => null,
    'icon' => null,
    'variant' => null,
    'collapsible' => false,
    'maximizable' => false,
    'flush' => false,
])

<div {{ $attributes->class(['card', 'shadow-sm', 'card-'.$variant => $variant]) }}>
    @if (filled($title) || filled($tools ?? null) || $collapsible || $maximizable)
        <div class="card-header">
            <h3 class="card-title d-flex align-items-center gap-2">
                @if ($icon)
                    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                @endif
                {{ $title }}
            </h3>

            <div class="card-tools">
                {{ $tools ?? '' }}

                @if ($maximizable)
                    <button type="button" class="btn btn-tool" data-lte-toggle="card-maximize"
                            aria-label="{{ __('shell.actions.maximize') }}">
                        <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
                        <i data-lte-icon="minimize" class="bi bi-fullscreen-exit d-none" aria-hidden="true"></i>
                    </button>
                @endif

                @if ($collapsible)
                    <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse"
                            aria-label="{{ __('shell.actions.collapse') }}">
                        <i data-lte-icon="expand" class="bi bi-dash-lg" aria-hidden="true"></i>
                        <i data-lte-icon="collapse" class="bi bi-plus-lg" aria-hidden="true"></i>
                    </button>
                @endif
            </div>
        </div>
    @endif

    <div @class(['card-body', 'p-0' => $flush])>
        {{ $slot }}
    </div>

    @if (filled($footer ?? null))
        <div class="card-footer">{{ $footer }}</div>
    @endif
</div>
