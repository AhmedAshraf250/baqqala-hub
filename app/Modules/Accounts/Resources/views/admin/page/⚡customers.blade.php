<?php

use App\Foundation\Area\Area;
use App\Modules\Accounts\Authorization\AccountPermission;
use App\Modules\Accounts\Domain\Queries\AccountListSort;
use App\Modules\Accounts\Domain\Queries\ActivityFilter;
use App\Modules\Accounts\Domain\Queries\BalanceFilter;
use App\Modules\Accounts\Domain\Queries\CustomerAccountFilter;
use App\Modules\Accounts\Domain\Queries\CustomerAccountList;
use App\Modules\Accounts\Domain\Queries\CustomerAccountTotals;
use App\Modules\Accounts\Domain\Queries\LastEntrySince;
use App\Modules\Accounts\Domain\Services\AccountSettings;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Contracts\Enums\CustomerPermission;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The counter's workspace: every customer, where each account stands, and
 * the way into recording for any of them without opening their page.
 *
 * Every filter is a property in the page's address, so a filtered list is a
 * link. The list itself is CustomerAccountList's: Customers answers who
 * matches, the accounts are filtered, sorted, and paged on their own table.
 * Each action asks for its permission again — a Livewire call reaches the
 * component directly, whatever the page showed.
 */
