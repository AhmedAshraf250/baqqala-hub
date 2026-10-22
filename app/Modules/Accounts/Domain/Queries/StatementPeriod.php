<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Time\LocalClock;
use Carbon\CarbonImmutable;

/**
 * The stretch of time a statement covers, in business days: a day starts
 * when the owner says the business day does.
 */
enum StatementPeriod: string
{
    case ThisMonth = 'month';

    case Last30Days = '30';

    case Last3Months = '90';

    case Everything = 'all';

    case Between = 'custom';

    public function label(): string
    {
        return __('accounts::module.statement.period.'.$this->value);
    }

    /**
     * The first and last business day covered, or null for an open end.
     * `Between` takes the dates given, as typed (Y-m-d); an end before the
     * start is read as the two the other way round.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    public function days(LocalClock $clock, ?string $from = null, ?string $to = null): array
    {
        $today = $clock->today();

        return match ($this) {
            self::ThisMonth => [$today->startOfMonth(), $today],
            self::Last30Days => [$today->subDays(29), $today],
            self::Last3Months => [$today->subMonthsNoOverflow(3)->addDay(), $today],
            self::Everything => [null, null],
            self::Between => self::between($from, $to, $clock->timezone()),
        };
    }

    /**
     * The moments, in UTC, the covered business days run between: the
     * start of the first, and the start of the day after the last.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    public function bounds(LocalClock $clock, ?string $from = null, ?string $to = null): array
    {
        [$first, $last] = $this->days($clock, $from, $to);

        return [
            $first === null ? null : $clock->dayBounds($first)[0],
            $last === null ? null : $clock->dayBounds($last)[1],
        ];
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private static function between(?string $from, ?string $to, string $timezone): array
    {
        // Read on the shop's wall clock, so the date is the day the shop means.
        $read = static function (?string $date) use ($timezone): ?CarbonImmutable {
            if ($date === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                return null;
            }

            return CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        };

        [$first, $last] = [$read($from), $read($to)];

        if ($first !== null && $last !== null && $last->lessThan($first)) {
            [$first, $last] = [$last, $first];
        }

        return [$first, $last];
    }
}
