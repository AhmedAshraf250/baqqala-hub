<?php

namespace App\Modules\Reports\Areas\Admin;

use App\Foundation\Time\LocalClock;
use App\Modules\Reports\Domain\Queries\ReportPeriod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A report screen over a period: this month unless another is chosen, kept
 * in the page's address so a report for a period is a link.
 */
abstract class PeriodReport extends Component
{
    #[Url(except: 'month')]
    public string $period = 'month';

    /** The first day of a chosen stretch, as Y-m-d. */
    #[Url(except: '')]
    public string $from = '';

    /** The last day of a chosen stretch, as Y-m-d. */
    #[Url(except: '')]
    public string $to = '';

    #[Computed]
    public function range(): ReportPeriod
    {
        return ReportPeriod::named(
            in_array($this->period, ReportPeriod::CHOICES, true) ? $this->period : 'month',
            app(LocalClock::class),
            $this->from,
            $this->to,
        );
    }

    /**
     * Each period offered, with its name.
     *
     * @return array<string, string>
     */
    public function periodChoices(): array
    {
        $choices = [];

        foreach (ReportPeriod::CHOICES as $choice) {
            $choices[$choice] = __('reports::module.period.'.$choice);
        }

        return $choices;
    }
}
