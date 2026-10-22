<?php

use App\Foundation\Area\Area;
use App\Foundation\Money\Money;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Authorization\AccountPermission;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Queries\AccountStatement;
use App\Modules\Accounts\Domain\Queries\StatementKind;
use App\Modules\Accounts\Domain\Queries\StatementPeriod;
use App\Modules\Accounts\Domain\Queries\StatementTotals;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Contracts\Enums\CustomerPermission;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Http\Request;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One customer's account: who they are, where the account stands and how it
 * got there, and the statement — newest first, a business day at a time,
 * every line with the balance either side of it.
 *
 * Read in a fixed handful of queries, a page of entries at a time: each entry
 * carries the balance after it, so nothing is summed from the ledger's start.
 * Recording, the details of an entry and its corrections, the adjustments,
 * and the customer's own details are each a component of their own, opened
 * from here; every one asks for its permission again.
 */
new #[Layout('admin::layout.app')] class extends Component {
    /** How many entries a page of the statement shows. */
    private const int PAGE = 50;

    #[Locked]
    public int $customerId;

    #[Url(except: '30')]
    public string $period = '30';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $kind = '';

    public int $limit = self::PAGE;

    public function mount(int $customer, Request $request): void
    {
        abort_unless(CustomerAccount::query()->where('customer_id', $customer)->exists(), 404);

        $this->customerId = $customer;

        // Reached from the quick search with «+» or «−»: the drawer opens at once.
        $entry = $request->query('entry');

        if (in_array($entry, ['charge', 'payment'], true) && $this->canPost) {
            $this->dispatch('open-entry', customerId: $customer, kind: $entry);
        }
    }

    public function rendering(View $view): void
    {
        $view->layoutData(['title' => $this->customer->name]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['period', 'from', 'to', 'kind'], true)) {
            $this->limit = self::PAGE;
        }
    }

    public function showOlder(): void
    {
        $this->limit += self::PAGE;
    }

    /**
     * Something on this account changed from a drawer or a dialog: read it
     * all again.
     */
    #[On('entry-posted')]
    #[On('account-changed')]
    public function refreshAccount(): void
    {
        unset($this->account, $this->statement, $this->periodTotals, $this->lifetime, $this->lastPayment, $this->customer, $this->groups);
    }

    #[Computed]
    public function customer(): CustomerData
    {
        return app(CustomerRepositoryInterface::class)->find($this->customerId) ?? abort(404);
    }

    #[Computed]
    public function account(): CustomerAccount
    {
        return CustomerAccount::query()->where('customer_id', $this->customerId)->firstOrFail();
    }

    /**
     * @return list<CustomerGroupData>
     */
    #[Computed]
    public function groups(): array
    {
        return app(CustomerGroupRepositoryInterface::class)->getForCustomers([$this->customerId])->get($this->customerId) ?? [];
    }

    /**
     * The account's figures over its whole life.
     */
    #[Computed]
    public function lifetime(): StatementTotals
    {
        return app(AccountStatement::class)->totals($this->account);
    }

    /**
     * The latest payment still standing: «آخر دفعة 200.00 اليوم».
     */
    #[Computed]
    public function lastPayment(): ?AccountTransaction
    {
        return AccountTransaction::query()
            ->where('account_id', $this->account->getKey())
            ->where('kind', EntryKind::Payment)
            ->whereDoesntHave('reversal')
            ->orderByDesc('id')
            ->first();
    }

    #[Computed]
    public function periodChoice(): StatementPeriod
    {
        return StatementPeriod::tryFrom($this->period) ?? StatementPeriod::Last30Days;
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    #[Computed]
    public function bounds(): array
    {
        return $this->periodChoice->bounds(app(LocalClock::class), $this->from ?: null, $this->to ?: null);
    }

    #[Computed]
    public function periodTotals(): StatementTotals
    {
        return app(AccountStatement::class)->totals($this->account, $this->bounds);
    }

    /**
     * @return array{days: list<\App\Modules\Accounts\Domain\Queries\StatementDay>, older: bool}
     */
    #[Computed]
    public function statement(): array
    {
        return app(AccountStatement::class)->days($this->account, $this->bounds, StatementKind::tryFrom($this->kind), $this->limit);
    }

    /**
     * @return array{period: array<string, string>, kind: array<string, string>}
     */
    #[Computed]
    public function choices(): array
    {
        return [
            'period' => collect(StatementPeriod::cases())->mapWithKeys(fn (StatementPeriod $period): array => [$period->value => $period->label()])->all(),
            'kind' => collect(StatementKind::cases())->mapWithKeys(fn (StatementKind $kind): array => [$kind->value => $kind->label()])->all(),
        ];
    }

    #[Computed]
    public function canPost(): bool
    {
        return Area::Admin->user()?->can(AccountPermission::Post->value) === true;
    }

    #[Computed]
    public function canAdjust(): bool
    {
        return Area::Admin->user()?->can(AccountPermission::Adjust->value) === true;
    }

    #[Computed]
    public function canManageCustomer(): bool
    {
        return Area::Admin->user()?->can(CustomerPermission::Manage->value) === true;
    }

    /**
     * A business day as a statement heads it: «اليوم · الجمعة 2 أكتوبر».
     */
    public function dayLabel(CarbonImmutable $date): string
    {
        $clock = app(LocalClock::class);
        $today = $clock->today();
        $named = $date->locale(app()->getLocale())->isoFormat($date->isSameYear($today) ? 'dddd D MMMM' : 'dddd D MMMM YYYY');

        return match (true) {
            $date->isSameDay($today) => __('accounts::module.statement.today', ['date' => $named]),
            $date->isSameDay($today->subDay()) => __('accounts::module.statement.yesterday', ['date' => $named]),
            default => $named,
        };
    }

    /**
     * A moment as a statement line shows it: the time on the local wall.
     */
    public function time(CarbonInterface $moment): string
    {
        return local_time($moment)->locale(app()->getLocale())->isoFormat('LT');
    }

    /**
     * A moment as a sentence reads it: «28 سبتمبر».
     */
    public function date(CarbonInterface $moment): string
    {
        return local_time($moment)->locale(app()->getLocale())->isoFormat('D MMMM');
    }

    /**
     * How long the balance has been owed, and the last payment: the story
     * under the figure.
     */
    public function story(): string
    {
        $parts = [];
        $account = $this->account;

        if ($account->outstanding->isPositive() && $account->in_debt_since !== null) {
            $parts[] = __('accounts::module.account.owed_since', [
                'days' => (int) $account->in_debt_since->diffInDays(CarbonImmutable::now()),
                'date' => $this->date($account->in_debt_since),
            ]);
        }

        if ($this->lastPayment !== null) {
            $parts[] = __('accounts::module.account.last_payment', [
                'amount' => $this->lastPayment->amount->toDisplayString(),
                'when' => local_time($this->lastPayment->created_at)->locale(app()->getLocale())->diffForHumans(['options' => CarbonInterface::JUST_NOW]),
            ]);
        } elseif ($account->last_entry_at !== null) {
            $parts[] = __('accounts::module.account.never_paid');
        }

        return implode(' · ', $parts);
    }

    /**
     * What a line was made of, on one line: the first few, and how many.
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

    /**
     * A balance in the counter's words.
     */
    public function inWords(Money $balance): string
    {
        return match (true) {
            $balance->isPositive() => __('accounts::module.balance.owes').' '.$balance->toDisplayString(),
            $balance->isNegative() => __('accounts::module.balance.in_credit').' '.$balance->negated()->toDisplayString(),
            default => __('accounts::module.balance.settled'),
        };
    }
}; ?>

