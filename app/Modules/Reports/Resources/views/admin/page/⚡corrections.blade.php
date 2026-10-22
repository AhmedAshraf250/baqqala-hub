<?php

use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Reports\Areas\Admin\PeriodReport;
use App\Modules\Reports\Domain\Queries\CorrectionsReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

/**
 * What was put right in a period: how often for each reason, and every void,
 * undo, and correction one by one — whose entry it was, for whom, and who put
 * it right.
 */
new #[Layout('admin::layout.app')] class extends PeriodReport {
    #[Url(except: '')]
    public string $reason = '';

    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('reports::module.corrections.title')]);
    }

    /**
     * How often each reason came up — and the one chosen, even at none, so
     * it can be let go.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function reasons(): array
    {
        return array_filter(
            app(CorrectionsReport::class)->byReason($this->range),
            fn (int $count, string $reason): bool => $count > 0 || $reason === $this->reason,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @return list<array{at: CarbonImmutable, by: string, recordedBy: string, customerId: int, customer: string, reason: CorrectionReason|null, kind: EntryKind, amount: \App\Foundation\Money\Money, recordedAt: CarbonImmutable, corrected: bool, note: string|null}>
     */
    #[Computed]
    public function entries(): array
    {
        return app(CorrectionsReport::class)->entries($this->range, CorrectionReason::tryFrom($this->reason));
    }

    public function chooseReason(string $reason): void
    {
        $this->reason = $this->reason === $reason || CorrectionReason::tryFrom($reason) === null ? '' : $reason;
    }

    public function reasonLabel(string $reason): string
    {
        return CorrectionReason::from($reason)->label();
    }

    public function when(CarbonImmutable $moment): string
    {
        return local_time($moment)->locale(app()->getLocale())->isoFormat('D MMMM, LT');
    }
}; ?>

<div class="d-grid gap-3">
    @include('reports::admin.period', ['period' => $period, 'from' => $from, 'to' => $to, 'choices' => $this->periodChoices(), 'range' => $this->range])

    {{-- How often for each reason that came up; each a press away from its entries. --}}
    <div class="d-flex flex-wrap gap-2 no-print" role="group" aria-label="{{ __('reports::module.corrections.reasons') }}">
        @foreach ($this->reasons as $value => $count)
            <button type="button" class="btn chip" wire:click="chooseReason('{{ $value }}')" aria-pressed="{{ $reason === $value ? 'true' : 'false' }}" wire:key="reason-{{ $value }}">
                <i class="bi bi-check2 chip-tick" aria-hidden="true"></i>
                {{ $this->reasonLabel($value) }}
                <span class="chip-count num" dir="ltr">{{ $count }}</span>
            </button>
        @endforeach
    </div>

    <x-admin::ui.card :title="__('reports::module.corrections.list')" icon="arrow-counterclockwise" flush>
        <div wire:loading.delay.class="is-refreshing" wire:target="period,from,to,chooseReason">
            @if ($this->entries === [])
                <x-admin::ui.empty-state icon="check2-circle" :title="__('reports::module.corrections.none')" />
            @else
                <x-admin::table.table :striped="false">
                    <x-slot:head>
                        <x-admin::table.heading>{{ __('reports::module.corrections.when') }}</x-admin::table.heading>
                        <x-admin::table.heading>{{ __('reports::module.customer') }}</x-admin::table.heading>
                        <x-admin::table.heading>{{ __('reports::module.corrections.what') }}</x-admin::table.heading>
                        <x-admin::table.heading class="d-none d-md-table-cell">{{ __('reports::module.corrections.reason') }}</x-admin::table.heading>
                        <x-admin::table.heading class="d-none d-lg-table-cell">{{ __('reports::module.corrections.who') }}</x-admin::table.heading>
                    </x-slot:head>
                    @foreach ($this->entries as $entry)
                        <tr wire:key="fix-{{ $loop->index }}">
                            <td class="small text-nowrap">{{ $this->when($entry['at']) }}</td>
                            <td><a href="{{ route('admin.accounts.show', $entry['customerId']) }}" class="link-body-emphasis link-underline-opacity-25 link-underline-opacity-100-hover" dir="auto">{{ $entry['customer'] }}</a></td>
                            <td>
                                <span class="fw-semibold">{{ $entry['kind']->label() }}</span>
                                <span class="num" dir="ltr">{{ $entry['amount']->toDisplayString() }}</span>
                                @if ($entry['corrected'])
                                    <span class="badge bg-primary-subtle text-primary-emphasis ms-1">{{ __('reports::module.corrections.corrected') }}</span>
                                @endif
                                <div class="small text-body-secondary">{{ __('reports::module.corrections.recorded', ['name' => $entry['recordedBy'], 'when' => $this->when($entry['recordedAt'])]) }}</div>
                                @if ($entry['note'])<div class="small text-body-secondary" dir="auto">{{ $entry['note'] }}</div>@endif
                            </td>
                            <td class="d-none d-md-table-cell">{{ $entry['reason']?->label() }}</td>
                            <td class="d-none d-lg-table-cell small" dir="auto">{{ $entry['by'] }}</td>
                        </tr>
                    @endforeach
                </x-admin::table.table>
            @endif
        </div>
    </x-admin::ui.card>
</div>
