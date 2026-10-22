<?php

use App\Foundation\Money\Money;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Reports\Areas\Admin\PeriodReport;
use App\Modules\Reports\Domain\Queries\ProductsReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

/**
 * What was taken on account most in a period — product by product, with how
 * much of it and on how many charges, and shelf by shelf.
 */
new #[Layout('admin::layout.app')] class extends PeriodReport {
    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('reports::module.products.title')]);
    }

    /**
     * @return list<array{productId: int|null, name: string|null, unit: string|null, category: string|null, quantity: int, amount: Money, charges: int}>
     */
    #[Computed]
    public function products(): array
    {
        return app(ProductsReport::class)->products($this->range);
    }

    /**
     * @return list<array{category: string|null, amount: Money, lines: int}>
     */
    #[Computed]
    public function categories(): array
    {
        return app(ProductsReport::class)->categories($this->range);
    }

    #[Computed]
    public function most(): int
    {
        return max(1, ...array_map(static fn (array $row): int => $row['amount']->minorUnits, $this->categories ?: [['amount' => Money::zero()]]));
    }

    /**
     * A quantity in thousandths, as it reads with its unit: «2 قطعة», «1.25 كجم».
     */
    public function quantity(int $thousandths, ?string $unit): string
    {
        $whole = intdiv($thousandths, SaleUnit::SCALE);
        $fraction = rtrim(str_pad((string) ($thousandths % SaleUnit::SCALE), 3, '0', STR_PAD_LEFT), '0');

        return trim(($fraction === '' ? (string) $whole : $whole.'.'.$fraction).' '.(SaleUnit::tryFrom((string) $unit)?->label() ?? ''));
    }
}; ?>

<div class="d-grid gap-3">
    @include('reports::admin.period', ['period' => $period, 'from' => $from, 'to' => $to, 'choices' => $this->periodChoices(), 'range' => $this->range])

    <div class="row g-3" wire:loading.delay.class="is-refreshing" wire:target="period,from,to">
        <div class="col-xl-7">
            <x-admin::ui.card :title="__('reports::module.products.most')" icon="basket" flush class="h-100">
                @if ($this->products === [])
                    <x-admin::ui.empty-state icon="basket" :title="__('reports::module.products.none')" />
                @else
                    <x-admin::table.table :striped="false">
                        <x-slot:head>
                            <x-admin::table.heading>{{ __('reports::module.products.product') }}</x-admin::table.heading>
                            <x-admin::table.heading align="end">{{ __('reports::module.products.quantity') }}</x-admin::table.heading>
                            <x-admin::table.heading align="end">{{ __('reports::module.products.amount') }}</x-admin::table.heading>
                        </x-slot:head>
                        @foreach ($this->products as $row)
                            <tr wire:key="product-{{ $row['productId'] ?? 'free' }}">
                                <td>
                                    <span class="report-rank num" aria-hidden="true">{{ $loop->iteration }}</span>
                                    <span class="fw-semibold" dir="auto">{{ $row['name'] ?? __('reports::module.products.free_lines') }}</span>
                                    <div class="small text-body-secondary">
                                        @if ($row['category'])<span dir="auto">{{ $row['category'] }}</span> ·@endif
                                        {{ trans_choice('reports::module.products.charges', $row['charges'], ['count' => $row['charges']]) }}
                                    </div>
                                </td>
                                <td class="text-end num text-nowrap">{{ $row['productId'] === null ? '—' : $this->quantity($row['quantity'], $row['unit']) }}</td>
                                <td class="text-end"><span class="num fw-semibold" dir="ltr">{{ $row['amount']->toDisplayString() }}</span></td>
                            </tr>
                        @endforeach
                    </x-admin::table.table>
                @endif
            </x-admin::ui.card>
        </div>

        <div class="col-xl-5">
            <x-admin::ui.card :title="__('reports::module.products.by_category')" icon="tags" flush class="h-100">
                @if ($this->categories === [])
                    <p class="text-body-secondary small p-3 mb-0">{{ __('reports::module.empty') }}</p>
                @else
                    <x-admin::table.table :striped="false">
                        <x-slot:head>
                            <x-admin::table.heading>{{ __('reports::module.products.category') }}</x-admin::table.heading>
                            <x-admin::table.heading align="end">{{ __('reports::module.products.amount') }}</x-admin::table.heading>
                        </x-slot:head>
                        @foreach ($this->categories as $row)
                            <tr wire:key="category-{{ $loop->index }}">
                                <td>
                                    <span dir="auto">{{ $row['category'] ?? __('reports::module.products.free_lines') }}</span>
                                    <progress class="report-bar mt-2" value="{{ $row['amount']->minorUnits }}" max="{{ $this->most }}" aria-hidden="true"></progress>
                                </td>
                                <td class="text-end"><span class="num fw-semibold" dir="ltr">{{ $row['amount']->toDisplayString() }}</span></td>
                            </tr>
                        @endforeach
                    </x-admin::table.table>
                @endif
            </x-admin::ui.card>
        </div>
    </div>
</div>
