<?php

namespace App\Foundation\Settings;

use App\Foundation\Audit\AuditChange;
use App\Foundation\Money\Money;

/**
 * One value an owner may change from the panel: what it is called, what kind
 * of value it holds, the bounds it keeps to, and what it is until changed.
 *
 * Declared by the layer that reads the value, with its configured value as
 * the default, so an installation that never opened the settings screen
 * behaves as its configuration says. The strings are translation keys in the
 * declaring layer's own file; the settings screen draws the control from the
 * type, and reads and writes the value through {@see SettingStore}.
 */
final readonly class SettingDefinition
{
    /**
     * @param  string  $key  Stable and namespaced by its layer: `accounts.late_after_days`.
     * @param  string  $label  A translation key.
     * @param  string|null  $help  A translation key: what changes when this does.
     * @param  mixed  $default  The configured value; for text, null means «the placeholder's words».
     * @param  string|null  $unit  A translation key for what a number counts, shown beside it.
     * @param  string|null  $placeholder  A translation key, shown in an empty text field.
     * @param  string  $icon  A Bootstrap Icons name, without the `bi-` prefix.
     */
    public function __construct(
        public string $key,
        public SettingType $type,
        public string $label,
        public ?string $help = null,
        public mixed $default = null,
        public ?int $min = null,
        public ?int $max = null,
        public ?string $unit = null,
        public ?string $placeholder = null,
        public string $icon = 'sliders',
    ) {}

    /**
     * A stored or default value, as the type reads it.
     */
    public function cast(mixed $value): mixed
    {
        return match ($this->type) {
            SettingType::Text => is_string($value) && trim($value) !== '' ? trim($value) : null,
            SettingType::Number => $this->bounded(is_numeric($value) ? (int) $value : (int) $this->default),
            SettingType::Toggle => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            SettingType::Money => $value instanceof Money ? $value : Money::fromDecimalString(is_string($value) && $value !== '' ? $value : (string) ($this->default ?? '0')),
        };
    }

    /**
     * A value as it is stored: plain, so the store holds JSON a person can read.
     */
    public function store(mixed $value): mixed
    {
        return match ($this->type) {
            SettingType::Money => $value instanceof Money ? $value->toDecimalString() : (string) $value,
            default => $this->cast($value),
        };
    }

    /**
     * A value as the change log shows it: a figure, a name, or — for on and
     * off — a translation key, so the log reads in its reader's language.
     */
    public function shown(mixed $value): ?string
    {
        $value = $this->cast($value);

        return match ($this->type) {
            SettingType::Toggle => $value ? 'foundation.audit.on' : 'foundation.audit.off',
            SettingType::Money => $value instanceof Money ? $value->toDisplayString() : null,
            SettingType::Number => (string) $value,
            SettingType::Text => $value,
        };
    }

    /**
     * The change from one value to another, for the change log; null when
     * nothing changed. Its name is the setting's own label.
     */
    public function change(mixed $from, mixed $to): ?AuditChange
    {
        $before = $this->shown($from);
        $after = $this->shown($to);

        return $before === $after ? null : new AuditChange($this->label, $before, $after, $this->type === SettingType::Toggle);
    }

    /**
     * Whose it is — the first part of its key — for the change log's subject.
     */
    public function owner(): string
    {
        return strstr($this->key, '.', true) ?: $this->key;
    }

    private function bounded(int $value): int
    {
        return max($this->min ?? PHP_INT_MIN, min($this->max ?? PHP_INT_MAX, $value));
    }
}
