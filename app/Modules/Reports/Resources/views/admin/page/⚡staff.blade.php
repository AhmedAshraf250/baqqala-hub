<?php

use App\Foundation\Money\Money;
use App\Modules\Reports\Areas\Admin\PeriodReport;
use App\Modules\Reports\Domain\Queries\StaffReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

/**
 * Each person's share of a period: what they took on account, the cash they
 * collected, and the mistakes they put right — the cash first, since that
 * is what a drawer is counted against.
 */
new #[Layout('admin::layout.app')] class extends PeriodReport {
    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('reports::module.staff.title')]);
    }

    /**
     * @return list<array{id: int, name: string, charges: int, taken: Money, payments: int, collected: Money, undone: int, voided: int, corrected: int}>
     */
    #[Computed]
    public function people(): array
    {
        return app(StaffReport::class)->people($this->range);
    }

    /**
     * What a person put right, only what there was of it.
     *
     * @param  array{undone: int, voided: int, corrected: int}  $person
     * @return array<string, int>
     */
    public function fixes(array $person): array
    {
        return array_filter(['undone' => $person['undone'], 'voided' => $person['voided'], 'corrected' => $person['corrected']]);
    }

    /**
     * The most cash anyone collected, for the bars.
     */
    #[Computed]
    public function most(): int
    {
        return max(1, ...array_map(static fn (array $person): int => $person['collected']->minorUnits, $this->people ?: [['collected' => Money::zero()]]));
    }
}; ?>

<div class="d-grid gap-3">
    @include('reports::admin.period', ['period' => $period, 'from' => $from, 'to' => $to, 'choices' => $this->periodChoices(), 'range' => $this->range])

    <x-admin::ui.card :title="__('reports::module.staff.title')" icon="people" flush>
        <div wire:loading.delay.class="is-refreshing" wire:target="period,from,to">
            @if ($this->people === [])
                <x-admin::ui.empty-state icon="people" :title="__('reports::module.staff.nobody')" />
            @else
                <x-admin::table.table :striped="false">
                    <x-slot:head>
                        <x-admin::table.heading>{{ __('reports::module.staff.person') }}</x-admin::table.heading>
                        <x-admin::table.heading align="end">{{ __('reports::module.activity.taken') }}</x-admin::table.heading>
                        <x-admin::table.heading align="end">{{ __('reports::module.activity.collected') }}</x-admin::table.heading>
                        <x-admin::table.heading align="end" class="d-none d-md-table-cell">{{ __('reports::module.staff.fixes') }}</x-admin::table.heading>
                    </x-slot:head>
                    @foreach ($this->people as $person)
                        <tr wire:key="person-{{ $person['id'] }}">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <x-admin::ui.avatar :name="$person['name']" size="sm" />
                                    <span class="fw-semibold" dir="auto">{{ $person['name'] }}</span>
                                </div>
                            </td>
                            <td class="text-end">
                                <span class="num flow-debit flow-text" dir="ltr">{{ $person['taken']->toDisplayString() }}</span>
                                <div class="small text-body-secondary">{{ trans_choice('reports::module.entries', $person['charges'], ['count' => $person['charges']]) }}</div>
                            </td>
                            <td class="text-end report-bar-cell">
                                <span class="num fw-bold flow-credit flow-text" dir="ltr">{{ $person['collected']->toDisplayString() }}</span>
                                <progress class="report-bar flow-credit" value="{{ $person['collected']->minorUnits }}" max="{{ $this->most }}" aria-hidden="true"></progress>
                                <div class="small text-body-secondary">{{ trans_choice('reports::module.entries', $person['payments'], ['count' => $person['payments']]) }}</div>
                            </td>
                            <td class="text-end d-none d-md-table-cell small">
                                @forelse ($this->fixes($person) as $fix => $count)
                                    <div wire:key="fix-{{ $person['id'] }}-{{ $fix }}">{{ trans_choice('reports::module.staff.'.$fix, $count, ['count' => $count]) }}</div>
                                @empty
                                    <span class="text-body-secondary">—</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </x-admin::table.table>
            @endif
        </div>
    </x-admin::ui.card>
</div>
