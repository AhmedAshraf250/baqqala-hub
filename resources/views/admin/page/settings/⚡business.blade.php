<?php

use App\Admin\Authorization\AdminPermission;
use App\Foundation\Area\Area;
use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditChange;
use App\Foundation\Audit\AuditTrail;
use App\Foundation\Settings\SettingStore;
use App\Foundation\Time\LocalClock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The business tab: where the business is in time.
 *
 * Moments stay stored in UTC; this decides how they are shown and where the
 * business day starts. The tab is listed only for whoever may configure the
 * panel, and `save()` checks again, since a Livewire action reaches the
 * component directly.
 */
new class extends Component {
    /**
     * Zones most installations pick, offered before the full list.
     *
     * @var list<string>
     */
    private const array COMMON = [
        'Africa/Cairo', 'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Amman',
        'Europe/London', 'Europe/Berlin', 'America/New_York',
    ];

    public string $timezone = '';

    public string $dayStartsAt = '';

    public function mount(LocalClock $clock): void
    {
        $this->timezone = $clock->timezone();
        $this->dayStartsAt = $clock->dayStartsAt();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function commonTimezones(): array
    {
        return $this->labelled(self::COMMON);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function otherTimezones(): array
    {
        return $this->labelled(array_values(array_diff(DateTimeZone::listIdentifiers(), self::COMMON)));
    }

    /**
     * The hours a business day may start at: midnight, or the small hours for
     * a business that closes after it.
     *
     * @return list<string>
     */
    #[Computed]
    public function dayStarts(): array
    {
        return array_map(static fn (int $hour): string => sprintf('%02d:00', $hour), range(0, 6));
    }

    /**
     * The time now in the zone being chosen, so the choice can be checked
     * against a wall clock before it is saved.
     */
    #[Computed]
    public function preview(): ?string
    {
        if (! in_array($this->timezone, DateTimeZone::listIdentifiers(), true)) {
            return null;
        }

        return CarbonImmutable::now($this->timezone)->locale(app()->getLocale())->isoFormat('dddd D MMMM · h:mm a');
    }

    public function save(SettingStore $settings, LocalClock $clock, AuditTrail $audit): void
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator?->can(AdminPermission::ManageSettings->value) === true, 403);

        $validated = $this->validate([
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'dayStartsAt' => ['required', 'string', Rule::in($this->dayStarts)],
        ]);

        foreach ([
            [LocalClock::TIMEZONE, 'shell.settings.timezone', $clock->timezone(), $validated['timezone']],
            [LocalClock::DAY_STARTS_AT, 'shell.settings.day_starts_at', $clock->dayStartsAt(), $validated['dayStartsAt']],
        ] as [$key, $label, $before, $after]) {
            if ($before !== $after) {
                $audit->record('foundation.setting', null, __($label), 'foundation.audit.setting', AuditAction::Updated, [new AuditChange($label, $before, $after)]);
            }

            $settings->put($key, $after, $administrator->getKey());
        }

        session()->flash('success', __('shell.settings.business_saved'));

        // Every time on every screen reads the zone, so the whole page is
        // drawn again rather than this pane alone.
        $this->redirectRoute('admin.settings', ['tab' => 'business'], navigate: false);
    }

    /**
     * Each zone with its offset from UTC today, so «UTC+03:00» settles which
     * of two similar names is meant.
     *
     * @param  list<string>  $zones
     * @return array<string, string>
     */
    private function labelled(array $zones): array
    {
        $labelled = [];

        foreach ($zones as $zone) {
            $labelled[$zone] = sprintf('%s (UTC%s)', $zone, CarbonImmutable::now($zone)->format('P'));
        }

        return $labelled;
    }
}; ?>

<x-admin::ui.card :title="__('shell.settings.business')" icon="shop">
    <p class="text-body-secondary">{{ __('shell.settings.business_description') }}</p>

    <form wire:submit="save" novalidate>
        <div class="settings-rows">
            <x-admin::ui.setting-row :label="__('shell.settings.timezone')" icon="globe2" for="business-timezone-toggle" label-id="business-timezone-label" wide>
                <x-slot:details>
                    @if ($this->preview)
                        <div class="small mt-1">
                            <span class="badge rounded-pill bg-body-tertiary text-body-emphasis border fw-normal">
                                <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ __('shell.settings.timezone_now', ['time' => $this->preview]) }}
                            </span>
                        </div>
                    @endif

                    {{-- Filled and shown by resources/js/admin/modules/device-timezone.js:
                         only the browser knows which zone its device is set to. --}}
                    <div class="small text-body-secondary mt-1" data-device-timezone data-target="business-timezone" wire:ignore hidden>
                        {{ __('shell.settings.device_timezone') }}
                        <span dir="ltr" data-device-timezone-name></span>
                        <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-device-timezone-use>
                            {{ __('shell.settings.use_device_timezone') }}
                        </button>
                    </div>
                </x-slot:details>

                {{-- The zones most picked first, then every other: one list, searched. --}}
                <x-admin::form.search-select
                    id="business-timezone"
                    wire:model.live="timezone"
                    :options="$this->commonTimezones + $this->otherTimezones"
                    :selected="$timezone"
                    :invalid="$errors->has('timezone')"
                    :describedby="$errors->has('timezone') ? 'business-timezone-error' : null"
                />
                @error('timezone')<div id="business-timezone-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </x-admin::ui.setting-row>

            <x-admin::ui.setting-row :label="__('shell.settings.day_starts_at')" :help="__('shell.settings.day_starts_at_help')" icon="sunrise" for="business-day-starts">
                <select
                    id="business-day-starts"
                    wire:model="dayStartsAt"
                    aria-describedby="business-day-starts-help{{ $errors->has('dayStartsAt') ? ' business-day-starts-error' : '' }}"
                    @if ($errors->has('dayStartsAt')) aria-invalid="true" @endif
                    @class(['form-select', 'is-invalid' => $errors->has('dayStartsAt')])
                >
                    @foreach ($this->dayStarts as $time)
                        <option value="{{ $time }}" wire:key="day-start-{{ $time }}">
                            {{ $time === '00:00' ? __('shell.settings.day_starts_midnight') : $time }}
                        </option>
                    @endforeach
                </select>
                @error('dayStartsAt')<div id="business-day-starts-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </x-admin::ui.setting-row>
        </div>

        <div class="alert alert-light border d-flex align-items-start gap-2 small mt-3 mb-3">
            <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
            <span>{{ __('shell.settings.display_only') }}</span>
        </div>

        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
            {{ __('shell.actions.save') }}
        </button>
    </form>
</x-admin::ui.card>
