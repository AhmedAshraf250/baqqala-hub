<?php

namespace App\Modules\Reports\Domain\Queries;

use App\Foundation\Time\LocalClock;
use Carbon\CarbonImmutable;

/**
 * The stretch of time a report covers, in business days — a day starts when
 * the owner says the business day does — and how it is cut into rows: by the
 * day for a month or so, by the month beyond that.
 */
final readonly class ReportPeriod
{
    /** The periods offered, the default first. */
    public const array CHOICES = ['month', 'today', 'week', 'last_month', 'year', 'custom'];

    /** Up to this many days, a report's rows are days; beyond, months. */
    private const int DAILY_UP_TO = 62;

    private function __construct(
        public CarbonImmutable $firstDay,
        public CarbonImmutable $lastDay,
    ) {}

    /**
     * A period by its name; `custom` takes the dates given, as typed (Y-m-d),
     * the other way round when the end is before the start, and never past
     * today.
     */
    public static function named(string $name, LocalClock $clock, string $from = '', string $to = ''): self
    {
        $today = $clock->today();

        [$first, $last] = match ($name) {
            'today' => [$today, $today],
            'week' => [$today->subDays(6), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'year' => [$today->startOfYear(), $today],
            'custom' => [self::read($from, $clock) ?? $today->startOfMonth(), self::read($to, $clock) ?? $today],
            default => [$today->startOfMonth(), $today],
        };

        if ($first->greaterThan($last)) {
            [$first, $last] = [$last, $first];
        }

        return new self($first, $last->greaterThan($today) ? $today : $last);
    }

    /**
     * The moments, in UTC, the period runs between: the start of its first
     * business day, and the start of the day after its last.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bounds(LocalClock $clock): array
    {
        return [$clock->dayBounds($this->firstDay)[0], $clock->dayBounds($this->lastDay)[1]];
    }

    public function days(): int
    {
        return (int) $this->firstDay->diffInDays($this->lastDay) + 1;
    }

    public function byMonth(): bool
    {
        return $this->days() > self::DAILY_UP_TO;
    }

    /**
     * The period cut into rows — days, or months — each with where it
     * starts in UTC; the last row ends where the period does.
     *
     * @return list<array{label: string, start: CarbonImmutable}>
     */
    public function steps(LocalClock $clock): array
    {
        $steps = [];
        $day = $this->firstDay;

        while ($day->lessThanOrEqualTo($this->lastDay)) {
            $steps[] = [
                'label' => $this->byMonth() ? $day->isoFormat('MMMM YYYY') : $day->isoFormat('ddd D MMMM'),
                'start' => $clock->dayBounds($day)[0],
            ];

            $day = $this->byMonth() ? $day->addMonthNoOverflow()->startOfMonth() : $day->addDay();
        }

        return $steps;
    }

    /**
     * How the period reads at the head of a report.
     */
    public function label(): string
    {

        return $this->firstDay->isSameDay($this->lastDay)
            ? $this->firstDay->isoFormat('D MMMM YYYY')
            : $this->firstDay->isoFormat('D MMMM YYYY').' — '.$this->lastDay->isoFormat('D MMMM YYYY');
    }

    private static function read(string $date, LocalClock $clock): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, $clock->timezone()) ?: null;
    }
}