new #[Layout('admin::layout.app')] class extends Component {
    use WithPagination;

    /** The filters this list reads from its address. */
    private const array FILTERS = ['q', 'group', 'balance', 'activity', 'since', 'sort'];

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: null)]
    public ?int $group = null;

    #[Url(except: '')]
    public string $balance = '';

    #[Url(except: '')]
    public string $activity = '';

    #[Url(except: '')]
    public string $since = '';

    #[Url(except: 'recent')]
    public string $sort = 'recent';

    /**
     * Customers picked for adding to a group, by id.
     *
     * @var list<int>
     */
    public array $selected = [];

    public ?int $bulkGroup = null;

    /** What the last action on this screen did, said once. */
    public ?string $notice = null;

    private ?CustomerAccountList $list = null;

    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('accounts::module.navigation.customers')]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    #[Computed]
    public function filter(): CustomerAccountFilter
    {
        return CustomerAccountFilter::fromScreen($this->q, $this->group, $this->balance, $this->activity, $this->since, $this->sort);
    }

    /**
     * @return LengthAwarePaginator<int, \App\Modules\Accounts\Domain\Queries\CustomerAccountRow>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return $this->list()->page($this->filter);
    }

    #[Computed]
    public function totals(): CustomerAccountTotals
    {
        return $this->list()->totals($this->filter);
    }

    #[Computed]
    public function everyone(): CustomerAccountTotals
    {
        return $this->filter->narrows() ? $this->list()->totals(new CustomerAccountFilter) : $this->totals;
    }

    /**
     * @return Collection<int, CustomerGroupData>
     */
    #[Computed]
    public function groups(): Collection
    {
        return app(CustomerGroupRepositoryInterface::class)->getAll();
    }

    /**
     * @return array{balance: array<string, string>, activity: array<string, string>, since: array<string, string>, sort: array<string, string>}
     */
    #[Computed]
    public function choices(): array
    {
        $labelled = static fn (array $cases): array => collect($cases)->mapWithKeys(fn ($case): array => [$case->value => $case->label()])->all();

        return [
            'balance' => $labelled(BalanceFilter::cases()),
            'activity' => $labelled(ActivityFilter::cases()),
            'since' => $labelled(LastEntrySince::cases()),
            'sort' => $labelled(AccountListSort::cases()),
        ];
    }

    /**
     * How many of the filters that narrow by where an account stands are on.
     */
    #[Computed]
    public function activeFilters(): int
    {
        return count(array_filter([$this->balance, $this->activity, $this->since], static fn (string $value): bool => $value !== ''));
    }

    #[Computed]
    public function canCreate(): bool
    {
        return Area::Admin->user()?->can(CustomerPermission::Create->value) === true;
    }

    #[Computed]
    public function canManage(): bool
    {
        return Area::Admin->user()?->can(CustomerPermission::Manage->value) === true;
    }

    #[Computed]
    public function canPost(): bool
    {
        return Area::Admin->user()?->can(AccountPermission::Post->value) === true;
    }

    public function selectGroup(?int $groupId): void
    {
        $this->group = $this->group === $groupId ? null : $groupId;
        $this->updated('group');
    }

    /**
     * A tile pressed: narrow the list to the balances it counts, or let go
     * when it already is.
     */
    public function quickBalance(string $balance): void
    {
        $this->balance = $this->balance === $balance ? '' : $balance;
        $this->activity = '';
        $this->updated('balance');
    }

    public function quickLate(): void
    {
        $this->activity = $this->activity === 'late' ? '' : 'late';
        $this->balance = '';
        $this->updated('activity');
    }

    #[Computed]
    public function lateAfterDays(): int
    {
        return app(AccountSettings::class)->lateAfterDays();
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'group', 'balance', 'activity', 'since');
        $this->updated('q');
    }

    /**
     * Put the picked customers in one group.
     */
    public function addSelectedToGroup(CustomerGroupRepositoryInterface $groups, CustomerRepositoryInterface $customers): void
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator?->can(CustomerPermission::Manage->value) === true, 403);

        $group = $this->bulkGroup === null ? null : $groups->find($this->bulkGroup);

        if ($group === null || $group->isArchived()) {
            $this->addError('bulkGroup', __('accounts::module.list.choose_group'));

            return;
        }

        $ids = array_values($customers->findMany(array_map(intval(...), $this->selected))->keys()->all());

        $groups->saveGroupMembers((int) $group->id, $ids, add: true, changedBy: (int) $administrator->getKey());

        $this->notice = trans_choice('accounts::module.list.added_to_group', count($ids), ['count' => count($ids), 'group' => $group->name]);
        $this->reset('selected', 'bulkGroup');
        unset($this->groups);
    }

    public function clearSelection(): void
    {
        $this->reset('selected', 'bulkGroup');
    }

    /**
     * A customer was just added: show them.
     */
    #[On('customer-added')]
    public function showAdded(string $term, ?string $notice = null): void
    {
        $this->reset('group', 'balance', 'activity', 'since');
        $this->q = $term;
        $this->notice = $notice;
        $this->updated('q');
    }

    /**
     * Something this list shows changed elsewhere on the page — an entry was
     * posted, a group renamed — and the next render reads it again.
     */
    #[On('entry-posted')]
    #[On('groups-changed')]
    public function refreshList(): void
    {
        unset($this->groups);

        if ($this->group !== null && ! $this->groups->has($this->group)) {
            $this->group = null;
        }
    }

    /**
     * When something happened, the way the counter says it: minutes ago,
     * a time today, yesterday, a weekday this week, a date before that.
     */
    public function when(CarbonInterface $moment): string
    {
        $locale = app()->getLocale();
        $local = local_time($moment)->locale($locale);
        $now = local_time(CarbonImmutable::now())->locale($locale);

        return match (true) {
            $local->diffInMinutes($now) < 60 => $local->diffForHumans(['options' => CarbonInterface::JUST_NOW]),
            $local->isSameDay($now) => $local->isoFormat('LT'),
            $local->isSameDay($now->subDay()) => __('accounts::module.list.yesterday'),
            $local->greaterThan($now->subDays(6)->startOfDay()) => $local->isoFormat('dddd'),
            $local->isSameYear($now) => $local->isoFormat('D MMMM'),
            default => $local->isoFormat('D MMMM YYYY'),
        };
    }

    private function list(): CustomerAccountList
    {
        return $this->list ??= app(CustomerAccountList::class);
    }
}; ?>

