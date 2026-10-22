{{--
    The few warnings an account in the list may carry, each only when it
    matters: a debt not paid towards for a while, a balance past the limit,
    a debt on an account that went quiet. Words and an icon, never colour
    alone.

    `<x-accounts::admin.account-alerts :row="$row" />` — a CustomerAccountRow.
--}}
@props(['row'])

@if ($row->hasWarnings())
    <div {{ $attributes->class(['d-flex flex-wrap gap-1']) }}>
        @if ($row->lateDays !== null)
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle d-inline-flex align-items-center gap-1">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                {{ __($row->hasPaid ? 'accounts::module.list.alert_late_paid' : 'accounts::module.list.alert_late_never', ['days' => $row->lateDays]) }}
            </span>
        @endif

        @if ($row->isOverLimit())
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle d-inline-flex align-items-center gap-1">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                {{ __('accounts::module.list.alert_over_limit', ['over' => $row->overLimitBy()->toDisplayString(), 'limit' => $row->creditLimit->toDisplayString()]) }}
            </span>
        @endif

        @if ($row->dormantDays !== null)
            <span class="badge bg-secondary-subtle text-body-emphasis border border-secondary-subtle d-inline-flex align-items-center gap-1">
                <i class="bi bi-moon" aria-hidden="true"></i>
                {{ __('accounts::module.list.alert_dormant', ['days' => $row->dormantDays]) }}
            </span>
        @endif
    </div>
@endif
