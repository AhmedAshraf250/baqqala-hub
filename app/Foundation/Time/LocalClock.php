<?php

namespace App\Foundation\Time;

use App\Foundation\Settings\SettingStore;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * The time as the people using the application read it.
 *
 * Every moment is stored in UTC, and stays there. This is where a stored
 * moment becomes the time on the wall where the business is, and where
 * «today» begins and ends. Two settings decide it, both changed from the
 * panel and defaulting to configuration:
 *
 * - the time zone, `Africa/Cairo` unless told otherwise, with its daylight
 *   saving applied by PHP's own zone data;
 * - when the business day starts. A shop that closes at two in the morning
 *   counts a sale at half past one in the day it opened, so its daily totals
 *   match the till it counts at closing. Midnight unless told otherwise.
 *
 * Changing either never rewrites anything stored: it changes how stored
 * moments are shown and grouped into days.
 */
#[Scoped]
final class LocalClock
{
    public const string TIMEZONE = 'foundation.timezone';

    public const string DAY_STARTS_AT = 'foundation.day_starts_at';

    private ?string $timezone = null;

    private ?string $dayStartsAt = null;

    public function __construct(
        private readonly SettingStore $settings,
        private readonly Config $config,
    ) {}

    /**
     * The local time zone, as an IANA identifier.
     */
    public function timezone(): string
    {
        return $this->timezone ??= (string) $this->settings->get(
            self::TIMEZONE,
            $this->config->get('foundation.time.timezone', 'UTC'),
        );
    }

    /**
     * When the business day starts, as a local `HH:MM`.
     */
    public function dayStartsAt(): string
    {
        return $this->dayStartsAt ??= (string) $this->settings->get(
            self::DAY_STARTS_AT,
            $this->config->get('foundation.time.day_starts_at', '00:00'),
        );
    }

    /**
     * Now, on the local wall clock.
     */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /**
     * A stored moment on the local wall clock.
     */
    public function local(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone($this->timezone());
    }

    /**
     * The business day a moment belongs to, as local midnight of its date.
     */
    public function businessDate(CarbonInterface $moment): CarbonImmutable
    {
        [$hours, $minutes] = $this->dayStart();

        return $this->local($moment)
            ->subHours($hours)
            ->subMinutes($minutes)
            ->startOfDay();
    }

    /**
     * Today's business day.
     */
    public function today(): CarbonImmutable
    {
        return $this->businessDate($this->now());
    }

    /**
     * Where a business day begins and ends, in UTC, for a query: from the
     * start (inclusive) to the next day's start (exclusive). A day that
     * daylight saving shortens or lengthens is 23 or 25 hours long, as it was
     * on the wall.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dayBounds(CarbonInterface $businessDate): array
    {
        $date = CarbonImmutable::instance($businessDate)->setTimezone($this->timezone())->format('Y-m-d');
        $start = CarbonImmutable::parse("{$date} {$this->dayStartsAt()}", $this->timezone());
        $end = CarbonImmutable::parse($start->addDay()->format('Y-m-d')." {$this->dayStartsAt()}", $this->timezone());

        return [$start->utc(), $end->utc()];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dayStart(): array
    {
        [$hours, $minutes] = array_map('intval', explode(':', $this->dayStartsAt()) + [1 => '0']);

        return [$hours, $minutes];
    }
}
