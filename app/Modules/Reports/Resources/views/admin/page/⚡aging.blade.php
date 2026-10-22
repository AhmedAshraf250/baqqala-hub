<?php

use App\Foundation\Money\Money;
use App\Modules\Reports\Domain\Queries\AgingReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Who owes, by how long they have gone without paying, and the list to ring —
 * the longest first, each with a button that dials them from a phone.
 */
new #[Layout('admin::layout.app')] class extends Component {
    /** One bucket, or every customer who owes. */
    #[Url(except: '')]
    public string $bucket = '';

    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('reports::module.aging.title')]);
    }

    /**
     * @return array<string, array{count: int, owed: Money}>
     */
    #[Computed]
    public function buckets(): array
    {
        return app(AgingReport::class)->buckets();
    }

    /**
     * @return list<array{customerId: int, name: string, phone: string|null, owed: Money, unpaidSince: CarbonImmutable|null, debtSince: CarbonImmutable|null, lastPayment: CarbonImmutable|null}>
     */
    #[Computed]
    public function customers(): array
    {
        return app(AgingReport::class)->customers(array_key_exists($this->bucket, AgingReport::BUCKETS) ? $this->bucket : null);
    }

    public function choose(string $bucket): void
    {
        $this->bucket = $this->bucket === $bucket || ! array_key_exists($bucket, AgingReport::BUCKETS) ? '' : $bucket;
    }

    public function days(?CarbonImmutable $since): ?int
    {
        return $since === null ? null : (int) $since->diffInDays(CarbonImmutable::now());
    }

    public function date(?CarbonImmutable $moment): string
    {
        return $moment === null ? '—' : local_time($moment)->locale(app()->getLocale())->isoFormat('D MMMM YYYY');
    }

    /**
     * How loud a bucket is: the longer without paying, the louder — quiet
     * tints at first, solid amber and red only at the far end.
     *
     * @return array{variant: string, soft: bool}
     */
    public function tone(string $bucket): array
    {
        return match ($bucket) {
            'under_30' => ['variant' => 'secondary', 'soft' => true],
            'under_60' => ['variant' => 'warning', 'soft' => true],
            'under_90' => ['variant' => 'warning', 'soft' => false],
            default => ['variant' => 'danger', 'soft' => false],
        };
    }
}; ?>

<div class="d-grid gap-3">
    <div class="report-toolbar no-print">
        <p class="text-body-secondary mb-0">{{ __('reports::module.aging.question') }}</p>
        <button type="button" class="btn btn-outline-secondary" data-print>
            <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('reports::module.print') }}
        </button>
    </div>

    {{-- How long without paying, each a press away from its customers. --}}
    <div class="row g-3" role="group" aria-label="{{ __('reports::module.aging.buckets') }}">
        @foreach ($this->buckets as $key => $figures)
            <div class="col-6 col-xl-3" wire:key="bucket-{{ $key }}">
                <x-admin::ui.stat
                    icon="hourglass-split"
                    :variant="$this->tone($key)['variant']"
                    :soft="$this->tone($key)['soft']"
                    :label="__('reports::module.aging.bucket.'.$key)"
                    :value="$figures['owed']->format()"
                    :caption="trans_choice('reports::module.customers', $figures['count'], ['count' => $figures['count']])"
                    wire:click="choose('{{ $key }}')"
                    :pressed="$bucket === $key"
                />
            </div>
        @endforeach
    </div>

    <x-admin::ui.card :title="$bucket === '' ? __('reports::module.aging.everyone') : __('reports::module.aging.bucket.'.$bucket)" icon="telephone" flush>
        <div wire:loading.delay.class="is-refreshing" wire:target="choose">
            @if ($this->customers === [])
                <p class="text-body-secondary small p-3 mb-0">{{ __('reports::module.aging.nobody') }}</p>
            @else
                <x-admin::table.table :striped="false">
                    <x-slot:head>
                        <x-admin::table.heading>{{ __('reports::module.customer') }}</x-admin::table.heading>
                        <x-admin::table.heading align="end">{{ __('reports::module.debts.owes') }}</x-admin::table.heading>
                        <x-admin::table.heading>{{ __('reports::module.aging.unpaid_for') }}</x-admin::table.heading>
                        <x-admin::table.heading class="d-none d-lg-table-cell">{{ __('reports::module.last_payment') }}</x-admin::table.heading>
                        <x-admin::table.heading align="end" class="no-print"><span class="visually-hidden">{{ __('reports::module.aging.call') }}</span></x-admin::table.heading>
                    </x-slot:head>
                    @foreach ($this->customers as $row)
                        <tr wire:key="late-{{ $row['customerId'] }}">
                            <td>
                                <a href="{{ route('admin.accounts.show', $row['customerId']) }}" class="fw-semibold link-body-emphasis link-underline-opacity-25 link-underline-opacity-100-hover" dir="auto">{{ $row['name'] }}</a>
                                @if ($row['phone'])
                                    <div class="small text-body-secondary"><span class="num" dir="ltr">{{ $row['phone'] }}</span></div>
                                @else
                                    <div class="small text-body-secondary">{{ __('reports::module.no_phone') }}</div>
                                @endif
                            </td>
                            <td class="text-end"><span class="num fw-bold flow-debit flow-text" dir="ltr">{{ $row['owed']->toDisplayString() }}</span></td>
                            <td>
                                @if ($this->days($row['unpaidSince']) !== null)
                                    {{ trans_choice('reports::module.aging.days', $this->days($row['unpaidSince']), ['count' => $this->days($row['unpaidSince'])]) }}
                                @endif
                                <div class="small text-body-secondary">{{ __('reports::module.aging.debt_since', ['date' => $this->date($row['debtSince'])]) }}</div>
                            </td>
                            <td class="d-none d-lg-table-cell small">{{ $row['lastPayment'] ? $this->date($row['lastPayment']) : __('reports::module.never_paid') }}</td>
                            <td class="text-end no-print">
                                @if ($row['phone'])
                                    <a href="tel:{{ $row['phone'] }}" class="btn btn-sm btn-flow flow-credit" aria-label="{{ __('reports::module.aging.call_named', ['name' => $row['name']]) }}">
                                        <i class="bi bi-telephone" aria-hidden="true"></i>
                                        <span class="d-none d-sm-inline">{{ __('reports::module.aging.call') }}</span>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-admin::table.table>
            @endif
        </div>
    </x-admin::ui.card>
</div>
