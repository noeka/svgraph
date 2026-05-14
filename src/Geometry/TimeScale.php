<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Geometry;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;

/**
 * A linear scale for time/date axes. The domain is expressed as unix seconds
 * (with microsecond precision) so the parent `Scale::map()` works unchanged;
 * `mapDate()` is the typed entry point.
 *
 * `timeTicks()` chooses a "nice" interval (seconds → minutes → hours → days
 * → months → years) sized to land roughly the requested number of ticks
 * across the range, snapping to whole-unit boundaries so labels read cleanly.
 *
 * `formatTick()` formats a tick via `IntlDateFormatter` honouring the given
 * locale and timezone. When `ext-intl` is unavailable (or `useIntl: false` is
 * passed), it falls back to `DateTimeInterface::format()` with a PHP format
 * string equivalent of the bucket's ICU pattern.
 */
final readonly class TimeScale extends Scale
{
    public DateTimeImmutable $start;
    public DateTimeImmutable $end;
    public bool $useIntl;

    public function __construct(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        float $rangeStart,
        float $rangeEnd,
        public ?string $locale = null,
        public ?DateTimeZone $timezone = null,
        public ?string $format = null,
        ?bool $useIntl = null,
    ) {
        $startTs = (float) $start->format('U.u');
        $endTs = (float) $end->format('U.u');

        if ($startTs >= $endTs) {
            $endTs = $startTs + 1.0;
        }

        parent::__construct($startTs, $endTs, $rangeStart, $rangeEnd);

        $this->start = $start;
        $this->end = $end;
        $this->useIntl = $useIntl ?? class_exists(IntlDateFormatter::class);
    }

    /**
     * Build a TimeScale from any iterable of DateTimeInterface values, taking
     * the min/max as the domain endpoints.
     *
     * @param iterable<DateTimeInterface> $values
     */
    public static function fromValues(
        iterable $values,
        float $rangeStart,
        float $rangeEnd,
        ?string $locale = null,
        DateTimeZone|string|null $timezone = null,
        ?string $format = null,
        ?bool $useIntl = null,
    ): self {
        $collected = iterator_to_array($values, preserve_keys: false);

        $min = new DateTimeImmutable('@0');
        $max = new DateTimeImmutable('@1');

        if ($collected !== []) {
            $min = $collected[0];
            $max = $collected[0];

            for ($i = 1, $n = count($collected); $i < $n; $i++) {
                if ($collected[$i] < $min) {
                    $min = $collected[$i];
                }

                if ($collected[$i] > $max) {
                    $max = $collected[$i];
                }
            }
        }

        $tz = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;

        $start = DateTimeImmutable::createFromInterface($min);
        $end = DateTimeImmutable::createFromInterface($max);

        if ($tz instanceof \DateTimeZone) {
            $start = $start->setTimezone($tz);
            $end = $end->setTimezone($tz);
        }

        return new self($start, $end, $rangeStart, $rangeEnd, $locale, $tz, $format, $useIntl);
    }

    public function mapDate(DateTimeInterface $when): float
    {
        return $this->map((float) $when->format('U.u'));
    }

    /**
     * "Nice" datetime ticks across the domain. The interval is selected so
     * the count lands close to (but never exceeds) the requested $count, and
     * each tick is snapped to a whole-unit boundary in the configured
     * timezone.
     *
     * @return list<DateTimeImmutable>
     */
    public function timeTicks(int $count = 5): array
    {
        if ($count < 2) {
            $count = 2;
        }

        $rangeSec = $this->domainMax - $this->domainMin;

        if ($rangeSec <= 0.0) {
            return [$this->boundary($this->start)];
        }

        $bucket = $this->pickBucket($rangeSec / ($count - 1));

        return $this->generateTicks($bucket);
    }

    /**
     * Format a tick. When the user supplied a `format`, that wins; otherwise
     * the bucket-appropriate pattern is used (ICU under Intl, PHP format
     * string in the fallback).
     */
    public function formatTick(DateTimeInterface $when, int $count = 5): string
    {
        if ($count < 2) {
            $count = 2;
        }

        $rangeSec = $this->domainMax - $this->domainMin;
        $approxStep = $rangeSec > 0.0 ? $rangeSec / ($count - 1) : 1.0;
        $bucket = $this->pickBucket($approxStep);

        $tz = $this->timezone ?? $when->getTimezone();

        if ($this->useIntl && class_exists(IntlDateFormatter::class)) {
            return $this->formatWithIntl($when, $bucket['icu'], $tz);
        }

        $phpFormat = $this->format ?? $bucket['php'];
        $whenInTz = DateTimeImmutable::createFromInterface($when)->setTimezone($tz);

        return $whenInTz->format($phpFormat);
    }

    /**
     * @return array{unit: string, step: int, sec: float, icu: string, php: string}
     */
    private function pickBucket(float $approxStepSec): array
    {
        $best = $this->buckets()[0];

        $bestDiff = INF;

        foreach ($this->buckets() as $b) {
            $diff = abs(log10(max($b['sec'], 1e-9)) - log10(max($approxStepSec, 1e-9)));

            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $b;
            }
        }

        return $best;
    }

    /**
     * @param array{unit: string, step: int, sec: float, icu: string, php: string} $bucket
     * @return list<DateTimeImmutable>
     */
    private function generateTicks(array $bucket): array
    {
        $tz = $this->timezone ?? $this->start->getTimezone();

        $startInTz = $this->start->setTimezone($tz);
        $endInTz = $this->end->setTimezone($tz);

        $cur = $this->snapDown($startInTz, $bucket['unit'], $bucket['step']);

        $ticks = [];

        $guard = 0;

        while ($cur <= $endInTz && $guard < 1024) {
            if ($cur >= $startInTz) {
                $ticks[] = $cur;
            }

            $cur = $this->advance($cur, $bucket['unit'], $bucket['step']);

            $guard++;
        }

        return $ticks;
    }

    private function snapDown(DateTimeImmutable $d, string $unit, int $step): DateTimeImmutable
    {
        $h = (int) $d->format('H');
        $m = (int) $d->format('i');
        $s = (int) $d->format('s');
        $year = (int) $d->format('Y');
        $month = (int) $d->format('n');

        return match ($unit) {
            'second' => $d->setTime($h, $m, intdiv($s, $step) * $step),
            'minute' => $d->setTime($h, intdiv($m, $step) * $step, 0),
            'hour'   => $d->setTime(intdiv($h, $step) * $step, 0, 0),
            'day'    => $d->setTime(0, 0, 0),
            'month'  => $d->setDate($year, intdiv($month - 1, $step) * $step + 1, 1)->setTime(0, 0, 0),
            'year'   => $d->setDate(intdiv($year, $step) * $step, 1, 1)->setTime(0, 0, 0),
            default  => $d,
        };
    }

    private function advance(DateTimeImmutable $d, string $unit, int $step): DateTimeImmutable
    {
        $modify = match ($unit) {
            'second' => "+{$step} seconds",
            'minute' => "+{$step} minutes",
            'hour'   => "+{$step} hours",
            'day'    => "+{$step} days",
            'month'  => "+{$step} months",
            'year'   => "+{$step} years",
            default  => '+1 day',
        };

        return $d->modify($modify);
    }

    private function boundary(DateTimeImmutable $d): DateTimeImmutable
    {
        $tz = $this->timezone ?? $d->getTimezone();

        return $d->setTimezone($tz);
    }

    private function formatWithIntl(DateTimeInterface $when, string $icu, DateTimeZone $tz): string
    {
        $pattern = $this->format ?? $icu;

        // ICU rejects the bare "Z" zone name PHP uses for Zulu time; UTC is the
        // canonical equivalent it does accept.
        $tzName = $tz->getName();
        $intlTz = $tzName === 'Z' ? new DateTimeZone('UTC') : $tz;

        $fmt = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $intlTz,
            IntlDateFormatter::GREGORIAN,
            $pattern,
        );

        $out = $fmt->format($when);

        return $out === false ? $when->format(DateTimeInterface::ATOM) : $out;
    }

    /**
     * Candidate intervals, sorted by length. `pickBucket()` picks the entry
     * closest (in log10) to the requested step so we always land in the
     * right ballpark, never one bucket too coarse or too fine.
     *
     * @return list<array{unit: string, step: int, sec: float, icu: string, php: string}>
     */
    private function buckets(): array
    {
        $second = 1.0;
        $minute = 60.0;
        $hour = 3600.0;
        $day = 86400.0;
        $month = 2_629_746.0; // average Gregorian month
        $year = 31_556_952.0; // average Gregorian year

        return [
            ['unit' => 'second', 'step' => 1,  'sec' => $second,        'icu' => 'HH:mm:ss',     'php' => 'H:i:s'],
            ['unit' => 'second', 'step' => 5,  'sec' => 5 * $second,    'icu' => 'HH:mm:ss',     'php' => 'H:i:s'],
            ['unit' => 'second', 'step' => 15, 'sec' => 15 * $second,   'icu' => 'HH:mm:ss',     'php' => 'H:i:s'],
            ['unit' => 'second', 'step' => 30, 'sec' => 30 * $second,   'icu' => 'HH:mm:ss',     'php' => 'H:i:s'],
            ['unit' => 'minute', 'step' => 1,  'sec' => $minute,        'icu' => 'HH:mm',        'php' => 'H:i'],
            ['unit' => 'minute', 'step' => 5,  'sec' => 5 * $minute,    'icu' => 'HH:mm',        'php' => 'H:i'],
            ['unit' => 'minute', 'step' => 15, 'sec' => 15 * $minute,   'icu' => 'HH:mm',        'php' => 'H:i'],
            ['unit' => 'minute', 'step' => 30, 'sec' => 30 * $minute,   'icu' => 'HH:mm',        'php' => 'H:i'],
            ['unit' => 'hour',   'step' => 1,  'sec' => $hour,          'icu' => 'HH:mm',        'php' => 'H:i'],
            ['unit' => 'hour',   'step' => 3,  'sec' => 3 * $hour,      'icu' => 'HH:mm',        'php' => 'H:i'],
            ['unit' => 'hour',   'step' => 6,  'sec' => 6 * $hour,      'icu' => 'MMM d HH:mm',  'php' => 'M j H:i'],
            ['unit' => 'hour',   'step' => 12, 'sec' => 12 * $hour,     'icu' => 'MMM d HH:mm',  'php' => 'M j H:i'],
            ['unit' => 'day',    'step' => 1,  'sec' => $day,           'icu' => 'MMM d',        'php' => 'M j'],
            ['unit' => 'day',    'step' => 2,  'sec' => 2 * $day,       'icu' => 'MMM d',        'php' => 'M j'],
            ['unit' => 'day',    'step' => 7,  'sec' => 7 * $day,       'icu' => 'MMM d',        'php' => 'M j'],
            ['unit' => 'month',  'step' => 1,  'sec' => $month,         'icu' => 'MMM yyyy',     'php' => 'M Y'],
            ['unit' => 'month',  'step' => 3,  'sec' => 3 * $month,     'icu' => 'MMM yyyy',     'php' => 'M Y'],
            ['unit' => 'month',  'step' => 6,  'sec' => 6 * $month,     'icu' => 'MMM yyyy',     'php' => 'M Y'],
            ['unit' => 'year',   'step' => 1,  'sec' => $year,          'icu' => 'yyyy',         'php' => 'Y'],
            ['unit' => 'year',   'step' => 2,  'sec' => 2 * $year,      'icu' => 'yyyy',         'php' => 'Y'],
            ['unit' => 'year',   'step' => 5,  'sec' => 5 * $year,      'icu' => 'yyyy',         'php' => 'Y'],
            ['unit' => 'year',   'step' => 10, 'sec' => 10 * $year,     'icu' => 'yyyy',         'php' => 'Y'],
        ];
    }
}
