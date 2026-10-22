<?php

namespace App\Modules\Accounts\Domain\Services;

use App\Foundation\Money\Money;
use App\Foundation\Settings\SettingDefinition;
use App\Foundation\Settings\SettingStore;
use App\Foundation\Settings\SettingType;

/**
 * What the owner may change about accounts from the panel — what a line with
 * no product is called, when a debt counts as late, how long a mistake may be
 * put right alone — each read with its configured value as the default.
 *
 * Declared once, here: the settings screen draws its «Accounts» tab from
 * {@see definitions()}, and the module reads each value through the methods
 * below, never through `config()` directly.
 */
final class AccountSettings
{
    public const string FREE_LINE_NAME = 'accounts.free_line_name';

    public const string LATE_AFTER_DAYS = 'accounts.late_after_days';

    public const string DORMANT_AFTER_DAYS = 'accounts.dormant_after_days';

    public const string CORRECTION_WINDOW_MINUTES = 'accounts.correction_window_minutes';

    public const string DUPLICATE_WARNING_SECONDS = 'accounts.duplicate_warning_seconds';

    public const string ALLOW_OVERPAYMENT = 'accounts.allow_overpayment';

    public const string DEFAULT_CREDIT_LIMIT = 'accounts.default_credit_limit';

    public function __construct(private readonly SettingStore $store) {}

    /**
     * Every value the owner may change, in the order the tab shows them.
     *
     * @return list<SettingDefinition>
     */
    public static function definitions(): array
    {
        return [
            new SettingDefinition(
                self::FREE_LINE_NAME, SettingType::Text,
                label: 'accounts::module.settings.free_line_name',
                help: 'accounts::module.settings.free_line_name_help',
                placeholder: 'accounts::module.entry.misc',
                icon: 'pencil-square',
            ),
            new SettingDefinition(
                self::LATE_AFTER_DAYS, SettingType::Number,
                label: 'accounts::module.settings.late_after_days',
                help: 'accounts::module.settings.late_after_days_help',
                default: (int) config('accounts.late_after_days', 30),
                min: 1, max: 365, unit: 'accounts::module.settings.days', icon: 'hourglass-split',
            ),
            new SettingDefinition(
                self::DORMANT_AFTER_DAYS, SettingType::Number,
                label: 'accounts::module.settings.dormant_after_days',
                help: 'accounts::module.settings.dormant_after_days_help',
                default: (int) config('accounts.dormant_after_days', 90),
                min: 1, max: 1000, unit: 'accounts::module.settings.days', icon: 'moon',
            ),
            new SettingDefinition(
                self::CORRECTION_WINDOW_MINUTES, SettingType::Number,
                label: 'accounts::module.settings.correction_window',
                help: 'accounts::module.settings.correction_window_help',
                default: (int) config('accounts.correction_window_minutes', 15),
                min: 0, max: 1440, unit: 'accounts::module.settings.minutes', icon: 'arrow-counterclockwise',
            ),
            new SettingDefinition(
                self::DUPLICATE_WARNING_SECONDS, SettingType::Number,
                label: 'accounts::module.settings.duplicate_warning',
                help: 'accounts::module.settings.duplicate_warning_help',
                default: (int) config('accounts.duplicate_warning_seconds', 120),
                min: 0, max: 3600, unit: 'accounts::module.settings.seconds', icon: 'files',
            ),
            new SettingDefinition(
                self::ALLOW_OVERPAYMENT, SettingType::Toggle,
                label: 'accounts::module.settings.allow_overpayment',
                help: 'accounts::module.settings.allow_overpayment_help',
                default: (bool) config('accounts.allow_overpayment', true),
                icon: 'piggy-bank',
            ),
            new SettingDefinition(
                self::DEFAULT_CREDIT_LIMIT, SettingType::Money,
                label: 'accounts::module.settings.default_credit_limit',
                help: 'accounts::module.settings.default_credit_limit_help',
                default: (string) config('accounts.default_credit_limit', '0.00'),
                icon: 'shield-check',
            ),
        ];
    }

    /**
     * What a line with no product is called when the counter does not name
     * it: the owner's word, or the shop's usual one in the reader's language.
     */
    public function freeLineName(): string
    {
        return $this->value(self::FREE_LINE_NAME) ?? __('accounts::module.entry.misc');
    }

    public function lateAfterDays(): int
    {
        return $this->value(self::LATE_AFTER_DAYS);
    }

    public function dormantAfterDays(): int
    {
        return $this->value(self::DORMANT_AFTER_DAYS);
    }

    public function correctionWindowMinutes(): int
    {
        return $this->value(self::CORRECTION_WINDOW_MINUTES);
    }

    public function duplicateWarningSeconds(): int
    {
        return $this->value(self::DUPLICATE_WARNING_SECONDS);
    }

    public function allowsOverpayment(): bool
    {
        return $this->value(self::ALLOW_OVERPAYMENT);
    }

    public function defaultCreditLimit(): Money
    {
        return $this->value(self::DEFAULT_CREDIT_LIMIT);
    }

    private function value(string $key): mixed
    {
        foreach (self::definitions() as $setting) {
            if ($setting->key === $key) {
                return $this->store->value($setting);
            }
        }

        return null;
    }
}
