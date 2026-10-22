{{--
    One line of a statement: what happened, in words and by an icon, what it
    was made of, when and by whom, the amount, and the balance after it with
    what it was before. A line that stands is tinted by its direction — red
    raising what is owed, green lowering it — fuller under the pointer.

    A voided entry stays where it was, struck through and saying so; a void
    and a correction read on the day they were made, naming the entry they
    concern; an entry undone at once reads as one grey line. Pressing the
    line's name — or Enter on the line — opens its details.

    Included with `$entry` (a StatementEntry), `$text` (its lines on one
    line, or null), `$time` (when, on the local wall), and `$was` (the
    balance before it, in words).
--}}
@php
    $icon = match ($entry->kind->value) {
        'charge' => 'bag-plus',
        'payment' => 'cash-coin',
        'opening_balance' => 'journal-bookmark',
        'discount' => 'percent',
        'return' => 'arrow-return-left',
        default => 'arrow-counterclockwise',
    };
    $reversal = $entry->kind->value === 'reversal';
    // Only what stands is coloured: a voided entry, a void, and an entry
    // undone at once read grey.
    $standing = ! $reversal && ! $entry->isVoided() && $entry->undone === null;
@endphp

<tr
    wire:key="entry-{{ $entry->id }}"
    data-row
    tabindex="-1"
    @class([
        'statement-voided' => $entry->isVoided() && $entry->undone === null,
        'statement-undone' => $entry->undone !== null,
        'statement-reversal' => $reversal,
        'flow-debit' => $standing && $entry->isDebit(),
        'flow-credit' => $standing && ! $entry->isDebit(),
    ])
>
    <td>
        <div class="d-flex gap-2 align-items-start">
            <span class="statement-icon" aria-hidden="true">
                <i class="bi bi-{{ $entry->undone ? 'arrow-counterclockwise' : $icon }}"></i>
            </span>

            <div class="min-w-0">
                <button
                    type="button"
                    class="btn btn-link p-0 text-start statement-title"
                    data-row-key="Enter"
                    wire:click="$dispatch('open-entry-details', { entryId: {{ $entry->id }} })"
                >
                    @if ($entry->undone)
                        {{ __('accounts::module.statement.undone', ['entry' => $entry->kind->label().' '.$entry->amount->toDisplayString()]) }}
                    @elseif ($reversal && $entry->cancels)
                        {{ __('accounts::module.statement.voids', ['date' => local_time($entry->cancels['at'])->locale(app()->getLocale())->isoFormat('D MMMM')]) }}
                    @else
                        {{ $entry->kind->label() }}
                    @endif
                </button>

                @if ($entry->isVoided() && $entry->undone === null)
                    <span class="badge bg-body-tertiary text-body-emphasis border ms-1">
                        {{ $entry->corrected ? __('accounts::module.statement.voided_corrected') : __('accounts::module.statement.voided') }}
                    </span>
                @endif

                @if ($entry->corrects && ! $reversal)
                    <span class="badge bg-body-tertiary text-body-emphasis border ms-1">
                        {{ __('accounts::module.statement.corrects', ['date' => local_time($entry->corrects['at'])->locale(app()->getLocale())->isoFormat('D MMMM')]) }}
                    </span>
                @endif

                <div class="small text-body-secondary text-truncate statement-detail" dir="auto">
                    @if ($entry->undone)
                        {{ __('accounts::module.statement.undone_by', ['name' => $entry->undone['by'], 'seconds' => $entry->undone['seconds']]) }}
                    @elseif ($reversal && $entry->reason)
                        {{ __('accounts::module.statement.reason', ['reason' => $entry->reason->label()]) }}@if ($entry->note) — {{ $entry->note }}@endif
                    @else
                        {{ $text ?? $entry->note }}
                    @endif
                </div>

                <div class="small text-body-secondary">
                    <time datetime="{{ $entry->at->toIso8601String() }}">{{ $time }}</time> · {{ $entry->by }}
                    {{-- On a phone the amount has no column of its own. --}}
                    <span class="d-sm-none">· <span class="num statement-amount" dir="ltr">{{ $entry->amount->toDisplayString() }}</span></span>
                </div>
            </div>
        </div>
    </td>

    <td class="d-none d-sm-table-cell text-end num statement-amount" dir="ltr">
        {{ $entry->amount->toDisplayString() }}
    </td>

    <td class="text-end text-nowrap">
        @if ($entry->undone)
            {{-- Undone: where it stood before, and still stands. --}}
            <x-accounts::admin.balance :amount="$entry->before" />
        @else
            <x-accounts::admin.balance :amount="$entry->after" />
            <div class="small text-body-secondary">{{ __('accounts::module.statement.was', ['balance' => $was]) }}</div>
        @endif
    </td>
</tr>
