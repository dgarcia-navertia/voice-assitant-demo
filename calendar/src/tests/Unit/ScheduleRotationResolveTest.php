<?php

namespace Tests\Unit;

use App\Services\ScheduleRotation;
use PHPUnit\Framework\TestCase;

class ScheduleRotationResolveTest extends TestCase
{
    private const MONDAY = '2026-07-13'; // Monday, rotation anchor
    private const TUESDAY = '2026-07-14';

    // --- pattern-only resolution, per weekday -----------------------------

    public function test_resolves_from_pattern_for_a_plain_weekly_schedule(): void
    {
        $patternWeeks = [
            0 => [
                'monday'    => '09:30-14:00 y 16:00-21:00',
                'tuesday'   => '09:30-14:00',
                'wednesday' => null,
                'thursday'  => '09:30-14:00',
                'friday'    => '09:30-14:00',
                'saturday'  => null,
                'sunday'    => null,
            ],
        ];

        $this->assertSame(
            '09:30-14:00 y 16:00-21:00',
            ScheduleRotation::resolveSchedule($patternWeeks, 1, null, self::MONDAY)
        );
        $this->assertSame(
            '09:30-14:00',
            ScheduleRotation::resolveSchedule($patternWeeks, 1, null, self::TUESDAY)
        );
        $this->assertNull(
            ScheduleRotation::resolveSchedule($patternWeeks, 1, null, '2026-07-15') // Wednesday, day off
        );
    }

    // --- override precedence -----------------------------------------------

    public function test_override_works_false_forces_day_off(): void
    {
        $patternWeeks = [0 => ['monday' => '09:30-14:00']];

        $result = ScheduleRotation::resolveSchedule(
            $patternWeeks,
            1,
            null,
            self::MONDAY,
            ['works' => false, 'schedule' => null]
        );

        $this->assertNull($result);
    }

    public function test_override_works_true_with_schedule_replaces_pattern(): void
    {
        $patternWeeks = [0 => ['monday' => '09:30-14:00']];

        $result = ScheduleRotation::resolveSchedule(
            $patternWeeks,
            1,
            null,
            self::MONDAY,
            ['works' => true, 'schedule' => '10:00-12:00']
        );

        $this->assertSame('10:00-12:00', $result);
    }

    public function test_override_works_true_without_schedule_inherits_pattern(): void
    {
        $patternWeeks = [0 => ['monday' => '09:30-14:00 y 16:00-21:00']];

        $result = ScheduleRotation::resolveSchedule(
            $patternWeeks,
            1,
            null,
            self::MONDAY,
            ['works' => true, 'schedule' => null]
        );

        $this->assertSame('09:30-14:00 y 16:00-21:00', $result);
    }

    // Defensive: this combination should never be persisted (see ScheduleOverride
    // validation), but the resolver must not blow up if it ever occurs.
    public function test_override_works_true_without_schedule_on_pattern_day_off_is_null(): void
    {
        $patternWeeks = [0 => ['monday' => null]];

        $result = ScheduleRotation::resolveSchedule(
            $patternWeeks,
            1,
            null,
            self::MONDAY,
            ['works' => true, 'schedule' => null]
        );

        $this->assertNull($result);
    }

    // --- half-day alternation across rotation weeks -------------------------

    public function test_half_day_alternation_between_rotation_weeks(): void
    {
        $patternWeeks = [
            0 => ['monday' => '09:30-14:00 y 16:00-21:00'],
            1 => ['monday' => '09:30-14:00'],
        ];

        $this->assertSame(
            '09:30-14:00 y 16:00-21:00',
            ScheduleRotation::resolveSchedule($patternWeeks, 2, self::MONDAY, self::MONDAY)
        );
        $this->assertSame(
            '09:30-14:00',
            ScheduleRotation::resolveSchedule($patternWeeks, 2, self::MONDAY, '2026-07-20')
        );
        $this->assertSame(
            '09:30-14:00 y 16:00-21:00',
            ScheduleRotation::resolveSchedule($patternWeeks, 2, self::MONDAY, '2026-07-27')
        );
    }

    // --- patternValueForDate ------------------------------------------------

    public function test_pattern_value_for_date_returns_plain_weekly_value(): void
    {
        $patternWeeks = [0 => ['monday' => '09:30-14:00', 'tuesday' => null]];

        $this->assertSame(
            '09:30-14:00',
            ScheduleRotation::patternValueForDate($patternWeeks, 1, null, self::MONDAY)
        );
        $this->assertNull(
            ScheduleRotation::patternValueForDate($patternWeeks, 1, null, self::TUESDAY)
        );
    }

    public function test_pattern_value_for_date_is_rotation_aware(): void
    {
        $patternWeeks = [
            0 => ['saturday' => '09:00-13:00'],
            1 => ['saturday' => null],
            2 => ['saturday' => null],
        ];

        $this->assertSame(
            '09:00-13:00',
            ScheduleRotation::patternValueForDate($patternWeeks, 3, self::MONDAY, '2026-07-18') // week 0 Saturday
        );
        $this->assertNull(
            ScheduleRotation::patternValueForDate($patternWeeks, 3, self::MONDAY, '2026-07-25') // week 1 Saturday
        );
    }

    public function test_pattern_value_for_date_normalizes_empty_string_to_null(): void
    {
        $patternWeeks = [0 => ['monday' => '']];

        $this->assertNull(ScheduleRotation::patternValueForDate($patternWeeks, 1, null, self::MONDAY));
    }
}
