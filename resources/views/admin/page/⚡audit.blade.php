<?php

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditEntry;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Time\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The change log: who changed what, and when — names and phones, prices,
 * credit limits, settings — the latest first, a day at a time, each change
 * field by field. Money is not here; the ledger keeps every amount.
 *
 * It knows nothing of what it lists: each line carries its kind and its
 * fields' names as translation keys of whoever wrote it, and is drawn as is.
 * The filters are in the page's address.
 */
new #[Layout('admin::layout.app')] class extends Component {
    use WithPagination;

    /** The filters this log reads from its address. */
    private const array FILTERS = ['q', 'person', 'subject', 'action', 'period'];

    private const int PAGE = 50;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: null)]
    public ?int $person = null;

    #[Url(except: '')]
    public string $subject = '';

    #[Url(except: '')]
    public string $action = '';

    #[Url(except: 'month')]
    public string $period = 'month';

    public function rendering(View $view): void
    {
        $view->layoutData(['title' => __('shell.navigation.audit')]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, AuditEntry>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        $since = match ($this->period) {
            'today' => app(LocalClock::class)->dayBounds(app(LocalClock::class)->today())[0],
            'week' => CarbonImmutable::now()->subDays(7),
            'all' => null,
            default => CarbonImmutable::now()->subDays(30),
        };

        return AuditEntry::query()
            ->when($since !== null, static fn (Builder $query) => $query->where('created_at', '>=', $since))
            ->when($this->person !== null, fn (Builder $query) => $query->where('user_id', $this->person))
            ->when($this->subject !== '', fn (Builder $query) => $query->where('subject', $this->subject))
            ->when(AuditAction::tryFrom($this->action) !== null, fn (Builder $query) => $query->where('action', $this->action))
            ->when(trim($this->q) !== '', fn (Builder $query) => $query->where('label', 'like', '%'.trim($this->q).'%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PAGE);
    }

    /**
     * Who appears in the log, for the filter.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function people(): array
    {
        return AdminUser::query()
            ->whereIn('id', AuditEntry::query()->select('user_id')->whereNotNull('user_id')->distinct())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * What kinds of thing appear in the log, for the filter: subject => its
     * name in the reader's language.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function kinds(): array
    {
        return AuditEntry::query()
            ->select(['subject', 'kind'])
            ->distinct()
            ->get()
            ->mapWithKeys(static fn (AuditEntry $entry): array => [$entry->subject => __($entry->kind)])
            ->sort()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function actions(): array
    {
        return collect(AuditAction::cases())->mapWithKeys(static fn (AuditAction $action): array => [$action->value => $action->label()])->all();
    }

    /**
     * Who made the changes on this page, by id.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function names(): array
    {
        $ids = $this->entries->getCollection()->pluck('user_id')->filter()->unique()->values()->all();

        return AdminUser::query()->whereKey($ids)->pluck('name', 'id')->map(static fn (mixed $name): string => (string) $name)->all();
    }

    /**
     * The business day a change belongs to, as its heading.
     */
    public function day(CarbonImmutable $at): string
    {
        return app(LocalClock::class)->businessDate($at)->locale(app()->getLocale())->isoFormat('dddd D MMMM YYYY');
    }

    public function time(CarbonImmutable $at): string
    {
        return local_time($at)->locale(app()->getLocale())->isoFormat('LT');
    }

    /**
     * A value as it reads: translated when it was stored as a key, «empty»
     * when there was none.
     */
    public function value(?string $value, bool $translated): string
    {
        if ($value === null || $value === '') {
            return __('foundation.audit.empty');
        }

        return $translated ? __($value) : $value;
    }

    public function icon(AuditAction $action): string
    {
        return match ($action) {
            AuditAction::Created => 'plus-lg',
            AuditAction::Updated => 'pencil',
            AuditAction::Archived => 'archive',
            AuditAction::Restored => 'arrow-counterclockwise',
            AuditAction::Deleted => 'trash',
            AuditAction::Purged => 'x-octagon',
        };
    }

    public function tone(AuditAction $action): string
    {
        return match ($action) {
            AuditAction::Created => 'success',
            AuditAction::Updated => 'primary',
            AuditAction::Archived => 'warning',
            AuditAction::Restored => 'info',
            AuditAction::Deleted => 'danger',
            AuditAction::Purged => 'danger',
        };
    }
}; ?>

<div class="d-grid gap-3">
    <p class="text-body-secondary mb-0">{{ __('shell.audit.intro') }}</p>

    {{-- Who, what, what happened, and when. --}}
    <div class="row g-2 align-items-end" role="group" aria-label="{{ __('shell.audit.search') }}">
        <div class="col-12 col-lg-4">
            <label for="audit-search" class="form-label small mb-1">{{ __('shell.audit.search') }}</label>
            <input id="audit-search" type="search" class="form-control" wire:model.live.debounce.250ms="q" placeholder="{{ __('shell.audit.search_placeholder') }}" autocomplete="off" dir="auto">
        </div>
        <div class="col-6 col-lg-2">
            <label for="audit-person" class="form-label small mb-1">{{ __('shell.audit.filter_person') }}</label>
            <select id="audit-person" class="form-select" wire:model.live="person">
                <option value="">{{ __('shell.audit.everyone') }}</option>
                @foreach ($this->people as $id => $name)
                    <option value="{{ $id }}" wire:key="person-{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="audit-kind" class="form-label small mb-1">{{ __('shell.audit.filter_kind') }}</label>
            <select id="audit-kind" class="form-select" wire:model.live="subject">
                <option value="">{{ __('shell.audit.all_kinds') }}</option>
                @foreach ($this->kinds as $key => $label)
                    <option value="{{ $key }}" wire:key="kind-{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="audit-action" class="form-label small mb-1">{{ __('shell.audit.filter_action') }}</label>
            <select id="audit-action" class="form-select" wire:model.live="action">
                <option value="">{{ __('shell.audit.all_actions') }}</option>
                @foreach ($this->actions as $value => $label)
                    <option value="{{ $value }}" wire:key="action-{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="audit-period" class="form-label small mb-1">{{ __('shell.audit.filter_period') }}</label>
            <select id="audit-period" class="form-select" wire:model.live="period">
                @foreach (['today', 'week', 'month', 'all'] as $choice)
                    <option value="{{ $choice }}" wire:key="period-{{ $choice }}">{{ __('shell.audit.period.'.$choice) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <p class="small fw-semibold mb-0" role="status">{{ trans_choice('shell.audit.count', $this->entries->total(), ['count' => $this->entries->total()]) }}</p>

    <div wire:loading.delay.class="is-refreshing" wire:target="q,person,subject,action,period,gotoPage,nextPage,previousPage">
        @if ($this->entries->isEmpty())
            <x-admin::ui.card>
                <x-admin::ui.empty-state icon="clock-history" :title="__('shell.audit.empty_title')" :description="__('shell.audit.empty_description')" />
            </x-admin::ui.card>
        @else
            {{-- AdminLTE's timeline: a label a day, a card a change. --}}
            <div class="timeline audit-timeline">
                @foreach ($this->entries as $entry)
                    @if ($loop->first || $this->day($entry->created_at) !== $this->day($this->entries[$loop->index - 1]->created_at))
                        <div class="time-label" wire:key="day-{{ $entry->id }}">
                            <span class="text-bg-secondary">{{ $this->day($entry->created_at) }}</span>
                        </div>
                    @endif

                    <div wire:key="audit-{{ $entry->id }}">
                        <i class="timeline-icon bi bi-{{ $this->icon($entry->action) }} text-bg-{{ $this->tone($entry->action) }}" aria-hidden="true"></i>

                        <div class="timeline-item">
                            <span class="time num" dir="ltr"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ $this->time($entry->created_at) }}</span>

                            <h2 class="timeline-header">
                                <span class="badge bg-{{ $this->tone($entry->action) }}-subtle text-{{ $this->tone($entry->action) }}-emphasis me-1">{{ $entry->action->label() }}</span>
                                <span class="text-body-secondary">{{ __($entry->kind) }}:</span>
                                <span class="fw-semibold text-body-emphasis" dir="auto">{{ $entry->label }}</span>
                            </h2>

                            @if ($entry->changeList() !== [])
                                <div class="timeline-body">
                                    <ul class="audit-changes">
                                        @foreach ($entry->changeList() as $change)
                                            <li>
                                                <span class="text-body-secondary">{{ __($change->label) }}:</span>
                                                @if ($entry->action->value === 'created')
                                                    <strong class="audit-to" dir="auto">{{ $this->value($change->to, $change->translated) }}</strong>
                                                @else
                                                    {{-- Drawn as «was → is»; read as «from … to …». --}}
                                                    <span aria-hidden="true">
                                                        <del class="audit-from" dir="auto">{{ $this->value($change->from, $change->translated) }}</del>
                                                        <i class="bi bi-arrow-{{ is_rtl() ? 'left' : 'right' }} text-body-secondary mx-1"></i>
                                                        <strong class="audit-to" dir="auto">{{ $this->value($change->to, $change->translated) }}</strong>
                                                    </span>
                                                    <span class="visually-hidden">{{ __('shell.audit.from_to', ['from' => $this->value($change->from, $change->translated), 'to' => $this->value($change->to, $change->translated)]) }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="timeline-footer small text-body-secondary">
                                <i class="bi bi-person me-1" aria-hidden="true"></i>{{ __('shell.audit.by', ['name' => $entry->user_id === null ? __('foundation.audit.nobody') : ($this->names[$entry->user_id] ?? '—')]) }}
                            </div>
                        </div>
                    </div>
                @endforeach
                <div aria-hidden="true">
                    <i class="timeline-icon bi bi-clock-history text-bg-secondary"></i>
                </div>
            </div>

            {{ $this->entries->links('admin::table.pagination') }}
        @endif
    </div>
</div>