<div class="d-grid gap-3">
    {{-- Find someone: the biggest thing on the screen, with «new customer»
         beside it — on a phone, as its icon. --}}
    <div class="d-flex gap-2 align-items-stretch">
        <div class="flex-grow-1 min-w-0">
            <label for="customer-search" class="visually-hidden">{{ __('accounts::module.list.search') }}</label>
            <div class="input-group input-group-lg">
                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input
                    id="customer-search"
                    type="search"
                    class="form-control"
                    wire:model.live.debounce.200ms="q"
                    placeholder="{{ __('accounts::module.list.search_placeholder') }}"
                    autocomplete="off"
                    dir="auto"
                    data-shortcut-focus="Slash"
                    data-row-list-input="customer-rows"
                    aria-keyshortcuts="/"
                >
                <span class="input-group-text d-none d-md-flex kbd-hint" aria-hidden="true"><kbd>/</kbd></span>
            </div>
        </div>

        @if ($this->canCreate)
            <button
                type="button"
                class="btn btn-primary btn-lg d-inline-flex align-items-center gap-2"
                data-bs-toggle="modal"
                data-bs-target="#new-customer"
                data-shortcut-click="KeyN"
                aria-keyshortcuts="N"
                aria-label="{{ __('accounts::module.list.new_customer') }}"
            >
                <i class="bi bi-person-plus" aria-hidden="true"></i>
                <span class="d-none d-sm-inline text-nowrap">{{ __('accounts::module.list.new_customer') }}</span>
                <kbd class="d-none d-md-inline kbd-hint" aria-hidden="true">N</kbd>
            </button>
        @endif
    </div>

    {{-- The shop at a glance, each figure a press away from the customers it counts. --}}
    <div class="row g-3" role="group" aria-label="{{ __('accounts::module.list.overview') }}">
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat
                icon="arrow-up-right-circle"
                variant="danger"
                :label="__('accounts::module.list.stat_owing')"
                :value="$this->everyone->owed->format()"
                :caption="trans_choice('accounts::module.list.stat_customers', $this->everyone->owing, ['count' => $this->everyone->owing])"
                wire:click="quickBalance('owes')"
                :pressed="$balance === 'owes'"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat
                icon="piggy-bank"
                variant="success"
                :label="__('accounts::module.list.stat_credit')"
                :value="$this->everyone->heldForThem->format()"
                :caption="trans_choice('accounts::module.list.stat_customers', $this->everyone->inCredit, ['count' => $this->everyone->inCredit])"
                wire:click="quickBalance('credit')"
                :pressed="$balance === 'credit'"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat
                icon="check2-circle"
                variant="secondary"
                :label="__('accounts::module.list.stat_settled')"
                :value="$this->everyone->settled"
                :caption="__('accounts::module.list.stat_settled_caption')"
                wire:click="quickBalance('settled')"
                :pressed="$balance === 'settled'"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat
                icon="hourglass-split"
                variant="warning"
                :label="__('accounts::module.list.stat_late')"
                :value="$this->everyone->late"
                :caption="trans_choice('accounts::module.list.stat_late_caption', $this->lateAfterDays, ['days' => $this->lateAfterDays])"
                wire:click="quickLate"
                :pressed="$activity === 'late'"
            />
        </div>
    </div>

    {{-- What shapes the list: the owner's groups, one press each, and where
         accounts stand — together in one panel above it. --}}
    <div class="filter-bar">
        <div class="filter-chips d-flex flex-wrap gap-2 align-items-center" role="group" aria-label="{{ __('accounts::module.list.groups') }}">
            <button type="button" class="btn chip" wire:click="selectGroup(null)" aria-pressed="{{ $group === null ? 'true' : 'false' }}">
                <i class="bi bi-check2 chip-tick" aria-hidden="true"></i>
                {{ __('accounts::module.list.all') }}
                <span class="chip-count num" dir="ltr">{{ $this->everyone->accounts }}</span>
            </button>

            @foreach ($this->groups as $chip)
                <button
                    type="button"
                    wire:key="group-chip-{{ $chip->id }}"
                    class="btn chip tone-{{ $chip->color->value }}"
                    wire:click="selectGroup({{ $chip->id }})"
                    aria-pressed="{{ $group === $chip->id ? 'true' : 'false' }}"
                    dir="auto"
                >
                    <i class="bi bi-check2 chip-tick" aria-hidden="true"></i>
                    <span class="chip-dot" aria-hidden="true"></span>
                    @if ($chip->icon)
                        <i class="bi bi-{{ $chip->icon }}" aria-hidden="true"></i>
                    @endif
                    {{ $chip->name }}
                    <span class="chip-count num" dir="ltr">{{ $chip->memberCount }}</span>
                </button>
            @endforeach

            @if ($this->canManage)
                <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="modal" data-bs-target="#customer-groups">
                    <i class="bi bi-sliders me-1" aria-hidden="true"></i>
                    {{ __('accounts::module.list.manage_groups') }}
                </button>
            @endif
        </div>

        <hr class="filter-bar-rule d-none d-md-block">

        <button
            type="button"
            class="btn btn-sm btn-outline-secondary filter-toggle d-md-none"
            data-bs-toggle="collapse"
            data-bs-target="#customer-filters"
            aria-controls="customer-filters"
            aria-expanded="{{ $this->activeFilters > 0 ? 'true' : 'false' }}"
        >
            <i class="bi bi-funnel" aria-hidden="true"></i>
            {{ __('accounts::module.list.filters') }}
            @if ($this->activeFilters > 0)
                <span class="badge rounded-pill text-bg-primary num">{{ $this->activeFilters }}</span>
            @endif
            <i class="bi bi-chevron-down ms-auto" aria-hidden="true"></i>
        </button>

        <div @class(['collapse d-md-block', 'show' => $this->activeFilters > 0]) id="customer-filters" wire:ignore.self>
            <div class="row g-2 align-items-end" role="group" aria-label="{{ __('accounts::module.list.filters') }}">
                <div class="col-6 col-md-3 col-xl-2">
                    <label for="filter-balance" class="form-label small mb-1">{{ __('accounts::module.list.filter_balance') }}</label>
                    <select id="filter-balance" @class(['form-select form-select-sm', 'is-filtering' => $balance !== '']) wire:model.live="balance">
                        <option value="">{{ __('accounts::module.list.any') }}</option>
                        @foreach ($this->choices['balance'] as $value => $label)
                            <option value="{{ $value }}" wire:key="balance-{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                    <label for="filter-activity" class="form-label small mb-1">{{ __('accounts::module.list.filter_activity') }}</label>
                    <select id="filter-activity" @class(['form-select form-select-sm', 'is-filtering' => $activity !== '']) wire:model.live="activity">
                        <option value="">{{ __('accounts::module.list.any') }}</option>
                        @foreach ($this->choices['activity'] as $value => $label)
                            <option value="{{ $value }}" wire:key="activity-{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-xl-2">
                    <label for="filter-since" class="form-label small mb-1">{{ __('accounts::module.list.filter_since') }}</label>
                    <select id="filter-since" @class(['form-select form-select-sm', 'is-filtering' => $since !== '']) wire:model.live="since">
                        <option value="">{{ __('accounts::module.list.any_time') }}</option>
                        @foreach ($this->choices['since'] as $value => $label)
                            <option value="{{ $value }}" wire:key="since-{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($this->filter->narrows())
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none" wire:click="clearFilters">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ __('accounts::module.list.clear_filters') }}
                        </button>
                    </div>
                @endif

                <div class="col-6 col-md-3 col-xl-2 ms-xl-auto">
                    <label for="filter-sort" class="form-label small mb-1">{{ __('accounts::module.list.sort_by') }}</label>
                    <select id="filter-sort" class="form-select form-select-sm" wire:model.live="sort">
                        @foreach ($this->choices['sort'] as $value => $label)
                            <option value="{{ $value }}" wire:key="sort-{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if ($notice)
        <x-admin::ui.alert variant="success" icon="check-circle" dismissible wire:key="notice-{{ md5($notice) }}">{{ $notice }}</x-admin::ui.alert>
    @endif

    @if ($this->canManage && $selected !== [])
        <div class="d-flex flex-wrap align-items-center gap-2 p-2 rounded-3 bg-primary-subtle text-primary-emphasis" role="group" aria-label="{{ __('accounts::module.list.add_to_group') }}">
            <span class="fw-semibold">{{ trans_choice('accounts::module.list.selected', count($selected), ['count' => count($selected)]) }}</span>

            <label for="bulk-group" class="visually-hidden">{{ __('accounts::module.list.choose_group') }}</label>
            <select
                id="bulk-group"
                wire:model="bulkGroup"
                @class(['form-select form-select-sm w-auto', 'is-invalid' => $errors->has('bulkGroup')])
                @if ($errors->has('bulkGroup')) aria-invalid="true" aria-describedby="bulk-group-error" @endif
            >
                <option value="">{{ __('accounts::module.list.choose_group') }}</option>
                @foreach ($this->groups as $choice)
                    <option value="{{ $choice->id }}" wire:key="bulk-group-{{ $choice->id }}">{{ $choice->name }}</option>
                @endforeach
            </select>

            <button type="button" class="btn btn-sm btn-primary" wire:click="addSelectedToGroup" wire:loading.attr="disabled">
                <i class="bi bi-tags me-1" aria-hidden="true"></i>
                {{ __('accounts::module.list.add_to_group') }}
            </button>

            <button type="button" class="btn btn-sm btn-link text-primary-emphasis" wire:click="clearSelection">
                {{ __('accounts::module.list.clear_selection') }}
            </button>

            @error('bulkGroup')<div id="bulk-group-error" class="invalid-feedback d-block w-100">{{ $message }}</div>@enderror
        </div>
    @endif

    <x-admin::ui.card flush>
        {{-- One line of totals over whatever the list is narrowed to. --}}
        <p class="small mb-0 px-3 py-2 border-bottom bg-body-tertiary" role="status">
            <span class="fw-semibold">{{ __('accounts::module.list.totals', ['count' => $this->totals->accounts]) }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ __('accounts::module.list.totals_owing', ['count' => $this->totals->owing, 'amount' => $this->totals->owed->format()]) }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ __('accounts::module.list.totals_credit', ['count' => $this->totals->inCredit, 'amount' => $this->totals->heldForThem->format()]) }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ __('accounts::module.list.totals_settled', ['count' => $this->totals->settled]) }}</span>
        </p>

        <div wire:loading.delay.class="is-refreshing" wire:target="q,group,balance,activity,since,sort,selectGroup,clearFilters,gotoPage,nextPage,previousPage">
            @if ($this->rows->isEmpty())
                @if (! $this->filter->narrows() && $this->everyone->accounts === 0)
                    <x-admin::ui.empty-state icon="people" :title="__('accounts::module.list.empty_title')" :description="__('accounts::module.list.empty_description')">
                        @if ($this->canCreate)
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#new-customer">
                                <i class="bi bi-person-plus me-1" aria-hidden="true"></i>
                                {{ __('accounts::module.list.new_customer') }}
                            </button>
                        @endif
                    </x-admin::ui.empty-state>
                @elseif ($this->filter->term !== '' && $group === null)
                    <x-admin::ui.empty-state icon="search" :title="__('accounts::module.list.no_match_name', ['term' => $this->filter->term])">
                        @if ($this->canCreate)
                            <button
                                type="button"
                                class="btn btn-primary"
                                wire:click="$dispatch('new-customer-prefill', { term: @js($this->filter->term) })"
                            >
                                <i class="bi bi-person-plus me-1" aria-hidden="true"></i>
                                {{ __('accounts::module.list.add_named', ['term' => $this->filter->term]) }}
                            </button>
                        @endif
                    </x-admin::ui.empty-state>
                @else
                    <x-admin::ui.empty-state icon="funnel" :title="__('accounts::module.list.no_match_filters')">
                        <button type="button" class="btn btn-outline-primary" wire:click="clearFilters">
                            {{ __('accounts::module.list.clear_filters') }}
                        </button>
                    </x-admin::ui.empty-state>
                @endif
            @else
                <x-admin::table.table :striped="false" id="customer-rows" class="table-cards" data-row-list>
                    <x-slot:head>
                        @if ($this->canManage)
                            <th scope="col" class="ps-3"><span class="visually-hidden">{{ __('accounts::module.list.add_to_group') }}</span></th>
                        @endif
                        <x-admin::table.heading>{{ __('accounts::module.list.column_customer') }}</x-admin::table.heading>
                        <x-admin::table.heading class="d-none d-lg-table-cell">{{ __('accounts::module.list.column_groups') }}</x-admin::table.heading>
                        <x-admin::table.heading align="end">{{ __('accounts::module.list.column_balance') }}</x-admin::table.heading>
                        <x-admin::table.heading class="d-none d-md-table-cell">{{ __('accounts::module.list.column_last') }}</x-admin::table.heading>
                        <x-admin::table.heading class="d-none d-xl-table-cell">{{ __('accounts::module.list.column_alerts') }}</x-admin::table.heading>
                        <x-admin::table.heading align="end"><span class="visually-hidden">{{ __('accounts::module.list.column_actions') }}</span></x-admin::table.heading>
                    </x-slot:head>

                    @foreach ($this->rows as $row)
                        <tr wire:key="account-{{ $row->customerId() }}" data-row tabindex="-1">
                            @if ($this->canManage)
                                <td class="ps-3 cell-check">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        value="{{ $row->customerId() }}"
                                        wire:model.live="selected"
                                        aria-label="{{ __('accounts::module.list.select', ['name' => $row->customer->name]) }}"
                                    >
                                </td>
                            @endif

                            <td class="cell-main">
                                <div class="d-flex align-items-center gap-2">
                                    <x-admin::ui.avatar :name="$row->customer->name" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.accounts.show', $row->customerId()) }}" class="fw-semibold text-break d-inline-block link-body-emphasis link-underline-opacity-25 link-underline-opacity-100-hover" dir="auto" data-row-key="Enter">{{ $row->customer->name }}</a>
                                        @if ($row->customer->phone)
                                            <div class="small text-body-secondary num" dir="ltr">{{ $row->customer->phone }}</div>
                                        @else
                                            <div class="small text-body-secondary">{{ __('accounts::module.list.no_phone') }}</div>
                                        @endif

                                        {{-- What wider screens give a column of its own. --}}
                                        <div class="d-flex flex-wrap gap-1 mt-1 d-lg-none">
                                            @foreach ($row->groups as $member)
                                                <x-accounts::admin.group-chip :group="$member" wire:key="row-{{ $row->customerId() }}-group-{{ $member->id }}" />
                                            @endforeach
                                        </div>
                                        <div class="d-xl-none">
                                            <x-accounts::admin.account-alerts :row="$row" />
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="d-none d-lg-table-cell">
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse ($row->groups as $member)
                                        <x-accounts::admin.group-chip :group="$member" wire:key="col-{{ $row->customerId() }}-group-{{ $member->id }}" />
                                    @empty
                                        <span class="text-body-secondary" aria-hidden="true">—</span>
                                    @endforelse
                                </div>
                            </td>

                            <td class="text-end cell-figure">
                                <x-accounts::admin.balance :amount="$row->outstanding" />
                            </td>

                            <td class="d-none d-md-table-cell small">
                                @if ($row->lastEntryKind && $row->lastEntryAmount && $row->lastEntryAt)
                                    <div>
                                        {{ $row->lastEntryKind->label() }}
                                        <span class="num" dir="ltr">{{ $row->lastEntryAmount->toDisplayString() }}</span>
                                    </div>
                                    <div class="text-body-secondary">
                                        <time datetime="{{ $row->lastEntryAt->toIso8601String() }}">{{ $this->when($row->lastEntryAt) }}</time>
                                    </div>
                                @else
                                    <span class="text-body-secondary">{{ __('accounts::module.list.no_entries') }}</span>
                                @endif
                            </td>

                            <td class="d-none d-xl-table-cell">
                                <x-accounts::admin.account-alerts :row="$row" />
                            </td>

                            <td class="text-end text-nowrap cell-actions">
                                @if ($this->canPost)
                                    <div class="d-inline-flex gap-1">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-flow flow-debit"
                                            data-row-key="+"
                                            wire:click="$dispatch('open-entry', { customerId: {{ $row->customerId() }}, kind: 'charge' })"
                                            aria-label="{{ __('accounts::module.list.charge_for', ['name' => $row->customer->name]) }}"
                                        >
                                            <i class="bi bi-bag-plus" aria-hidden="true"></i>
                                            <span>{{ __('accounts::module.list.charge') }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-flow flow-credit"
                                            data-row-key="-"
                                            wire:click="$dispatch('open-entry', { customerId: {{ $row->customerId() }}, kind: 'payment' })"
                                            aria-label="{{ __('accounts::module.list.payment_from', ['name' => $row->customer->name]) }}"
                                        >
                                            <i class="bi bi-cash-coin" aria-hidden="true"></i>
                                            <span>{{ __('accounts::module.list.payment') }}</span>
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-admin::table.table>
            @endif
        </div>

        <x-slot:footer>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <p class="kbd-hints kbd-hint small text-body-secondary mb-0 d-none d-lg-flex flex-wrap gap-3">
                    <span class="visually-hidden">{{ __('accounts::module.list.keys') }}:</span>
                    <span><kbd dir="ltr">↑ ↓</kbd> {{ __('accounts::module.list.key_move') }}</span>
                    @if ($this->canPost)
                        <span><kbd>+</kbd> {{ __('accounts::module.list.key_charge') }}</span>
                        <span><kbd>−</kbd> {{ __('accounts::module.list.key_payment') }}</span>
                    @endif
                    <span><kbd>/</kbd> {{ __('accounts::module.list.key_search') }}</span>
                    @if ($this->canCreate)
                        <span><kbd>N</kbd> {{ __('accounts::module.list.key_new') }}</span>
                    @endif
                </p>

                <div class="ms-auto">
                    {{ $this->rows->links('admin::table.pagination') }}
                </div>
            </div>
        </x-slot:footer>
    </x-admin::ui.card>

    {{-- Keyed, so the list redrawing around them never mounts them again.
         The drawer that records an entry is on every screen already, for
         whoever may post — see AccountsServiceProvider::adminOverlays(). --}}
    @if ($this->canCreate)
        <livewire:accounts::admin.customers.new-customer wire:key="new-customer" />
    @endif

    @if ($this->canManage)
        <livewire:accounts::admin.customers.groups wire:key="customer-groups" />
    @endif
</div>
