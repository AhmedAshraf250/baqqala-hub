{{--
    A report's period, and its print button: a choice of stretch, two dates
    for one of the reader's own, and the stretch as it reads.

    Included with `$period` (the choice), `$from` and `$to` (the dates typed),
    `$choices` (choice => name), and `$range` (the ReportPeriod it gives).
--}}
<div class="report-toolbar no-print">
    <div class="d-flex flex-wrap align-items-end gap-2">
        <div>
            <label for="report-period" class="form-label small mb-1">{{ __('reports::module.period.label') }}</label>
            <select id="report-period" class="form-select" wire:model.live="period">
                @foreach ($choices as $value => $label)
                    <option value="{{ $value }}" wire:key="period-{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($period === 'custom')
            <div>
                <label for="report-from" class="form-label small mb-1">{{ __('reports::module.period.from') }}</label>
                <input id="report-from" type="date" class="form-control num" dir="ltr" wire:model.live="from">
            </div>
            <div>
                <label for="report-to" class="form-label small mb-1">{{ __('reports::module.period.to') }}</label>
                <input id="report-to" type="date" class="form-control num" dir="ltr" wire:model.live="to">
            </div>
        @endif
    </div>

    <button type="button" class="btn btn-outline-secondary" data-print>
        <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('reports::module.print') }}
    </button>
</div>

<p class="report-range" role="status">
    <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ $range->label() }}
</p>
