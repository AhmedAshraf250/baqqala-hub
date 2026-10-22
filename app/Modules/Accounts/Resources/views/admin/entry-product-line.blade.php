{{--
    One product line of a charge, inside the entry drawer: what it is and what
    it comes to on the first row; how much of it, and at what price, on the
    second — the price at the row's end, where every line keeps its figure's
    field, so the fields of a long charge stand in one column.

    A loose product is weighed, or sold by what it costs («بـ10 جنيه»); a
    price the counter changed is marked, with the catalog's beside it.
    Included by the drawer, inside the line's card, with the line and its
    index, and what the drawer worked out about it: `$total`, `$changed`,
    `$loose`, `$unit` (its unit's name), and `$approx` (what an amount buys,
    for a line sold by amount).
--}}

<div class="entry-line-body">
    <div class="d-flex align-items-start gap-2">
        <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold text-break" dir="auto">{{ $line['name'] }}</div>
            <div class="small text-body-secondary">
                @if ($line['category'])<span dir="auto">{{ $line['category'] }}</span> ·@endif
                {{ __('accounts::module.entry.per_unit', ['price' => $line['listPrice'] ?? $line['price'], 'unit' => $unit]) }}
            </div>
        </div>
        <div class="entry-line-total num" dir="ltr">{{ $total?->toDisplayString() ?? '—' }}</div>
    </div>

    <div class="entry-line-controls">
        @if ($loose)
            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('accounts::module.entry.sold_how', ['name' => $line['name']]) }}">
                <button type="button" @class(['btn', 'btn-primary' => ! $line['byAmount'], 'btn-outline-secondary' => $line['byAmount']]) wire:click="sellByAmount('{{ $line['key'] }}', false)" aria-pressed="{{ $line['byAmount'] ? 'false' : 'true' }}">{{ __('accounts::module.entry.by_weight') }}</button>
                <button type="button" @class(['btn', 'btn-primary' => $line['byAmount'], 'btn-outline-secondary' => ! $line['byAmount']]) wire:click="sellByAmount('{{ $line['key'] }}', true)" aria-pressed="{{ $line['byAmount'] ? 'true' : 'false' }}">{{ __('accounts::module.entry.by_amount') }}</button>
            </div>
        @endif

        @if ($line['byAmount'])
            @if ($approx)
                <span class="small text-body-secondary">{{ __('accounts::module.entry.about', ['quantity' => $approx]) }}</span>
            @endif

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
                        @class(['form-control num text-end', 'is-invalid' => $errors->has("form.lines.{$index}.amount")])
                    >
                    <span class="input-group-text">{{ currency_symbol() }}</span>
                </div>
            </div>
        @else
            <div class="input-group input-group-sm entry-quantity" role="group" aria-label="{{ __('accounts::module.entry.quantity_of', ['name' => $line['name']]) }}">
                <button type="button" class="btn btn-outline-secondary" wire:click="step('{{ $line['key'] }}', -1)" aria-label="{{ __('accounts::module.entry.less', ['name' => $line['name']]) }}">
                    <i class="bi bi-dash" aria-hidden="true"></i>
                </button>
                <label for="line-quantity-{{ $line['key'] }}" class="visually-hidden">{{ __('accounts::module.entry.quantity') }}</label>
                <input
                    id="line-quantity-{{ $line['key'] }}"
                    type="text"
                    inputmode="decimal"
                    dir="ltr"
                    autocomplete="off"
                    wire:model.live.debounce.250ms="form.lines.{{ $index }}.quantity"
                    x-on:keydown.enter.prevent="document.getElementById('entry-search')?.focus()"
                    @class(['form-control num text-center', 'is-invalid' => $errors->has("form.lines.{$index}.quantity")])
                >
                <button type="button" class="btn btn-outline-secondary" wire:click="step('{{ $line['key'] }}', 1)" aria-label="{{ __('accounts::module.entry.more', ['name' => $line['name']]) }}">
                    <i class="bi bi-plus" aria-hidden="true"></i>
                </button>
                <span class="input-group-text">{{ $unit }}</span>
            </div>

            @if ($loose)
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('accounts::module.entry.quick_weights') }}">
                    @foreach (['0.25' => '¼', '0.5' => '½', '0.75' => '¾', '1' => '1'] as $weight => $shown)
                        <button type="button" class="btn btn-outline-secondary" wire:click="setQuantity('{{ $line['key'] }}', '{{ $weight }}')" aria-label="{{ __('accounts::module.entry.weight', ['quantity' => $weight, 'unit' => $unit]) }}" wire:key="weight-{{ $line['key'] }}-{{ $weight }}">{{ $shown }}</button>
                    @endforeach
                </div>
            @endif

            <div class="entry-line-money">
                <span class="entry-line-times" aria-hidden="true">×</span>

                <div class="input-group input-group-sm entry-price">
                    <label for="line-price-{{ $line['key'] }}" class="visually-hidden">{{ __('accounts::module.entry.price_of', ['name' => $line['name']]) }}</label>
                    <input
                        id="line-price-{{ $line['key'] }}"
                        type="text"
                        inputmode="decimal"
                        dir="ltr"
                        autocomplete="off"
                        wire:model.live.debounce.250ms="form.lines.{{ $index }}.price"
                        x-on:keydown.enter.prevent="document.getElementById('entry-search')?.focus()"
                        @class(['form-control num text-end', 'border-warning bg-warning-subtle' => $changed, 'is-invalid' => $errors->has("form.lines.{$index}.price")])
                    >
                    <span class="input-group-text">{{ currency_symbol() }}</span>
                </div>
            </div>
        @endif
    </div>

    @if ($changed)
        <div class="small text-warning-emphasis"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('accounts::module.entry.original_price', ['price' => $line['listPrice']]) }}</div>
    @endif

    @foreach (['quantity', 'price', 'amount'] as $field)
        @error("form.lines.{$index}.{$field}")<div class="small text-danger-emphasis" role="alert">{{ $message }}</div>@enderror
    @endforeach
</div>
