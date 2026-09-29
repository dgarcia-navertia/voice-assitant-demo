<?php

namespace Tests\Unit;

use App\Services\AvailabilityService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AvailabilityServiceTest extends TestCase
{
    // All tests use 2026-06-15 (Monday) as the reference date.
    private static function ts(string $time): int
    {
        return strtotime("2026-06-15 {$time}");
    }

    private static function slots(array $ranges, array $bookedRanges, int $durationMinutes, int $intervalMinutes): array
    {
        $method = new ReflectionMethod(AvailabilityService::class, 'generateSlots');
        $method->setAccessible(true);
        return $method->invoke(null, $ranges, $bookedRanges, $durationMinutes, $intervalMinutes);
    }

    private static function range(string $from, string $to): array
    {
        return ['start' => self::ts($from), 'end' => self::ts($to)];
    }

    private static function booked(string $from, string $to): array
    {
        return ['start' => self::ts($from), 'end' => self::ts($to)];
    }

    // Old fixed-step algorithm would miss 09:45 here — it only steps by duration (30 min)
    // so after the 09:00–09:45 block it would land on 09:30, skip it, then jump to 10:00.
    // The base-interval algorithm steps by 15 min and correctly offers 09:45.
    public function test_gap_is_filled_after_variable_duration_appointment(): void
    {
        $result = self::slots(
            [self::range('09:00', '12:00')],
            [self::booked('09:00', '09:45')],
            durationMinutes: 30,
            intervalMinutes: 15
        );

        $this->assertContains('09:45', $result, '09:45 must be offered — it is the first gap after the 45-min block');
        $this->assertNotContains('09:00', $result);
        $this->assertNotContains('09:15', $result);
        $this->assertNotContains('09:30', $result);
    }

    // A slot that starts exactly at the range boundary is valid (end == range end).
    public function test_slot_fits_exactly_at_range_end(): void
    {
        $result = self::slots(
            [self::range('09:00', '10:00')],
            [],
            durationMinutes: 60,
            intervalMinutes: 15
        );

        $this->assertSame(['09:00'], $result, 'Only 09:00 fits; 09:15+60min overshoots the range');
    }

    // No slot offered when the range is shorter than the service duration.
    public function test_returns_empty_when_range_is_shorter_than_duration(): void
    {
        $result = self::slots(
            [self::range('09:00', '09:29')],
            [],
            durationMinutes: 30,
            intervalMinutes: 15
        );

        $this->assertSame([], $result);
    }

    // Slots must not cross the lunch-break gap between two ranges.
    public function test_split_schedule_slots_do_not_cross_break(): void
    {
        $result = self::slots(
            [self::range('09:00', '14:00'), self::range('16:00', '20:00')],
            [],
            durationMinutes: 60,
            intervalMinutes: 15
        );

        // Last valid morning slot: 13:00 (13:00+60=14:00 fits exactly)
        $this->assertContains('13:00', $result);
        // 13:15+60=14:15 overshoots the morning range
        $this->assertNotContains('13:15', $result);
        // Nothing between 14:00 and 16:00
        $this->assertNotContains('14:00', $result);
        $this->assertNotContains('15:00', $result);
        // Afternoon opens at 16:00
        $this->assertContains('16:00', $result);
    }

    // When all intervals are booked, no slot is returned.
    public function test_returns_empty_when_fully_booked(): void
    {
        $result = self::slots(
            [self::range('09:00', '10:00')],
            [self::booked('09:00', '09:30'), self::booked('09:30', '10:00')],
            durationMinutes: 30,
            intervalMinutes: 15
        );

        $this->assertSame([], $result);
    }

    // 15-min interval generates dense, non-overlapping candidates for a 45-min service.
    public function test_interval_stepping_with_45_minute_service(): void
    {
        $result = self::slots(
            [self::range('09:00', '11:00')],
            [],
            durationMinutes: 45,
            intervalMinutes: 15
        );

        // 10:15+45=11:00 fits; 10:30+45=11:15 does not
        $this->assertContains('09:00', $result);
        $this->assertContains('09:15', $result);
        $this->assertContains('10:15', $result);
        $this->assertNotContains('10:30', $result);
    }

    // A partial overlap blocks the slot even if the appointment started before the range.
    public function test_slot_blocked_by_partially_overlapping_appointment(): void
    {
        // Appointment started before our range but ends at 09:20 — blocks 09:00 slot (30-min service).
        $result = self::slots(
            [self::range('09:00', '12:00')],
            [self::booked('08:45', '09:20')],
            durationMinutes: 30,
            intervalMinutes: 15
        );

        $this->assertNotContains('09:00', $result, '09:00–09:30 overlaps the 08:45–09:20 block');
        $this->assertNotContains('09:15', $result, '09:15–09:45... wait, 09:15 < 09:20 so still overlaps');
        $this->assertContains('09:30', $result, '09:30 is fully after the 09:20 block end');
    }
}
