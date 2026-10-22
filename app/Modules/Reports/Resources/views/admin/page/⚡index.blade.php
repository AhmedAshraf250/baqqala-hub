<?php

use App\Foundation\Time\LocalClock;
use App\Modules\Reports\Domain\Queries\ActivityReport;
use App\Modules\Reports\Domain\Queries\AgingReport;
use App\Modules\Reports\Domain\Queries\CorrectionsReport;
use App\Modules\Reports\Domain\Queries\DebtsReport;
use App\Modules\Reports\Domain\Queries\ProductsReport;
use App\Modules\Reports\Domain\Queries\ReportPeriod;
use App\Modules\Reports\Domain\Queries\StaffReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The reports, each a card with what it answers and its headline figure for
 * now or this month, a press away from the whole of it.
 */
new #[Layout('admin::layout.app')] class extends Component {
    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('reports::module.navigation.reports')]);
    }

    /**
     * Each report's card: where it goes, its icon and tone, and the figure
     * it leads with.
     *
     * @return list<array{key: string, route: string, icon: string, tone: string, figure: string, caption: string}>
     */
    #[Computed]
    public function cards(): array
    {
        $month = ReportPeriod::named('month', app(LocalClock::class));
        $debts = app(DebtsReport::class)->totals();
        $aging = app(AgingReport::class)->buckets();
        $activity = app(ActivityReport::class)->totals($month);
        $staff = app(StaffReport::class)->people($month);
        $corrections = array_sum(app(CorrectionsReport::class)->byReason($month));
        $top = app(ProductsReport::class)->products($month, 1)[0] ?? null;

        return [
            ['key' => 'debts', 'route' => 'admin.reports.debts', 'icon' => 'wallet2', 'tone' => 'red', 'figure' => $debts['owed']->format(), 'caption' => trans_choice('reports::module.index.debts_caption', $debts['owing'], ['count' => $debts['owing']])],
            ['key' => 'aging', 'route' => 'admin.reports.aging', 'icon' => 'hourglass-split', 'tone' => 'amber', 'figure' => (string) $aging['over_90']['count'], 'caption' => __('reports::module.index.aging_caption')],
            ['key' => 'activity', 'route' => 'admin.reports.activity', 'icon' => 'bar-chart', 'tone' => 'green', 'figure' => $activity['collected']->format(), 'caption' => __('reports::module.index.activity_caption')],
            ['key' => 'staff', 'route' => 'admin.reports.staff', 'icon' => 'people', 'tone' => 'blue', 'figure' => $staff[0]['name'] ?? '—', 'caption' => __('reports::module.index.staff_caption')],
            ['key' => 'corrections', 'route' => 'admin.reports.corrections', 'icon' => 'arrow-counterclockwise', 'tone' => 'gray', 'figure' => (string) $corrections, 'caption' => __('reports::module.index.corrections_caption')],
            ['key' => 'products', 'route' => 'admin.reports.products', 'icon' => 'basket', 'tone' => 'teal', 'figure' => $top === null ? '—' : (string) ($top['name'] ?? __('reports::module.products.free_lines')), 'caption' => __('reports::module.index.products_caption')],
        ];
    }
}; ?>

<div class="d-grid gap-3">
    <p class="text-body-secondary mb-0">{{ __('reports::module.index.intro') }}</p>

    <div class="row g-3">
        @foreach ($this->cards as $card)
            <div class="col-md-6 col-xl-4" wire:key="report-{{ $card['key'] }}">
                <a href="{{ route($card['route']) }}" class="card h-100 report-card text-decoration-none">
                    <div class="card-body d-flex gap-3">
                        <span class="report-card-icon tone-{{ $card['tone'] }}" aria-hidden="true"><i class="bi bi-{{ $card['icon'] }}"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <h2 class="h6 mb-1 text-body-emphasis">{{ __('reports::module.'.$card['key'].'.title') }}</h2>
                            <p class="small text-body-secondary mb-2">{{ __('reports::module.'.$card['key'].'.question') }}</p>
                            <div class="report-card-figure num text-body-emphasis" dir="auto">{{ $card['figure'] }}</div>
                            <div class="small text-body-secondary">{{ $card['caption'] }}</div>
                        </div>
                        <i class="bi bi-chevron-{{ is_rtl() ? 'left' : 'right' }} text-body-secondary align-self-center" aria-hidden="true"></i>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
