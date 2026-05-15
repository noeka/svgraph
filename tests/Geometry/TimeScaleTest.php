<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Geometry;

use DateTimeImmutable;
use DateTimeZone;
use Noeka\Svgraph\Geometry\TimeScale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeScaleTest extends TestCase
{
    public function test_maps_dates_linearly_across_range(): void
    {
        $start = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $end = new DateTimeImmutable('2026-01-02T00:00:00Z');
        $scale = new TimeScale($start, $end, 0.0, 100.0);

        self::assertSame(0.0, $scale->mapDate($start));
        self::assertSame(100.0, $scale->mapDate($end));
        self::assertEqualsWithDelta(50.0, $scale->mapDate(new DateTimeImmutable('2026-01-01T12:00:00Z')), 0.0001);
    }

    public function test_zero_range_does_not_divide_by_zero(): void
    {
        $start = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $scale = new TimeScale($start, $start, 0.0, 100.0);
        self::assertSame(0.0, $scale->mapDate($start));
    }

    public function test_30_second_range_uses_second_ticks(): void
    {
        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('2026-05-01T12:00:00', $tz);
        $end = new DateTimeImmutable('2026-05-01T12:00:30', $tz);
        $scale = new TimeScale($start, $end, 0.0, 100.0, timezone: $tz, useIntl: false);

        $ticks = $scale->timeTicks(5);
        self::assertNotEmpty($ticks);
        self::assertGreaterThanOrEqual(2, count($ticks));
        // Every tick should be a multiple of the bucket step in seconds.
        $stepDeltas = [];
        $counter = count($ticks);

        for ($i = 1; $i < $counter; $i++) {
            $stepDeltas[] = $ticks[$i]->getTimestamp() - $ticks[$i - 1]->getTimestamp();
        }

        self::assertSame(array_unique($stepDeltas), [array_unique($stepDeltas)[0]]);
        self::assertLessThanOrEqual(30, $stepDeltas[0]);
        self::assertGreaterThanOrEqual(5, $stepDeltas[0]);
        // Format string is HH:mm:ss (or H:i:s) for sub-minute ranges.
        $label = $scale->formatTick($ticks[0], 5);
        self::assertMatchesRegularExpression('/^\d{1,2}:\d{2}:\d{2}$/', $label);
    }

    public function test_24_hour_range_uses_hour_ticks(): void
    {
        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('2026-05-01T00:00:00', $tz);
        $end = new DateTimeImmutable('2026-05-02T00:00:00', $tz);
        $scale = new TimeScale($start, $end, 0.0, 100.0, timezone: $tz, useIntl: false);

        $ticks = $scale->timeTicks(5);
        self::assertGreaterThanOrEqual(3, count($ticks));
        $delta = $ticks[1]->getTimestamp() - $ticks[0]->getTimestamp();
        // Should land on hours, somewhere between 3h and 12h.
        self::assertGreaterThanOrEqual(3 * 3600, $delta);
        self::assertLessThanOrEqual(12 * 3600, $delta);
        // Hour labels at midnight read e.g. "00:00" or "May 1 00:00" depending
        // on whether the bucket includes the date prefix.
        self::assertMatchesRegularExpression('/00:00$/', $scale->formatTick($ticks[0], 5));
    }

    public function test_30_day_range_uses_day_ticks(): void
    {
        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('2026-05-01T00:00:00', $tz);
        $end = $start->modify('+30 days');
        $scale = new TimeScale($start, $end, 0.0, 100.0, timezone: $tz, useIntl: false);

        $ticks = $scale->timeTicks(5);
        self::assertGreaterThanOrEqual(3, count($ticks));

        // Each tick should land at midnight in the configured timezone.
        foreach ($ticks as $t) {
            self::assertSame('00:00:00', $t->format('H:i:s'));
        }

        $delta = $ticks[1]->getTimestamp() - $ticks[0]->getTimestamp();
        self::assertGreaterThanOrEqual(86400, $delta);
        self::assertLessThanOrEqual(7 * 86400, $delta);
    }

    public function test_5_year_range_uses_year_ticks(): void
    {
        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('2021-01-01T00:00:00', $tz);
        $end = new DateTimeImmutable('2026-01-01T00:00:00', $tz);
        $scale = new TimeScale($start, $end, 0.0, 100.0, timezone: $tz, useIntl: false);

        $ticks = $scale->timeTicks(5);
        self::assertGreaterThanOrEqual(3, count($ticks));

        foreach ($ticks as $t) {
            self::assertSame('01-01 00:00:00', $t->format('m-d H:i:s'));
        }
        // PHP-format fallback should yield a 4-digit year.
        self::assertMatchesRegularExpression('/^\d{4}$/', $scale->formatTick($ticks[0], 5));
    }

    public function test_falls_back_to_php_format_when_intl_disabled(): void
    {
        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('2026-05-01T00:00:00', $tz);
        $end = new DateTimeImmutable('2026-05-31T00:00:00', $tz);
        $scale = new TimeScale($start, $end, 0.0, 100.0, timezone: $tz, useIntl: false);

        $label = $scale->formatTick($start, 5);
        // PHP "M j" format → e.g. "May 1".
        self::assertMatchesRegularExpression('/^[A-Za-z]{3} \d{1,2}$/', $label);
    }

    public function test_locale_changes_month_names_when_intl_available(): void
    {
        if (!class_exists(\IntlDateFormatter::class)) {
            self::markTestSkipped('ext-intl not available');
        }

        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('2026-05-01T00:00:00', $tz);
        $end = new DateTimeImmutable('2026-05-31T00:00:00', $tz);
        $en = new TimeScale($start, $end, 0.0, 100.0, locale: 'en_US', timezone: $tz);
        $es = new TimeScale($start, $end, 0.0, 100.0, locale: 'es_ES', timezone: $tz);

        $enLabel = $en->formatTick($start, 5);
        $esLabel = $es->formatTick($start, 5);
        self::assertNotSame($enLabel, $esLabel);
        self::assertStringContainsStringIgnoringCase('may', $enLabel);
    }

    public function test_timezone_offsets_tick_hours(): void
    {
        $start = new DateTimeImmutable('2026-05-01T00:00:00Z');
        $end = new DateTimeImmutable('2026-05-02T00:00:00Z');
        $utc = new TimeScale($start, $end, 0.0, 100.0, timezone: new DateTimeZone('UTC'), useIntl: false);
        $jp = new TimeScale($start, $end, 0.0, 100.0, timezone: new DateTimeZone('Asia/Tokyo'), useIntl: false);

        // First UTC tick at midnight UTC; first Tokyo tick at the next 00:00 in JST,
        // which is 15:00 UTC on the same day.
        self::assertSame('00:00:00', $utc->timeTicks(5)[0]->format('H:i:s'));
        $jpTicks = $jp->timeTicks(5);
        self::assertNotEmpty($jpTicks);
        self::assertSame('Asia/Tokyo', $jpTicks[0]->getTimezone()->getName());
    }

    public function test_format_override_is_honoured(): void
    {
        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('2026-05-04T00:00:00', $tz);
        $end = new DateTimeImmutable('2026-05-31T00:00:00', $tz);
        // Y-m-d is unambiguous in PHP format and works as ICU date pattern.
        $scale = new TimeScale($start, $end, 0.0, 100.0, timezone: $tz, format: 'Y-m-d', useIntl: false);
        self::assertSame('2026-05-04', $scale->formatTick($start, 5));
    }

    public function test_from_values_picks_min_and_max(): void
    {
        $values = [
            new DateTimeImmutable('2026-05-10T00:00:00Z'),
            new DateTimeImmutable('2026-05-01T00:00:00Z'),
            new DateTimeImmutable('2026-05-31T00:00:00Z'),
        ];
        $scale = TimeScale::fromValues($values, 0.0, 100.0, timezone: 'UTC', useIntl: false);
        self::assertSame('2026-05-01', $scale->start->format('Y-m-d'));
        self::assertSame('2026-05-31', $scale->end->format('Y-m-d'));
    }

    /**
     * Every row exercises one bucket from the table in TimeScale::buckets().
     * For each, the range is chosen so `pickBucket` picks that bucket exactly,
     * and we assert (a) the time between consecutive ticks, (b) the format
     * string used. Any IncrementInteger/DecrementInteger on a bucket's
     * step/sec constant, or a swap in the snapDown/advance match arms,
     * shifts the delta or the format and fails the row.
     */
    #[DataProvider('bucketMatrix')]
    public function test_bucket_selection(
        string $startIso,
        string $endIso,
        string $advance,
        string $expectedFirstFormatted,
    ): void {
        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable($startIso, $tz);
        $end = new DateTimeImmutable($endIso, $tz);
        $scale = new TimeScale($start, $end, 0.0, 100.0, timezone: $tz, useIntl: false);

        $ticks = $scale->timeTicks(5);
        self::assertGreaterThanOrEqual(2, count($ticks), 'Expected at least two ticks.');

        // The second tick must equal the first advanced by the bucket's step.
        $expectedSecond = $ticks[0]->modify($advance);
        self::assertSame(
            $expectedSecond->getTimestamp(),
            $ticks[1]->getTimestamp(),
            "Bucket step mismatch (expected {$advance} between ticks).",
        );

        // The PHP-format output of the first tick locks down the bucket's
        // format string and (for sub-day buckets) the snap behaviour.
        self::assertSame($expectedFirstFormatted, $scale->formatTick($ticks[0], 5));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function bucketMatrix(): array
    {
        return [
            // sub-minute → format H:i:s.
            'sec/1'    => ['2026-05-01T00:00:00', '2026-05-01T00:00:04', '+1 seconds',  '00:00:00'],
            'sec/5'    => ['2026-05-01T00:00:00', '2026-05-01T00:00:20', '+5 seconds',  '00:00:00'],
            'sec/15'   => ['2026-05-01T00:00:00', '2026-05-01T00:01:00', '+15 seconds', '00:00:00'],
            'sec/30'   => ['2026-05-01T00:00:00', '2026-05-01T00:02:00', '+30 seconds', '00:00:00'],
            // minute & hour → format H:i.
            'min/1'    => ['2026-05-01T00:00:00', '2026-05-01T00:04:00', '+1 minutes',  '00:00'],
            'min/5'    => ['2026-05-01T00:00:00', '2026-05-01T00:20:00', '+5 minutes',  '00:00'],
            'min/15'   => ['2026-05-01T00:00:00', '2026-05-01T01:00:00', '+15 minutes', '00:00'],
            'min/30'   => ['2026-05-01T00:00:00', '2026-05-01T02:00:00', '+30 minutes', '00:00'],
            'hour/1'   => ['2026-05-01T00:00:00', '2026-05-01T04:00:00', '+1 hours',    '00:00'],
            'hour/3'   => ['2026-05-01T00:00:00', '2026-05-01T12:00:00', '+3 hours',    '00:00'],
            // 6h/12h drop into the M j H:i format (date prefix).
            'hour/6'   => ['2026-05-01T00:00:00', '2026-05-02T00:00:00', '+6 hours',    'May 1 00:00'],
            'hour/12'  => ['2026-05-01T00:00:00', '2026-05-03T00:00:00', '+12 hours',   'May 1 00:00'],
            // Day → "M j".
            'day/1'    => ['2026-05-01T00:00:00', '2026-05-05T00:00:00', '+1 days',     'May 1'],
            'day/2'    => ['2026-05-01T00:00:00', '2026-05-09T00:00:00', '+2 days',     'May 1'],
            'day/7'    => ['2026-05-01T00:00:00', '2026-05-29T00:00:00', '+7 days',     'May 1'],
            // Month → "M Y".
            'month/1'  => ['2026-01-01T00:00:00', '2026-05-01T00:00:00', '+1 months',   'Jan 2026'],
            'month/3'  => ['2026-01-01T00:00:00', '2027-01-01T00:00:00', '+3 months',   'Jan 2026'],
            'month/6'  => ['2026-01-01T00:00:00', '2028-01-01T00:00:00', '+6 months',   'Jan 2026'],
            // Year → "Y".
            'year/1'   => ['2026-01-01T00:00:00', '2030-01-01T00:00:00', '+1 years',    '2026'],
            'year/2'   => ['2026-01-01T00:00:00', '2034-01-01T00:00:00', '+2 years',    '2026'],
            'year/5'   => ['2020-01-01T00:00:00', '2040-01-01T00:00:00', '+5 years',    '2020'],
            'year/10'  => ['2000-01-01T00:00:00', '2040-01-01T00:00:00', '+10 years',   '2000'],
        ];
    }

    public function test_from_values_handles_unique_max_at_index_zero(): void
    {
        // The accumulator initialises both $min and $max to $collected[0]. If
        // $max is mis-initialised to $collected[1] instead, this case (where
        // the unique maximum is the first element) silently truncates the
        // domain to the second-largest value.
        $values = [
            new DateTimeImmutable('2026-05-31T00:00:00Z'),
            new DateTimeImmutable('2026-05-10T00:00:00Z'),
            new DateTimeImmutable('2026-05-01T00:00:00Z'),
        ];
        $scale = TimeScale::fromValues($values, 0.0, 100.0, timezone: 'UTC', useIntl: false);
        self::assertSame('2026-05-01', $scale->start->format('Y-m-d'));
        self::assertSame('2026-05-31', $scale->end->format('Y-m-d'));
    }
}
