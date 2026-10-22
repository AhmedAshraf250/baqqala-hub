<?php

use App\Admin\Authorization\AdminPermission;
use App\Admin\Settings\AdminSettings;
use App\Admin\Settings\SettingsSection;
use App\Foundation\Area\Area;
use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Foundation\Money\Money;
use App\Foundation\Settings\SettingDefinition;
use App\Foundation\Settings\SettingStore;
use App\Foundation\Settings\SettingType;
use App\Foundation\Text\Numerals;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A settings tab drawn from the values a layer declared: a row for each, with
 * the control its type calls for, and its default beside it.
 *
 * Shown only to whoever may configure the panel, and `save()` checks again —
 * a Livewire action reaches the component directly. Each value is checked
 * against its own bounds and stored as its type writes it.
 */
new class extends Component {
    #[Locked]
    public string $section = '';

    /**
     * What each field holds, by its place in the tab.
     *
     * @var array<int, string|bool>
     */
    public array $values = [];

    /** Whether the last save went through, said once. */
    public bool $saved = false;

    public function mount(string $section, SettingStore $store): void
    {
        $this->section = $section;

        foreach ($this->settings as $index => $setting) {
            $value = $store->value($setting);

            $this->values[$index] = match ($setting->type) {
                SettingType::Toggle => (bool) $value,
                SettingType::Money => $value instanceof Money ? $value->toDecimalString() : '0.00',
                SettingType::Text => $store->has($setting->key) ? (string) $value : '',
                SettingType::Number => (string) $value,
            };
        }
    }

    #[Computed]
    public function tab(): ?SettingsSection
    {
        return app(AdminSettings::class)->sections()->firstWhere('key', $this->section);
    }

    /**
     * @return list<SettingDefinition>
     */
    #[Computed]
    public function settings(): array
    {
        return $this->tab?->settings ?? [];
    }

    /**
     * A setting's default, as a person reads it beside the field.
     */
    public function defaultOf(SettingDefinition $setting): ?string
    {
        return match ($setting->type) {
            SettingType::Toggle => __($setting->default ? 'shell.settings.declared.on' : 'shell.settings.declared.off'),
            SettingType::Money => Money::fromDecimalString((string) ($setting->default ?? '0'))->toDisplayString(),
            SettingType::Number => trim($setting->default.' '.($setting->unit ? __($setting->unit) : '')),
            SettingType::Text => $setting->placeholder ? __($setting->placeholder) : null,
        };
    }

    public function save(SettingStore $store, AuditTrail $audit): void
    {
        $administrator = Area::Admin->user();

        abort_unless($administrator?->can(AdminPermission::ManageSettings->value) === true, 403);

        $this->saved = false;
        $rules = [];
        $attributes = [];

        foreach ($this->settings as $index => $setting) {
            if ($setting->type !== SettingType::Toggle) {
                $this->values[$index] = trim(Numerals::toLatin((string) ($this->values[$index] ?? '')));
            }

            $rules["values.{$index}"] = match ($setting->type) {
                SettingType::Text => ['nullable', 'string', 'max:60'],
                SettingType::Number => ['required', 'integer', ...($setting->min === null ? [] : ["min:{$setting->min}"]), ...($setting->max === null ? [] : ["max:{$setting->max}"])],
                SettingType::Toggle => ['boolean'],
                SettingType::Money => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            };
            $attributes["values.{$index}"] = __($setting->label);
        }

        $this->validate($rules, attributes: $attributes);

        foreach ($this->settings as $index => $setting) {
            $change = $setting->change($store->value($setting), $this->values[$index]);

            $store->put($setting->key, $setting->store($this->values[$index]), (int) $administrator->getKey());

            if ($change !== null) {
                $audit->record($setting->owner().'.setting', null, __($setting->label), 'foundation.audit.setting', AuditAction::Updated, [$change]);
            }
        }

        $this->saved = true;
    }
}; ?>

<x-admin::ui.card :title="$this->tab ? $this->tab->title() : ''" :icon="$this->tab->icon ?? 'sliders'">
    @if ($this->tab)
        <p class="text-body-secondary">{{ $this->tab->description() }}</p>
    @endif

    <form wire:submit="save" novalidate>
        <div class="settings-rows">
            @foreach ($this->settings as $index => $setting)
                @php($id = 'setting-'.$section.'-'.$index)
                <x-admin::ui.setting-row :label="__($setting->label)" :help="$setting->help ? __($setting->help) : null" :icon="$setting->icon" :for="$id" wire:key="setting-{{ $setting->key }}">
                    @if ($setting->type->value === 'toggle')
                        <div class="form-check form-switch setting-switch m-0">
                            <input id="{{ $id }}" class="form-check-input" type="checkbox" role="switch" wire:model="values.{{ $index }}" @if ($setting->help) aria-describedby="{{ $id }}-help" @endif>
                        </div>
                    @else
                        <div class="input-group">
                            <input
                                id="{{ $id }}"
                                type="text"
                                @if ($setting->type->value === 'text')
                                    dir="auto"
                                    maxlength="60"
                                    placeholder="{{ $setting->placeholder ? __($setting->placeholder) : '' }}"
                                @else
                                    dir="ltr"
                                    inputmode="{{ $setting->type->value === 'money' ? 'decimal' : 'numeric' }}"
                                @endif
                                autocomplete="off"
                                wire:model="values.{{ $index }}"
                                aria-describedby="{{ $setting->help ? $id.'-help' : '' }}{{ $errors->has('values.'.$index) ? ' '.$id.'-error' : '' }}"
                                @if ($errors->has('values.'.$index)) aria-invalid="true" @endif
                                @class(['form-control', 'num' => $setting->type->value !== 'text', 'is-invalid' => $errors->has('values.'.$index)])
                            >
                            @if ($setting->type->value === 'money')
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                            @elseif ($setting->unit)
                                <span class="input-group-text">{{ __($setting->unit) }}</span>
                            @endif
                        </div>
                        @error('values.'.$index)<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endif

                    @if ($this->defaultOf($setting) !== null)
                        <div class="small text-body-secondary mt-1">{{ __('shell.settings.declared.default', ['value' => $this->defaultOf($setting)]) }}</div>
                    @endif
                </x-admin::ui.setting-row>
            @endforeach
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('shell.actions.save') }}
            </button>

            @if ($saved)
                <span class="text-success-emphasis small" role="status" wire:key="settings-saved-{{ $section }}">
                    <i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ __('shell.settings.declared.saved') }}
                </span>
            @endif
        </div>
    </form>
</x-admin::ui.card>
