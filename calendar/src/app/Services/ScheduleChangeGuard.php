<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Commercial;
use App\Models\ScheduleOverride;
use App\Models\WeekPattern;

/**
 * Blocks schedule changes (rotation pattern or per-date overrides) that would
 * strand a future, non-cancelled appointment outside the commercial's new
 * effective schedule. The core check (findConflicts) is pure and DB-free —
 * it takes appointment rows and an already-resolved proposed schedule per
 * date — so it is unit-testable without a database. The `check*` methods
 * are thin DB wrappers that fetch appointments/pattern data and delegate to it.
 */
class ScheduleChangeGuard
{
    /**
     * Pure conflict check. $appointments: rows with at least 'starts_at'
     * (Y-m-d H:i:s), 'duration_minutes' and 'status'. $scheduleByDate: the
     * PROPOSED schedule string (or null = day off) for every date present
     * among $appointments, already resolved by the caller (e.g. via
     * ScheduleRotation::resolveSchedule with the new configuration).
     *
     * Cancelled and past appointments are ignored — $now (Y-m-d H:i:s,
     * defaults to the current time) is the cutoff, injectable for tests.
     *
     * Returns the conflicting appointment rows (empty = safe to proceed).
     */
    public static function findConflicts(array $appointments, array $scheduleByDate, ?string $now = null): array
    {
        $now = $now ?? date('Y-m-d H:i:s');

        $conflicts = [];
        foreach ($appointments as $appointment) {
            if (($appointment['status'] ?? null) === 'cancelled') {
                continue;
            }
            if ($appointment['starts_at'] < $now) {
                continue;
            }

            $date     = substr($appointment['starts_at'], 0, 10);
            $schedule = $scheduleByDate[$date] ?? null;
            if (!self::fitsSchedule($appointment, $schedule)) {
                $conflicts[] = $appointment;
            }
        }
        return $conflicts;
    }

    /**
     * Validates the works=true/schedule=null combination: it means "inherit
     * the pattern's schedule for that day", which is invalid when the pattern
     * day is itself null (there is nothing to inherit). Returns an error
     * message, or null when the combination is valid.
     */
    public static function validateOverride(?string $patternDayValue, bool $works, ?string $schedule): ?string
    {
        $patternDayValue = ($patternDayValue === '') ? null : $patternDayValue;
        $schedule        = ($schedule === '') ? null : $schedule;

        if ($works && $schedule === null && $patternDayValue === null) {
            return 'No se puede marcar el día como trabajado sin indicar un horario, porque la plantilla no tiene horario asignado ese día.';
        }
        return null;
    }

    /**
     * Whether $appointment (start + duration, both in minutes-of-day terms)
     * fits entirely within one of $schedule's ranges. A null/empty schedule
     * (day off) never fits.
     */
    private static function fitsSchedule(array $appointment, ?string $schedule): bool
    {
        if ($schedule === null || $schedule === '') {
            return false;
        }

        [$start, $end] = self::appointmentMinutes($appointment);
        foreach (self::parseRanges($schedule) as [$rangeStart, $rangeEnd]) {
            if ($start >= $rangeStart && $end <= $rangeEnd) {
                return true;
            }
        }
        return false;
    }

    private static function appointmentMinutes(array $appointment): array
    {
        $time  = substr($appointment['starts_at'], 11, 5); // HH:MM
        $start = self::toMinutes($time);
        $end   = $start + (int) $appointment['duration_minutes'];
        return [$start, $end];
    }

    /** Parses "09:30-14:00 y 16:00-21:00" into [[start, end], ...] in minutes-of-day. */
    private static function parseRanges(string $schedule): array
    {
        $ranges = [];
        foreach (explode(' y ', $schedule) as $part) {
            [$open, $close] = explode('-', trim($part));
            $ranges[] = [self::toMinutes(trim($open)), self::toMinutes(trim($close))];
        }
        return $ranges;
    }

    private static function toMinutes(string $hhmm): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $hhmm));
        return $hours * 60 + $minutes;
    }

    /**
     * Builds the user-facing error message listing the conflicting appointments.
     */
    public static function conflictMessage(array $conflicts): string
    {
        $lines = array_map(
            fn(array $a) => sprintf('#%d — %s (%d min)', $a['id'], $a['starts_at'], (int) $a['duration_minutes']),
            $conflicts
        );
        return "No se puede guardar este cambio de horario: las siguientes citas quedarían fuera del nuevo horario. "
            . "Reasígnalas a otro comercial antes de continuar:\n" . implode("\n", $lines);
    }

    // --- Thin DB wrappers ---------------------------------------------------

    /**
     * Checks whether replacing a commercial's whole rotation configuration
     * (pattern weeks / rotation_length / anchor) would strand any future,
     * non-cancelled appointment outside the new schedule.
     */
    public static function checkRotationChange(
        int     $commercialId,
        array   $proposedPatternWeeks,
        int     $proposedRotationLength,
        ?string $proposedAnchor
    ): array {
        $appointments = Appointment::nonCancelledFromDate($commercialId, date('Y-m-d') . ' 00:00:00');
        if (!$appointments) {
            return [];
        }

        $scheduleByDate = [];
        foreach ($appointments as $appointment) {
            $date = substr($appointment['starts_at'], 0, 10);
            if (!array_key_exists($date, $scheduleByDate)) {
                $override = ScheduleOverride::forCommercialOnDate($commercialId, $date);
                $scheduleByDate[$date] = ScheduleRotation::resolveSchedule(
                    $proposedPatternWeeks,
                    $proposedRotationLength,
                    $proposedAnchor,
                    $date,
                    $override
                );
            }
        }

        return self::findConflicts($appointments, $scheduleByDate);
    }

    /**
     * Checks whether creating/updating an override on $date would strand any
     * future, non-cancelled appointment of that commercial on that date.
     */
    public static function checkOverrideChange(int $commercialId, string $date, bool $works, ?string $schedule): array
    {
        $appointments = Appointment::nonCancelledOnDate($commercialId, $date);
        if (!$appointments) {
            return [];
        }

        $commercial = Commercial::find($commercialId) ?? [];
        $patternWeeks = WeekPattern::forCommercial($commercialId);
        $rotationLength = (int) ($commercial['rotation_length'] ?? 1);
        $anchor         = $commercial['rotation_anchor'] ?? null;

        $scheduleByDate = [
            $date => ScheduleRotation::resolveSchedule(
                $patternWeeks,
                $rotationLength,
                $anchor,
                $date,
                ['works' => $works, 'schedule' => $schedule]
            ),
        ];

        return self::findConflicts($appointments, $scheduleByDate);
    }
}
