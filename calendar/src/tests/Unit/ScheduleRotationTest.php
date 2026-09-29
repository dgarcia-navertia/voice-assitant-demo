<?php

namespace Tests\Unit;

use App\Services\ScheduleRotation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScheduleRotationTest extends TestCase
{
    // --- normalizeAnchor -----------------------------------------------

    public function test_normalize_anchor_monday_stays_monday(): void
    {
        $this->assertSame('2026-07-13', ScheduleRotation::normalizeAnchor('2026-07-13'));
    }

    #[DataProvider('weekdayProvider')]
    public function test_normalize_anchor_any_weekday_maps_to_its_monday(string $input, string $expectedMonday): void
    {
        $this->assertSame($expectedMonday, ScheduleRotation::normalizeAnchor($input));
    }

    public static function weekdayProvider(): array
    {
        // Week of 2026-07-13 (Monday) .. 2026-07-19 (Sunday).
        return [
            'monday'    => ['2026-07-13', '2026-07-13'],
            'tuesday'   => ['2026-07-14', '2026-07-13'],
            'wednesday' => ['2026-07-15', '2026-07-13'],
            'thursday'  => ['2026-07-16', '2026-07-13'],
            'friday'    => ['2026-07-17', '2026-07-13'],
            'saturday'  => ['2026-07-18', '2026-07-13'],
            'sunday'    => ['2026-07-19', '2026-07-13'],
        ];
    }

    // --- rotation_length 1 -----------------------------------------------

    public function test_rotation_length_one_is_always_week_zero(): void
    {
        $this->assertSame(0, ScheduleRotation::weekIndex(null, '2026-07-15', 1));
        $this->assertSame(0, ScheduleRotation::weekIndex('2026-07-13', '2027-01-01', 1));
    }

    // --- dates before the anchor (mathematical modulo, never negative) ---

    public function test_dates_before_anchor_resolve_with_mathematical_modulo(): void
    {
        $anchor = '2026-07-13'; // Monday, week index 0
        $n      = 3;

        // Week 0: 2026-07-13..19
        $this->assertSame(0, ScheduleRotation::weekIndex($anchor, '2026-07-13', $n));
        $this->assertSame(0, ScheduleRotation::weekIndex($anchor, '2026-07-19', $n));

        // One week before anchor: week (0 - 1) mod 3 = 2
        $this->assertSame(2, ScheduleRotation::weekIndex($anchor, '2026-07-06', $n));
        $this->assertSame(2, ScheduleRotation::weekIndex($anchor, '2026-07-12', $n));

        // Two weeks before anchor: week (0 - 2) mod 3 = 1
        $this->assertSame(1, ScheduleRotation::weekIndex($anchor, '2026-06-29', $n));

        // Three weeks before anchor: back to week 0
        $this->assertSame(0, ScheduleRotation::weekIndex($anchor, '2026-06-22', $n));

        // Four weeks before anchor: week (0 - 4) mod 3 = 2
        $this->assertSame(2, ScheduleRotation::weekIndex($anchor, '2026-06-15', $n));
    }

    // --- DST transitions --------------------------------------------------

    public function test_week_index_sequence_unaffected_by_spring_forward_dst(): void
    {
        // Europe/Madrid spring-forward: last Sunday of March 2026 = 2026-03-29.
        $anchor = '2026-03-16'; // Monday
        $n      = 2;

        $expected = [
            '2026-03-16' => 0,
            '2026-03-23' => 1, // week straddling the DST weekend
            '2026-03-30' => 0,
            '2026-04-06' => 1,
        ];
        foreach ($expected as $date => $index) {
            $this->assertSame($index, ScheduleRotation::weekIndex($anchor, $date, $n), "date {$date}");
        }
    }

    public function test_week_index_sequence_unaffected_by_fall_back_dst(): void
    {
        // Europe/Madrid fall-back: last Sunday of October 2026 = 2026-10-25.
        $anchor = '2026-10-12'; // Monday
        $n      = 2;

        $expected = [
            '2026-10-12' => 0,
            '2026-10-19' => 1, // week straddling the DST weekend
            '2026-10-26' => 0,
            '2026-11-02' => 1,
        ];
        foreach ($expected as $date => $index) {
            $this->assertSame($index, ScheduleRotation::weekIndex($anchor, $date, $n), "date {$date}");
        }
    }

    // --- year boundary / ISO week 53 --------------------------------------

    public function test_week_index_alternates_correctly_across_year_boundary_with_iso_week_53(): void
    {
        // 2026-12-28 (Monday) is in ISO week 53 of 2026; naive ISO-week-number
        // modulo arithmetic would desync here. Plain sequential counting must not.
        $anchor = '2026-11-30'; // Monday
        $n      = 2;

        $expected = [
            '2026-11-30' => 0,
            '2026-12-07' => 1,
            '2026-12-14' => 0,
            '2026-12-21' => 1,
            '2026-12-28' => 0, // ISO week 53
            '2027-01-04' => 1,
        ];
        foreach ($expected as $date => $index) {
            $this->assertSame($index, ScheduleRotation::weekIndex($anchor, $date, $n), "date {$date}");
        }
    }

    // --- full cycles -------------------------------------------------------

    public function test_rotation_length_two_full_cycle(): void
    {
        $anchor = '2026-07-13';
        $n      = 2;

        $expected = [
            '2026-07-13' => 0,
            '2026-07-20' => 1,
            '2026-07-27' => 0,
            '2026-08-03' => 1,
            '2026-08-10' => 0,
            '2026-08-17' => 1,
            '2026-08-24' => 0,
        ];
        foreach ($expected as $date => $index) {
            $this->assertSame($index, ScheduleRotation::weekIndex($anchor, $date, $n), "date {$date}");
        }
    }

    public function test_rotation_length_three_full_cycle(): void
    {
        $anchor = '2026-07-13';
        $n      = 3;

        $expected = [
            '2026-07-13' => 0,
            '2026-07-20' => 1,
            '2026-07-27' => 2,
            '2026-08-03' => 0,
            '2026-08-10' => 1,
            '2026-08-17' => 2,
            '2026-08-24' => 0,
        ];
        foreach ($expected as $date => $index) {
            $this->assertSame($index, ScheduleRotation::weekIndex($anchor, $date, $n), "date {$date}");
        }
    }
}
