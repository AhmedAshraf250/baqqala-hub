<?php

use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Domain\Queries\DailyJournal;
use App\Modules\Accounts\Domain\Queries\JournalEntry;
use App\Modules\Accounts\Domain\Queries\JournalTotals;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The day's book, for the owner at closing: every entry of a business day for
 * every customer, the day's figures, and what each person recorded — the cash
 * they took above all, which the drawer is counted against.
 *
 * The day, the person, and the kind are in the page's address, so a day is a
 * link. Voided entries are listed, struck through, and counted in nothing.
 */
new #[Layout('admin::layout.app')] class extends Component {
    /** The business day, as Y-m-d in the owner's zone; empty for today. */
    #[Url(except: '')]
    public string $day = '';

    #[Url(except: null)]
    public ?int $person = null;

    #[Url(except: '')]
    public string $kind = '';

    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('accounts::module.navigation.journal')]);
    }

    /**
     * The business day shown: the one asked for, never after today.
     */
    #[Computed]
    public function date(): CarbonImmutable
    {
        $clock = app(LocalClock::class);
        $today = $clock->today();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day) !== 1) {
            return $today;
        }

        $asked = CarbonImmutable::createFromFormat('!Y-m-d', $this->day, $clock->timezone());

        return $asked === null || $asked->greaterThan($today) ? $today : $asked;
    }

    /**
     * Today's business day, as the date field writes it — its last choice.
     */
    #[Computed]
    public function lastDay(): string
    {
        return app(LocalClock::class)->today()->format('Y-m-d');
    }

    /**
     * Whether the day has more entries than one page lists.
     */
    #[Computed]
    public function limited(): bool
    {
        return count($this->entries) >= DailyJournal::LIMIT;
    }

    /**
     * Which way the day moved what customers owe, in words.
     */
    public function changeCaption(): string
    {
        $change = $this->totals->change();

        return match (true) {
            $change->isPositive() => __('accounts::module.journal.change_up'),
            $change->isNegative() => __('accounts::module.journal.change_down'),
            default => __('accounts::module.journal.change_none'),
        };
    }

    #[Computed]
    public function isToday(): bool
    {
        return $this->date->isSameDay(app(LocalClock::class)->today());
    }

    /**
     * @return list<JournalEntry>
     */
    #[Computed]
    public function entries(): array
    {
        return app(DailyJournal::class)->entries($this->date, $this->person, EntryKind::tryFrom($this->kind));
    }

    #[Computed]
    public function totals(): JournalTotals
    {
        return app(DailyJournal::class)->totals($this->date);
    }

    /**
     * @return list<array{id: int, name: string, totals: JournalTotals}>
     */
    #[Computed]
    public function people(): array
    {
        return app(DailyJournal::class)->people($this->date);
    }

    /**
     * One day back, or forward — never past today.
     */
    public function shift(int $days): void
    {
        $this->goTo($this->date->addDays($days <=> 0)->format('Y-m-d'));
    }

    public function goTo(string $day): void
    {
        $this->day = $day;
        $this->day = $this->isTodayDate() ? '' : $this->date->format('Y-m-d');
    }

    public function choosePerson(?int $person): void
    {
        $this->person = $this->person === $person ? null : $person;
    }

    public function chooseKind(string $kind): void
    {
        $this->kind = in_array($kind, [EntryKind::Charge->value, EntryKind::Payment->value], true) ? $kind : '';
    }

    /**
     * What a charge was made of, on one line: the first few, and how many more.
     *
     * @param  list<string>  $lines
     */
    public function linesText(array $lines): ?string
    {
        if ($lines === []) {
            return null;
        }

        $shown = implode('، ', array_slice($lines, 0, 4));

        return count($lines) > 4 ? __('accounts::module.statement.more_lines', ['lines' => $shown, 'count' => count($lines)]) : $shown;
    }

    public function time(CarbonImmutable $at): string
    {
        return local_time($at)->locale(app()->getLocale())->isoFormat('LT');
    }

    private function isTodayDate(): bool
    {
        unset($this->date, $this->isToday);

        return $this->isToday;
    }
}; ?>

