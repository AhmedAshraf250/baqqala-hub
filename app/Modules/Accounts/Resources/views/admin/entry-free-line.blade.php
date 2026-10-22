{{--
    One line of a charge with no product, inside the entry drawer — in the
    same shape as a product line, so every line reads alike: what it is and
    what it comes to on the first row, its figure's field at the end of the
    second, where a product line's price is.

    Included by the drawer, inside the line's card, with the line and its
    index, `$total` (what it comes to, or null), and `$placeholder` (the name
    a line gets when none is typed).
--}}

<div class="entry-line-body">
    <div class="d-flex align-items-center gap-2">
        <div class="input-group input-group-sm flex-grow-1 min-w-0">
            <label for="line-name-{{ $line['key'] }}" class="input-group-text">{{ __('accounts::module.entry.line_name') }}</label>
            <input
                id="line-name-{{ $line['key'] }}"
                type="text"
                dir="auto"
                maxlength="255"
                autocomplete="off"
                placeholder="{{ $placeholder }}"
                wire:model.blur="form.lines.{{ $index }}.name"
                x-on:keydown.enter.prevent="document.getElementById('entry-search')?.focus()"
                @if ($errors->has("form.lines.{$index}.name")) aria-invalid="true" aria-describedby="line-name-{{ $line['key'] }}-error" @endif
                @class(['form-control', 'is-invalid' => $errors->has("form.lines.{$index}.name")])
            >
        </div>
        <div class="entry-line-total num" dir="ltr">{{ $total?->toDisplayString() ?? '—' }}</div>
    </div>

    <div class="entry-line-controls">
        <span class="small text-body-secondary">{{ __('accounts::module.entry.free_line') }}</span>

        <div class="entry-line-money">
            <label for="line-amount-{{ $line['key'] }}" class="entry-line-money-label">{{ __('accounts::module.entry.line_amount') }}</label>
            <div class="input-group input-group-sm entry-price">
                <input
                    id="line-amount-{{ $line['key'] }}"
                    type="text"
                    inputmode="decimal"
                    dir="ltr"
                    autocomplete="off"
                    wire:model.live.debounce.250ms="form.lines.{{ $index }}.amount"
                    x-on:keydown.enter.prevent="document.getElementById('entry-search')?.focus()"
                    @if ($errors->has("form.lines.{$index}.amount")) aria-invalid="true" aria-describedby="line-amount-{{ $line['key'] }}-error" @endif
                    @class(['form-control num text-end', 'is-invalid' => $errors->has("form.lines.{$index}.amount")])
                >
                <span class="input-group-text">{{ currency_symbol() }}</span>
            </div>
        </div>
    </div>

    @error("form.lines.{$index}.name")<div id="line-name-{{ $line['key'] }}-error" class="small text-danger-emphasis" role="alert">{{ $message }}</div>@enderror
    @error("form.lines.{$index}.amount")<div id="line-amount-{{ $line['key'] }}-error" class="small text-danger-emphasis" role="alert">{{ $message }}</div>@enderror
</div>