<div class="row g-3">
    {{-- Who this is: the first guard against recording on the wrong person. --}}
    <div class="col-12">
        <x-admin::ui.card>
            <div class="d-flex flex-wrap align-items-start gap-3">
                <x-admin::ui.avatar :name="$this->customer->name" size="lg" />

                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap gap-3 small text-body-secondary mb-2">
                        @if ($this->customer->phone)
                            <span><i class="bi bi-telephone me-1" aria-hidden="true"></i><span class="num" dir="ltr">{{ $this->customer->phone }}</span></span>
                        @else
                            <span>{{ __('accounts::module.list.no_phone') }}</span>
                        @endif
                        @if ($this->customer->address)
                            <span dir="auto"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $this->customer->address }}</span>
                        @endif
                        <span><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ __('accounts::module.account.customer_since', ['date' => $this->date($this->account->created_at)]) }}</span>
                    </div>

                    <div class="d-flex flex-wrap gap-1 align-items-center">
                        @foreach ($this->groups as $member)
                            <x-accounts::admin.group-chip :group="$member" wire:key="account-group-{{ $member->id }}" />
                        @endforeach
                    </div>

                    @if ($this->customer->notes)
                        <p class="small mt-2 mb-0" dir="auto">{{ $this->customer->notes }}</p>
                    @endif
                </div>

                @if ($this->canManageCustomer)
                    <button type="button" class="btn btn-outline-secondary no-print" wire:click="$dispatch('edit-customer', { customerId: {{ $customerId }} })">
                        <i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('accounts::module.account.edit_customer') }}
                    </button>
                @endif
            </div>
        </x-admin::ui.card>
    </div>

    {{-- Where it stands, and the two things done most. --}}
    <div class="col-12 col-lg-4">
        <x-admin::ui.card class="account-summary">
            <h2 class="h6 text-body-secondary mb-1">{{ __('accounts::module.account.balance_now') }}</h2>
            <div class="mb-1"><x-accounts::admin.balance :amount="$this->account->outstanding" size="lg" /></div>
            @if ($story = $this->story())
                <p class="small text-body-secondary mb-3">{{ $story }}</p>
            @endif

            @if ($this->canPost)
                <div class="d-flex gap-2 mb-3 no-print">
                    <button
                        type="button"
                        class="btn btn-lg btn-flow flow-debit flex-grow-1"
                        wire:click="$dispatch('open-entry', { customerId: {{ $customerId }}, kind: 'charge' })"
                        data-shortcut-key="+"
                        aria-keyshortcuts="+"
                    >
                        <i class="bi bi-bag-plus me-1" aria-hidden="true"></i>{{ __('accounts::module.list.charge') }}
                        <kbd class="ms-1 d-none d-md-inline kbd-hint" aria-hidden="true">+</kbd>
                    </button>
                    <button
                        type="button"
                        class="btn btn-lg btn-flow flow-credit flex-grow-1"
                        wire:click="$dispatch('open-entry', { customerId: {{ $customerId }}, kind: 'payment' })"
                        data-shortcut-key="-"
                        aria-keyshortcuts="-"
                    >
                        <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>{{ __('accounts::module.list.payment') }}
                        <kbd class="ms-1 d-none d-md-inline kbd-hint" aria-hidden="true">−</kbd>
                    </button>
                </div>
            @endif

            <dl class="account-facts mb-0">
                <div><dt>{{ __('accounts::module.account.taken') }}</dt><dd class="num" dir="ltr">{{ $this->lifetime->taken->toDisplayString() }}</dd></div>
                <div><dt>{{ __('accounts::module.account.paid') }}</dt><dd class="num" dir="ltr">{{ $this->lifetime->paid->toDisplayString() }}</dd></div>
                @unless ($this->lifetime->openingBalances->isZero())
                    <div><dt>{{ __('accounts::module.account.opening_balances') }}</dt><dd>{{ $this->inWords($this->lifetime->openingBalances) }}</dd></div>
                @endunless
                @unless ($this->lifetime->discounts->isZero())
                    <div><dt>{{ __('accounts::module.account.discounts') }}</dt><dd class="num" dir="ltr">{{ $this->lifetime->discounts->toDisplayString() }}</dd></div>
                @endunless
                <div><dt>{{ __('accounts::module.account.entries') }}</dt><dd>{{ trans_choice('accounts::module.account.entries_count', $this->lifetime->entries, ['count' => $this->lifetime->entries, 'voided' => $this->lifetime->voided]) }}</dd></div>
                <div>
                    <dt>{{ __('accounts::module.account.credit_limit') }}</dt>
                    <dd>
                        @if ($this->account->credit_limit->isZero())
                            {{ __('accounts::module.account.no_limit') }}
                        @else
                            <span class="num" dir="ltr">{{ $this->account->credit_limit->toDisplayString() }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
            <p class="small text-body-secondary mt-2 mb-0">{{ __('accounts::module.account.totals_note') }}</p>

            <div class="d-flex flex-wrap gap-2 mt-3 no-print">
                @if ($this->canAdjust)
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-sliders me-1" aria-hidden="true"></i>{{ __('accounts::module.account.adjust') }}
                        </button>
                        <ul class="dropdown-menu" role="list">
                            <li><button type="button" class="dropdown-item" wire:click="$dispatch('adjust-account', { customerId: {{ $customerId }}, mode: 'opening' })">{{ __('accounts::module.adjust.opening_title') }}</button></li>
                            <li><button type="button" class="dropdown-item" wire:click="$dispatch('adjust-account', { customerId: {{ $customerId }}, mode: 'discount' })">{{ __('accounts::module.adjust.discount_title') }}</button></li>
                            <li><button type="button" class="dropdown-item" wire:click="$dispatch('adjust-account', { customerId: {{ $customerId }}, mode: 'limit' })">{{ __('accounts::module.adjust.limit_title') }}</button></li>
                        </ul>
                    </div>
                @endif
                <button type="button" class="btn btn-sm btn-outline-secondary" data-print>
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('accounts::module.statement.print') }}
                </button>
            </div>
        </x-admin::ui.card>
    </div>

    {{-- The statement: like the paper book, a day at a time. --}}
    <div class="col-12 col-lg-8">
        <x-admin::ui.card :title="__('accounts::module.statement.title')" icon="journal-text" flush>
            {{-- What a printed statement says at its head. --}}
            <div class="print-only px-3 pt-3">
                <p class="fw-bold mb-0">{{ __('shell.brand.name') }} — {{ __('accounts::module.statement.title') }}</p>
                <p class="mb-0" dir="auto">{{ $this->customer->name }} @if ($this->customer->phone)· <span dir="ltr">{{ $this->customer->phone }}</span>@endif</p>
                <p class="small mb-0">{{ $this->periodChoice->label() }}</p>
            </div>

            <div class="row g-2 align-items-end px-3 pt-3 no-print" role="group" aria-label="{{ __('accounts::module.statement.filters') }}">
                <div class="col-6 col-md-4">
                    <label for="statement-period" class="form-label small mb-1">{{ __('accounts::module.statement.period_label') }}</label>
                    <select id="statement-period" class="form-select form-select-sm" wire:model.live="period">
                        @foreach ($this->choices['period'] as $value => $label)
                            <option value="{{ $value }}" wire:key="period-{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label for="statement-kind" class="form-label small mb-1">{{ __('accounts::module.statement.kind_label') }}</label>
                    <select id="statement-kind" class="form-select form-select-sm" wire:model.live="kind">
                        <option value="">{{ __('accounts::module.list.any') }}</option>
                        @foreach ($this->choices['kind'] as $value => $label)
                            <option value="{{ $value }}" wire:key="kind-{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($this->periodChoice->value === 'custom')
                    <div class="col-6 col-md-2">
                        <label for="statement-from" class="form-label small mb-1">{{ __('accounts::module.statement.from') }}</label>
                        <input id="statement-from" type="date" class="form-control form-control-sm" wire:model.live="from" dir="ltr">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="statement-to" class="form-label small mb-1">{{ __('accounts::module.statement.to') }}</label>
                        <input id="statement-to" type="date" class="form-control form-control-sm" wire:model.live="to" dir="ltr">
                    </div>
                @endif
            </div>

            {{-- The period's figures, as a printed statement heads them. --}}
            <dl class="statement-strip small px-3 py-2 my-3 border-top border-bottom bg-body-tertiary" aria-label="{{ __('accounts::module.statement.figures') }}">
                <div><dt>{{ __('accounts::module.statement.opening') }}</dt><dd>{{ $this->inWords($this->periodTotals->opening) }}</dd></div>
                @unless ($this->periodTotals->openingBalances->isZero())
                    <div><dt>{{ __('accounts::module.account.opening_balances') }}</dt><dd>{{ $this->inWords($this->periodTotals->openingBalances) }}</dd></div>
                @endunless
                <div><dt>{{ __('accounts::module.list.charge') }}</dt><dd><span class="num" dir="ltr">{{ $this->periodTotals->taken->toDisplayString() }}</span> ({{ $this->periodTotals->charges }})</dd></div>
                <div><dt>{{ __('accounts::module.list.payment') }}</dt><dd><span class="num" dir="ltr">{{ $this->periodTotals->paid->toDisplayString() }}</span> ({{ $this->periodTotals->payments }})</dd></div>
                @unless ($this->periodTotals->discounts->isZero())
                    <div><dt>{{ __('accounts::module.account.discounts') }}</dt><dd class="num" dir="ltr">{{ $this->periodTotals->discounts->toDisplayString() }}</dd></div>
                @endunless
                <div><dt>{{ __('accounts::module.statement.closing') }}</dt><dd class="fw-semibold">{{ $this->inWords($this->periodTotals->closing) }}</dd></div>
            </dl>

            <div wire:loading.delay.class="is-refreshing" wire:target="period,kind,from,to,showOlder">
                @if ($this->statement['days'] === [])
                    <x-admin::ui.empty-state icon="journal" :title="__('accounts::module.statement.empty')">
                        @if ($this->canPost && $this->lifetime->entries === 0)
                            <div class="d-flex flex-wrap justify-content-center gap-2 no-print">
                                <button type="button" class="btn btn-flow flow-debit" wire:click="$dispatch('open-entry', { customerId: {{ $customerId }}, kind: 'charge' })">{{ __('accounts::module.list.charge') }}</button>
                                <button type="button" class="btn btn-flow flow-credit" wire:click="$dispatch('open-entry', { customerId: {{ $customerId }}, kind: 'payment' })">{{ __('accounts::module.list.payment') }}</button>
                                @if ($this->canAdjust)
                                    <button type="button" class="btn btn-outline-secondary" wire:click="$dispatch('adjust-account', { customerId: {{ $customerId }}, mode: 'opening' })">{{ __('accounts::module.adjust.opening_title') }}</button>
                                @endif
                            </div>
                        @endif
                    </x-admin::ui.empty-state>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 statement" id="statement-rows" data-row-list>
                            <thead>
                                <tr>
                                    <x-admin::table.heading>{{ __('accounts::module.statement.entry') }}</x-admin::table.heading>
                                    <x-admin::table.heading align="end" class="d-none d-sm-table-cell">{{ __('accounts::module.statement.amount') }}</x-admin::table.heading>
                                    <x-admin::table.heading align="end">{{ __('accounts::module.statement.balance_after') }}</x-admin::table.heading>
                                </tr>
                            </thead>

                            @foreach ($this->statement['days'] as $day)
                                <tbody wire:key="day-{{ $day->date->format('Y-m-d') }}">
                                    <tr class="statement-day">
                                        <th scope="rowgroup" colspan="3">
                                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                                <span>{{ $this->dayLabel($day->date) }}</span>
                                                @if ($day->closing)
                                                    <span class="fw-normal">{{ __('accounts::module.statement.day_end', ['balance' => $this->inWords($day->closing)]) }}</span>
                                                @endif
                                            </div>
                                        </th>
                                    </tr>

                                    @foreach ($day->entries as $entry)
                                        @include('accounts::admin.statement-row', [
                                            'entry' => $entry,
                                            'text' => $this->linesText($entry->lines),
                                            'time' => $this->time($entry->at),
                                            'was' => $this->inWords($entry->before),
                                        ])
                                    @endforeach
                                </tbody>
                            @endforeach
                        </table>
                    </div>

                    @if ($this->statement['older'])
                        <div class="text-center p-2 border-top no-print">
                            <button type="button" class="btn btn-link" wire:click="showOlder" wire:loading.attr="disabled">
                                {{ __('accounts::module.statement.show_older') }}
                            </button>
                        </div>
                    @endif
                @endif
            </div>
        </x-admin::ui.card>
    </div>

    {{-- Keyed, so the page redrawing around them never mounts them again.
         The drawer that records an entry is on every screen already, for
         whoever may post — see AccountsServiceProvider::adminOverlays(). --}}
    <livewire:accounts::admin.entry-details wire:key="entry-details" />
    @if ($this->canAdjust)
        <livewire:accounts::admin.account-adjustments wire:key="account-adjustments" />
    @endif
    @if ($this->canManageCustomer)
        <livewire:accounts::admin.customers.edit-customer wire:key="edit-customer" />
    @endif
</div>
