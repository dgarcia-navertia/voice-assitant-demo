<?php

namespace App\Services;

use DateTimeImmutable;

/**
 * Pure, DB-free week-rotation math for the N-week rotating schedule feature.
 *
 * A commercial's schedule repeats every `rotation_length` weeks (1 = a plain
 * weekly schedule). The rotation is anchored to a Monday (`rotation_anchor`);
 * the resolver figures out which "rotation week" (0..rotation_length-1) a
 * given date falls into relative to that anchor.
 *
 * All date arithmetic is done on Y-m-d calendar dates via DateTimeImmutable,
 * never on Unix timestamps/seconds — dividing seconds by a week's worth of
 * seconds breaks across DST transitions (Europe/Madrid shifts by one hour
 * twice a year). ISO week numbers are avoided too: week 53 (some years have
 * one) would desynchronize a simple modulo against the calendar week number.
 */
class ScheduleRotation
{
    /**
     * Normalizes any date to the Monday of its week (Monday stays Monday).
     */
    public static function normalizeAnchor(string $date): string
    {
        $d = new DateTimeImmutable($date);
        // date('N'): 1 = Monday ... 7 = Sunday.
        $isoDow = (int) $d->format('N');
        $monday = $d->modify('-' . ($isoDow - 1) . ' days');
        return $monday->format('Y-m-d');
    }

    /**
     * Resolves the rotation week index (0..rotationLength-1) for $date given
     * an (already normalized-to-Monday) $anchor.
     *
     * When rotationLength is 1 there is only one week, so the index is always
     * 0 regardless of anchor (anchor may be null in that case).
     */
    public static function weekIndex(?string $anchor, string $date, int $rotationLength): int
    {
        if ($rotationLength <= 1) {
            return 0;
        }
        if ($anchor === null) {
            // No anchor with a multi-week rotation: nothing to compute against.
            return 0;
        }

        $anchorMonday = new DateTimeImmutable(self::normalizeAnchor($anchor));
        $target       = new DateTimeImmutable(self::normalizeAnchor($date));

        $diffDays = self::daysBetween($anchorMonday, $target);
        $weeksElapsed = intdiv($diffDays, 7);
        // For dates before the anchor, floor() must round toward negative
        // infinity, not toward zero — intdiv() in PHP truncates toward zero,
        // so we need an explicit floor for negative diffs.
        if ($diffDays % 7 !== 0 && $diffDays < 0) {
            $weeksElapsed -= 1;
        }

        return self::mathematicalModulo($weeksElapsed, $rotationLength);
    }

    /**
     * Signed day difference target - anchor, computed on calendar dates
     * (never on timestamps/seconds, to stay DST-safe).
     */
    private static function daysBetween(DateTimeImmutable $anchor, DateTimeImmutable $target): int
    {
        $diff = $anchor->diff($target);
        return (int) $diff->days * ($diff->invert ? -1 : 1);
    }

    /**
     * Mathematical modulo: always returns a value in [0, n), unlike PHP's `%`
     * which keeps the sign of the dividend for negative numbers.
     */
    private static function mathematicalModulo(int $x, int $n): int
    {
        return (($x % $n) + $n) % $n;
    }

    /**
     * Resolves the effective schedule string for a commercial on $date, applying:
     *
     *   holiday (checked upstream, not here) -> override -> pattern
     *
     * $patternWeeks is indexed [weekIndex => [weekday => schedule|null, ...]]
     * (weekday keys: monday..sunday), one row per rotation week.
     *
     * $override, when given, is ['works' => bool, 'schedule' => ?string]:
     *   - works = false                     -> day off (null), overrides the pattern.
     *   - works = true, schedule set        -> that schedule, overrides the pattern.
     *   - works = true, schedule = null     -> inherit the pattern's schedule for
     *                                          that weekday. If the pattern day is
     *                                          itself null this is an invalid
     *                                          combination (should be rejected at
     *                                          write time); defensively resolves to
     *                                          null here rather than throwing.
     *
     * Returns the schedule string, or null if the commercial does not work that day.
     */
    public static function resolveSchedule(
        array $patternWeeks,
        int $rotationLength,
        ?string $anchor,
        string $date,
        ?array $override = null
    ): ?string {
        $patternValue = self::patternValueForDate($patternWeeks, $rotationLength, $anchor, $date);

        if ($override !== null) {
            if (!$override['works']) {
                return null;
            }
            $overrideSchedule = $override['schedule'] ?? null;
            if ($overrideSchedule !== null && $overrideSchedule !== '') {
                return $overrideSchedule;
            }
            // works = true, no explicit schedule: inherit the pattern's day.
            // If the pattern day is null, this combination is invalid and should
            // never have been saved (see ScheduleOverride validation) — defensively
            // return null instead of a broken/empty schedule.
            return $patternValue;
        }

        return $patternValue;
    }

    /**
     * Resolves the plain pattern schedule (no override applied) for $date —
     * the raw value from $patternWeeks at the resolved rotation week/weekday,
     * with '' normalized to null. Exposed so callers (e.g. the override form,
     * which needs "what does the pattern say for this day?" to validate a
     * proposed override) don't have to duplicate the weekday/week-index math.
     */
    public static function patternValueForDate(
        array $patternWeeks,
        int $rotationLength,
        ?string $anchor,
        string $date
    ): ?string {
        $weekday = self::weekdayKey($date);
        $index   = self::weekIndex($anchor, $date, $rotationLength);
        $value   = $patternWeeks[$index][$weekday] ?? null;
        return ($value === '') ? null : $value;
    }

    /** Maps a Y-m-d date to its weekday column key (monday..sunday). */
    private static function weekdayKey(string $date): string
    {
        $weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        return $weekdays[(int) (new DateTimeImmutable($date))->format('N') - 1];
    }
}