<div class="d-grid gap-3">
    {{-- Which day: back, the date, forward — and back to today. --}}
    <div class="d-flex flex-wrap align-items-center gap-2 no-print">
        <div class="input-group w-auto">
            <button type="button" class="btn btn-outline-secondary" wire:click="shift(-1)" aria-label="{{ __('accounts::module.journal.previous') }}">
                <i class="bi bi-chevron-{{ is_rtl() ? 'right' : 'left' }}" aria-hidden="true"></i>
            </button>
            <label for="journal-day" class="visually-hidden">{{ __('accounts::module.journal.day') }}</label>
            <input
                id="journal-day"
                type="date"
                class="form-control num"
                dir="ltr"
                value="{{ $this->date->format('Y-m-d') }}"
                max="{{ $this->lastDay }}"
                wire:change="goTo($event.target.value)"
            >
            <button type="button" class="btn btn-outline-secondary" wire:click="shift(1)" @disabled($this->isToday) aria-label="{{ __('accounts::module.journal.next') }}">
                <i class="bi bi-chevron-{{ is_rtl() ? 'left' : 'right' }}" aria-hidden="true"></i>
            </button>
        </div>

        @unless ($this->isToday)
            <button type="button" class="btn btn-outline-primary" wire:click="goTo('')">
                <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>{{ __('accounts::module.journal.go_today') }}
            </button>
        @endunless

        <button type="button" class="btn btn-outline-secondary ms-auto" data-print>
            <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('accounts::module.journal.print') }}
        </button>
    </div>

    <h2 class="h4 mb-0">
        {{ $this->date->locale(app()->getLocale())->isoFormat('dddd D MMMM YYYY') }}
        @if ($this->isToday)
            <span class="badge rounded-pill text-bg-primary align-middle fs-6 ms-1">{{ __('accounts::module.journal.today') }}</span>
        @endif
    </h2>

    {{-- The day's figures, over what stands. --}}
    <div class="row g-3" role="group" aria-label="{{ __('accounts::module.journal.figures') }}">
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat icon="bag-plus" variant="danger" :label="__('accounts::module.journal.stat_taken')" :value="$this->totals->taken->format()" :caption="trans_choice('accounts::module.journal.entries_count', $this->totals->charges, ['count' => $this->totals->charges])" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat icon="cash-coin" variant="success" :label="__('accounts::module.journal.stat_collected')" :value="$this->totals->collected->format()" :caption="trans_choice('accounts::module.journal.entries_count', $this->totals->payments, ['count' => $this->totals->payments])" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat
                icon="arrow-down-up"
                variant="primary"
                :label="__('accounts::module.journal.stat_change')"
                :value="($this->totals->change()->isNegative() ? $this->totals->change()->negated() : $this->totals->change())->format()"
                :caption="$this->changeCaption()"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat icon="x-circle" variant="secondary" :label="__('accounts::module.journal.stat_voided')" :value="$this->totals->voided" :caption="__('accounts::module.journal.stat_voided_caption')" />
        </div>
    </div>

    {{-- Who took what: the drawer is counted against the cash column. --}}
    <x-admin::ui.card :title="__('accounts::module.journal.drawer_title')" icon="person-badge" flush>
        @if ($this->people === [])
            <p class="text-body-secondary small px-3 py-3 mb-0">{{ __('accounts::module.journal.nobody') }}</p>
        @else
            <x-admin::table.table :striped="false">
                <x-slot:head>
                    <x-admin::table.heading>{{ __('accounts::module.journal.column_person') }}</x-admin::table.heading>
                    <x-admin::table.heading align="end" class="d-none d-sm-table-cell">{{ __('accounts::module.journal.column_entries') }}</x-admin::table.heading>
                    <x-admin::table.heading align="end">{{ __('accounts::module.journal.column_taken') }}</x-admin::table.heading>
                    <x-admin::table.heading align="end">{{ __('accounts::module.journal.column_collected') }}</x-admin::table.heading>
                    <x-admin::table.heading align="end" class="no-print"><span class="visually-hidden">{{ __('accounts::module.journal.only_theirs') }}</span></x-admin::table.heading>
                </x-slot:head>

                @foreach ($this->people as $someone)
                    <tr wire:key="person-{{ $someone['id'] }}" @class(['table-active' => $person === $someone['id']])>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <x-admin::ui.avatar :name="$someone['name']" size="sm" />
                                <span class="fw-semibold" dir="auto">{{ $someone['name'] }}</span>
                            </div>
                        </td>
                        <td class="text-end num d-none d-sm-table-cell">{{ $someone['totals']->entries() }}</td>
                        <td class="text-end"><span class="num flow-debit flow-text" dir="ltr">{{ $someone['totals']->taken->toDisplayString() }}</span></td>
                        <td class="text-end"><span class="num fw-bold flow-credit flow-text" dir="ltr">{{ $someone['totals']->collected->toDisplayString() }}</span></td>
                        <td class="text-end no-print">
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="choosePerson({{ $someone['id'] }})" aria-pressed="{{ $person === $someone['id'] ? 'true' : 'false' }}" aria-label="{{ __('accounts::module.journal.only_theirs_named', ['name' => $someone['name']]) }}">
                                <i class="bi bi-funnel" aria-hidden="true"></i>
                                <span class="d-none d-md-inline">{{ $person === $someone['id'] ? __('accounts::module.journal.all_people') : __('accounts::module.journal.only_theirs') }}</span>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </x-admin::table.table>
        @endif
    </x-admin::ui.card>

    {{-- Every entry of the day, the latest first. --}}
    <x-admin::ui.card :title="__('accounts::module.journal.entries_title')" icon="journal-text" flush>
        <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary no-print" role="group" aria-label="{{ __('accounts::module.journal.filter_kind') }}">
            @foreach (['' => __('accounts::module.journal.all'), 'charge' => __('accounts::module.journal.charges'), 'payment' => __('accounts::module.journal.payments')] as $value => $label)
                <button type="button" class="btn chip" wire:click="chooseKind('{{ $value }}')" aria-pressed="{{ $kind === $value ? 'true' : 'false' }}" wire:key="kind-{{ $value ?: 'all' }}">
                    <i class="bi bi-check2 chip-tick" aria-hidden="true"></i>{{ $label }}
                </button>
            @endforeach
        </div>

        <div wire:loading.delay.class="is-refreshing" wire:target="shift,goTo,choosePerson,chooseKind">
            @if ($this->entries === [])
                <x-admin::ui.empty-state icon="journal" :title="__('accounts::module.journal.empty_title')" :description="__('accounts::module.journal.empty_description')" />
            @else
                <x-admin::table.table :striped="false" class="statement">
                    <x-slot:head>
                        <x-admin::table.heading>{{ __('accounts::module.journal.column_time') }}</x-admin::table.heading>
                        <x-admin::table.heading>{{ __('accounts::module.journal.column_customer') }}</x-admin::table.heading>
                        <x-admin::table.heading class="d-none d-md-table-cell">{{ __('accounts::module.journal.column_what') }}</x-admin::table.heading>
                        <x-admin::table.heading align="end">{{ __('accounts::module.journal.column_amount') }}</x-admin::table.heading>
                        <x-admin::table.heading class="d-none d-lg-table-cell">{{ __('accounts::module.journal.column_by') }}</x-admin::table.heading>
                    </x-slot:head>

                    @foreach ($this->entries as $entry)
                        <tr
                            wire:key="journal-{{ $entry->id }}"
                            @class([
                                'statement-voided' => $entry->voided,
                                'statement-reversal' => $entry->kind->value === 'reversal',
                                'flow-debit' => $entry->stands() && $entry->debit,
                                'flow-credit' => $entry->stands() && ! $entry->debit,
                            ])
                        >
                            <td class="num text-nowrap" dir="ltr">{{ $this->time($entry->at) }}</td>
                            <td>
                                <a href="{{ route('admin.accounts.show', $entry->customerId) }}" class="fw-semibold link-body-emphasis link-underline-opacity-25 link-underline-opacity-100-hover" dir="auto">{{ $entry->customer }}</a>
                                <div class="small text-body-secondary d-md-none">{{ $entry->kind->label() }}</div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="fw-semibold statement-title">{{ $entry->kind->label() }}</span>
                                @if ($entry->voided)
                                    <span class="badge bg-body-tertiary text-body-emphasis border ms-1">{{ __('accounts::module.journal.voided') }}</span>
                                @endif
                                @if ($this->linesText($entry->lines) ?? $entry->note)
                                    <div class="small text-body-secondary text-truncate statement-detail" dir="auto">{{ $this->linesText($entry->lines) ?? $entry->note }}</div>
                                @endif
                            </td>
                            <td class="text-end"><span class="num statement-amount" dir="ltr">{{ $entry->amount->toDisplayString() }}</span></td>
                            <td class="d-none d-lg-table-cell small text-body-secondary" dir="auto">{{ $entry->by }}</td>
                        </tr>
                    @endforeach
                </x-admin::table.table>

                @if ($this->limited)
                    <p class="small text-body-secondary px-3 py-2 mb-0">{{ __('accounts::module.journal.limited', ['count' => count($this->entries)]) }}</p>
                @endif
            @endif
        </div>
    </x-admin::ui.card>
</div>
