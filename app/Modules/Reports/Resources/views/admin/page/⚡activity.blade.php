<?php

use App\Foundation\Money\Money;
use App\Modules\Reports\Areas\Admin\PeriodReport;
use App\Modules\Reports\Domain\Queries\ActivityReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

/**
 * What was taken on account and what was paid, day by day — or month by
 * month over a longer period — and what that did to what customers owe.
 */
new #[Layout('admin::layout.app')] class extends PeriodReport {
    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('reports::module.activity.title')]);
    }

    /**
     * @return list<array{label: string, taken: Money, charges: int, collected: Money, payments: int}>
     */
    #[Computed]
    public function rows(): array
    {
        return app(ActivityReport::class)->rows($this->range);
    }

    /**
     * @return array{taken: Money, charges: int, collected: Money, payments: int}
     */
    #[Computed]
    public function totals(): array
    {
        return app(ActivityReport::class)->totals($this->range);
    }

    /**
     * The most a row shows either way, for the bars.
     */
    #[Computed]
    public function most(): int
    {
        return max(1, ...array_map(static fn (array $row): int => max($row['taken']->minorUnits, $row['collected']->minorUnits), $this->rows));
    }

    /**
     * What the period did to what customers owe: which way, said in the
     * label, and by how much — so the figure stays a figure.
     *
     * @return array{label: string, amount: Money, variant: string}
     */
    #[Computed]
    public function movement(): array
    {
        $change = $this->totals['taken']->minus($this->totals['collected']);

        return match (true) {
            $change->isPositive() => ['label' => __('reports::module.activity.rose'), 'amount' => $change, 'variant' => 'danger'],
            $change->isNegative() => ['label' => __('reports::module.activity.fell'), 'amount' => $change->negated(), 'variant' => 'success'],
            default => ['label' => __('reports::module.activity.unchanged'), 'amount' => $change, 'variant' => 'secondary'],
        };
    }

    /**
     * What a row did to what customers owe, in words — «up 350.00» — never
     * a sign.
     */
    public function change(Money $taken, Money $collected): string
    {
        $change = $taken->minus($collected);

        return match (true) {
            $change->isPositive() => __('reports::module.activity.up', ['amount' => $change->toDisplayString()]),
            $change->isNegative() => __('reports::module.activity.down', ['amount' => $change->negated()->toDisplayString()]),
            default => __('reports::module.activity.none'),
        };
    }
}; ?>

<div class="d-grid gap-3">
    @include('reports::admin.period', ['period' => $period, 'from' => $from, 'to' => $to, 'choices' => $this->periodChoices(), 'range' => $this->range])

    <div class="row g-3">
        <div class="col-sm-4">
            <x-admin::ui.stat icon="bag-plus" variant="danger" :label="__('reports::module.activity.taken')" :value="$this->totals['taken']->format()" :caption="trans_choice('reports::module.entries', $this->totals['charges'], ['count' => $this->totals['charges']])" />
        </div>
        <div class="col-sm-4">
            <x-admin::ui.stat icon="cash-coin" variant="success" :label="__('reports::module.activity.collected')" :value="$this->totals['collected']->format()" :caption="trans_choice('reports::module.entries', $this->totals['payments'], ['count' => $this->totals['payments']])" />
        </div>
        <div class="col-sm-4">
            <x-admin::ui.stat icon="arrow-down-up" :variant="$this->movement['variant']" :label="$this->movement['label']" :value="$this->movement['amount']->format()" :caption="__('reports::module.activity.change_caption')" />
        </div>
    </div>

    <x-admin::ui.card :title="$this->range->byMonth() ? __('reports::module.activity.by_month') : __('reports::module.activity.by_day')" icon="bar-chart" flush>
        <div wire:loading.delay.class="is-refreshing" wire:target="period,from,to">
            <x-admin::table.table :striped="false">
                <x-slot:head>
                    <x-admin::table.heading>{{ $this->range->byMonth() ? __('reports::module.activity.month') : __('reports::module.activity.day') }}</x-admin::table.heading>
                    <x-admin::table.heading align="end">{{ __('reports::module.activity.taken') }}</x-admin::table.heading>
                    <x-admin::table.heading align="end">{{ __('reports::module.activity.collected') }}</x-admin::table.heading>
                    <x-admin::table.heading align="end" class="d-none d-md-table-cell">{{ __('reports::module.activity.change') }}</x-admin::table.heading>
                </x-slot:head>
                @foreach ($this->rows as $row)
                    <tr wire:key="step-{{ $loop->index }}" @class(['text-body-secondary' => $row['charges'] + $row['payments'] === 0])>
                        <td class="text-nowrap">{{ $row['label'] }}</td>
                        <td class="text-end report-bar-cell">
                            <span class="num flow-debit flow-text" dir="ltr">{{ $row['taken']->toDisplayString() }}</span>
                            <progress class="report-bar flow-debit" value="{{ $row['taken']->minorUnits }}" max="{{ $this->most }}" aria-hidden="true"></progress>
                        </td>
                        <td class="text-end report-bar-cell">
                            <span class="num flow-credit flow-text" dir="ltr">{{ $row['collected']->toDisplayString() }}</span>
                            <progress class="report-bar flow-credit" value="{{ $row['collected']->minorUnits }}" max="{{ $this->most }}" aria-hidden="true"></progress>
                        </td>
                        <td class="text-end d-none d-md-table-cell num">{{ $this->change($row['taken'], $row['collected']) }}</td>
                    </tr>
                @endforeach
                <x-slot:foot>
                    <tr class="fw-bold">
                        <td>{{ __('reports::module.total') }}</td>
                        <td class="text-end"><span class="num flow-debit flow-text" dir="ltr">{{ $this->totals['taken']->toDisplayString() }}</span></td>
                        <td class="text-end"><span class="num flow-credit flow-text" dir="ltr">{{ $this->totals['collected']->toDisplayString() }}</span></td>
                        <td class="text-end d-none d-md-table-cell num">{{ $this->change($this->totals['taken'], $this->totals['collected']) }}</td>
                    </tr>
                </x-slot:foot>
            </x-admin::table.table>
        </div>
    </x-admin::ui.card>
</div>
