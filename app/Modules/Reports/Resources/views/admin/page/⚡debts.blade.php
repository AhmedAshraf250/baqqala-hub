<?php

use App\Foundation\Money\Money;
use App\Modules\Reports\Domain\Queries\DebtsReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * What customers owe now: in all, by the owner's groups, and who owes most —
 * with how long they have owed and when they last paid.
 */
new #[Layout('admin::layout.app')] class extends Component {
    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('reports::module.debts.title')]);
    }

    /**
     * @return array{accounts: int, owing: int, owed: Money, inCredit: int, held: Money, settled: int}
     */
    #[Computed]
    public function totals(): array
    {
        return app(DebtsReport::class)->totals();
    }

    /**
     * @return list<array{name: string|null, color: string|null, members: int, owing: int, owed: Money}>
     */
    #[Computed]
    public function groups(): array
    {
        return app(DebtsReport::class)->byGroup();
    }

    /**
     * @return list<array{customerId: int, name: string, phone: string|null, owed: Money, since: CarbonImmutable|null, lastPayment: CarbonImmutable|null}>
     */
    #[Computed]
    public function largest(): array
    {
        return app(DebtsReport::class)->largest();
    }

    /**
     * The most a row shows, for the bars beside the figures.
     */
    #[Computed]
    public function most(): int
    {
        return max(1, ...array_map(static fn (array $group): int => $group['owed']->minorUnits, $this->groups ?: [['owed' => Money::zero()]]));
    }

    public function ago(?CarbonImmutable $moment): string
    {
        return $moment === null ? '—' : local_time($moment)->locale(app()->getLocale())->diffForHumans(['parts' => 1]);
    }
}; ?>

<div class="d-grid gap-3">
    <div class="report-toolbar no-print">
        <p class="text-body-secondary mb-0">{{ __('reports::module.debts.question') }}</p>
        <button type="button" class="btn btn-outline-secondary" data-print>
            <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('reports::module.print') }}
        </button>
    </div>

    <div class="row g-3">
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat icon="arrow-up-right-circle" variant="danger" :label="__('reports::module.debts.owed')" :value="$this->totals['owed']->format()" :caption="trans_choice('reports::module.customers', $this->totals['owing'], ['count' => $this->totals['owing']])" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat icon="piggy-bank" variant="success" :label="__('reports::module.debts.held')" :value="$this->totals['held']->format()" :caption="trans_choice('reports::module.customers', $this->totals['inCredit'], ['count' => $this->totals['inCredit']])" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat icon="check2-circle" variant="secondary" :label="__('reports::module.debts.settled')" :value="$this->totals['settled']" :caption="__('reports::module.debts.settled_caption')" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin::ui.stat icon="people" variant="primary" :label="__('reports::module.debts.accounts')" :value="$this->totals['accounts']" :caption="__('reports::module.debts.accounts_caption')" />
        </div>
    </div>

    <div class="row g-3">
        {{-- By the owner's groups: who owes the shop most, as a group. --}}
        <div class="col-xl-5">
            <x-admin::ui.card :title="__('reports::module.debts.by_group')" icon="tags" flush class="h-100">
                @if ($this->groups === [])
                    <p class="text-body-secondary small p-3 mb-0">{{ __('reports::module.empty') }}</p>
                @else
                    <x-admin::table.table :striped="false">
                        <x-slot:head>
                            <x-admin::table.heading>{{ __('reports::module.debts.group') }}</x-admin::table.heading>
                            <x-admin::table.heading align="end">{{ __('reports::module.debts.owing') }}</x-admin::table.heading>
                            <x-admin::table.heading align="end">{{ __('reports::module.debts.owed') }}</x-admin::table.heading>
                        </x-slot:head>
                        @foreach ($this->groups as $group)
                            <tr wire:key="group-{{ $loop->index }}">
                                <td>
                                    <span @class(['chip', 'tone-'.$group['color'] => $group['color'] !== null]) dir="auto">
                                        @if ($group['color'] !== null)<span class="chip-dot" aria-hidden="true"></span>@endif
                                        {{ $group['name'] ?? __('reports::module.debts.no_group') }}
                                    </span>
                                    <progress class="report-bar flow-debit mt-2" value="{{ $group['owed']->minorUnits }}" max="{{ $this->most }}" aria-hidden="true"></progress>
                                </td>
                                <td class="text-end num">{{ $group['owing'] }} <span class="text-body-secondary">/ {{ $group['members'] }}</span></td>
                                <td class="text-end"><span class="num fw-semibold flow-debit flow-text" dir="ltr">{{ $group['owed']->toDisplayString() }}</span></td>
                            </tr>
                        @endforeach
                    </x-admin::table.table>
                @endif
            </x-admin::ui.card>
        </div>

        {{-- Who owes most. --}}
        <div class="col-xl-7">
            <x-admin::ui.card :title="__('reports::module.debts.largest')" icon="sort-down" flush class="h-100">
                @if ($this->largest === [])
                    <p class="text-body-secondary small p-3 mb-0">{{ __('reports::module.debts.nobody_owes') }}</p>
                @else
                    <x-admin::table.table :striped="false">
                        <x-slot:head>
                            <x-admin::table.heading>{{ __('reports::module.customer') }}</x-admin::table.heading>
                            <x-admin::table.heading align="end">{{ __('reports::module.debts.owes') }}</x-admin::table.heading>
                            <x-admin::table.heading class="d-none d-md-table-cell">{{ __('reports::module.debts.since') }}</x-admin::table.heading>
                            <x-admin::table.heading class="d-none d-md-table-cell">{{ __('reports::module.last_payment') }}</x-admin::table.heading>
                        </x-slot:head>
                        @foreach ($this->largest as $row)
                            <tr wire:key="largest-{{ $row['customerId'] }}">
                                <td>
                                    <span class="report-rank num" aria-hidden="true">{{ $loop->iteration }}</span>
                                    <a href="{{ route('admin.accounts.show', $row['customerId']) }}" class="fw-semibold link-body-emphasis link-underline-opacity-25 link-underline-opacity-100-hover" dir="auto">{{ $row['name'] }}</a>
                                    @if ($row['phone'])<div class="small text-body-secondary"><span class="num" dir="ltr">{{ $row['phone'] }}</span></div>@endif
                                </td>
                                <td class="text-end"><span class="num fw-bold flow-debit flow-text" dir="ltr">{{ $row['owed']->toDisplayString() }}</span></td>
                                <td class="d-none d-md-table-cell small">{{ $this->ago($row['since']) }}</td>
                                <td class="d-none d-md-table-cell small">{{ $row['lastPayment'] ? $this->ago($row['lastPayment']) : __('reports::module.never_paid') }}</td>
                            </tr>
                        @endforeach
                    </x-admin::table.table>
                @endif
            </x-admin::ui.card>
        </div>
    </div>
</div>
