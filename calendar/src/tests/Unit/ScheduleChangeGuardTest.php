<?php

namespace Tests\Unit;

use App\Services\ScheduleChangeGuard;
use PHPUnit\Framework\TestCase;

class ScheduleChangeGuardTest extends TestCase
{
    private static function appointment(
        int    $id,
        string $startsAt,
        int    $durationMinutes = 60,
        string $status = 'confirmed'
    ): array {
        return [
            'id'               => $id,
            'starts_at'        => $startsAt,
            'duration_minutes' => $durationMinutes,
            'status'           => $status,
        ];
    }

    // --- findConflicts -------------------------------------------------

    public function test_appointment_now_outside_new_schedule_is_a_conflict(): void
    {
        $appointments = [self::appointment(1, '2026-07-20 18:00:00', 60)];
        // New schedule for that date only covers mornings — 18:00 no longer fits.
        $scheduleByDate = ['2026-07-20' => '09:00-14:00'];

        $conflicts = ScheduleChangeGuard::findConflicts($appointments, $scheduleByDate, '2026-07-15 00:00:00');

        $this->assertCount(1, $conflicts);
        $this->assertSame(1, $conflicts[0]['id']);
    }

    public function test_appointment_still_inside_new_schedule_is_allowed(): void
    {
        $appointments = [self::appointment(2, '2026-07-20 10:00:00', 60)];
        $scheduleByDate = ['2026-07-20' => '09:00-14:00'];

        $conflicts = ScheduleChangeGuard::findConflicts($appointments, $scheduleByDate, '2026-07-15 00:00:00');

        $this->assertSame([], $conflicts);
    }

    public function test_day_off_in_new_schedule_conflicts_with_any_appointment_that_day(): void
    {
        $appointments = [self::appointment(3, '2026-07-20 10:00:00', 60)];
        $scheduleByDate = ['2026-07-20' => null];

        $conflicts = ScheduleChangeGuard::findConflicts($appointments, $scheduleByDate, '2026-07-15 00:00:00');

        $this->assertCount(1, $conflicts);
    }

    public function test_cancelled_appointments_are_ignored(): void
    {
        $appointments = [self::appointment(4, '2026-07-20 18:00:00', 60, 'cancelled')];
        $scheduleByDate = ['2026-07-20' => '09:00-14:00'];

        $conflicts = ScheduleChangeGuard::findConflicts($appointments, $scheduleByDate, '2026-07-15 00:00:00');

        $this->assertSame([], $conflicts);
    }

    public function test_past_appointments_are_ignored(): void
    {
        $appointments = [self::appointment(5, '2026-07-10 18:00:00', 60)];
        $scheduleByDate = ['2026-07-10' => '09:00-14:00'];

        // "now" is after the appointment's start.
        $conflicts = ScheduleChangeGuard::findConflicts($appointments, $scheduleByDate, '2026-07-15 00:00:00');

        $this->assertSame([], $conflicts);
    }

    public function test_multiple_appointments_only_conflicting_ones_are_returned(): void
    {
        $appointments = [
            self::appointment(6, '2026-07-20 10:00:00', 60), // fits
            self::appointment(7, '2026-07-20 18:00:00', 60), // does not fit
            self::appointment(8, '2026-07-21 10:00:00', 60), // fits
        ];
        $scheduleByDate = [
            '2026-07-20' => '09:00-14:00',
            '2026-07-21' => '09:00-14:00',
        ];

        $conflicts = ScheduleChangeGuard::findConflicts($appointments, $scheduleByDate, '2026-07-15 00:00:00');

        $this->assertCount(1, $conflicts);
        $this->assertSame(7, $conflicts[0]['id']);
    }

    // --- validateOverride ------------------------------------------------

    public function test_validate_override_works_true_no_schedule_valid_when_pattern_has_a_day(): void
    {
        $this->assertNull(ScheduleChangeGuard::validateOverride('09:30-14:00', true, null));
    }

    public function test_validate_override_works_true_no_schedule_invalid_when_pattern_day_is_null(): void
    {
        $error = ScheduleChangeGuard::validateOverride(null, true, null);
        $this->assertNotNull($error);
        $this->assertIsString($error);
    }

    public function test_validate_override_works_true_with_explicit_schedule_always_valid(): void
    {
        $this->assertNull(ScheduleChangeGuard::validateOverride(null, true, '10:00-12:00'));
    }

    public function test_validate_override_works_false_always_valid(): void
    {
        $this->assertNull(ScheduleChangeGuard::validateOverride(null, false, null));
    }

    // --- conflictMessage ---------------------------------------------------

    public function test_conflict_message_lists_conflicting_appointments(): void
    {
        $conflicts = [self::appointment(9, '2026-07-20 18:00:00', 60)];

        $message = ScheduleChangeGuard::conflictMessage($conflicts);

        $this->assertStringContainsString('#9', $message);
        $this->assertStringContainsString('2026-07-20 18:00:00', $message);
    }
}
