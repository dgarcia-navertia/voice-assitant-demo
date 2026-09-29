<?php

namespace App\Services;

use App\Models\Commercial;
use DateTime;

/**
 * Pure parsing/validation for the N-week rotation schedule section of the
 * commercial create/edit form. No DB access — testable in isolation from
 * the controller that wires it to WeekPattern/ScheduleChangeGuard.
 */
class ScheduleFormInput
{
    public const MIN_ROTATION_LENGTH = 1;
    public const MAX_ROTATION_LENGTH = 6;

    /**
     * Parses the rotation_length form field. Non-numeric input falls back to
     * 1 (a plain, non-rotating week) rather than throwing — out-of-range
     * values are still flagged by validate().
     */
    public static function parseRotationLength(mixed $raw): int
    {
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        return $value === false ? 1 : $value;
    }

    /**
     * Validates the rotation_length/rotation_anchor combination. Returns a
     * Spanish, user-facing error message, or null when valid.
     */
    public static function validate(int $rotationLength, ?string $anchorRaw): ?string
    {
        if ($rotationLength < self::MIN_ROTATION_LENGTH || $rotationLength > self::MAX_ROTATION_LENGTH) {
            return sprintf(
                'La duración de la rotación debe ser un número entre %d y %d semanas.',
                self::MIN_ROTATION_LENGTH,
                self::MAX_ROTATION_LENGTH
            );
        }

        $anchorRaw = $anchorRaw !== null ? trim($anchorRaw) : '';

        if ($rotationLength > 1 && $anchorRaw === '') {
            return 'Debes indicar una fecha de referencia (ancla) cuando la rotación tiene más de una semana.';
        }
        if ($anchorRaw !== '' && !self::isValidDate($anchorRaw)) {
            return 'La fecha de referencia de la rotación no es válida.';
        }
        return null;
    }

    /**
     * Parses the `weeks[i][weekday]` POST structure into
     * [week_index => [weekday => schedule|null]] for week_index 0..$rotationLength-1.
     * Any weeks submitted beyond that range (e.g. left over from a rotation
     * that just shrank in the browser) are dropped here — the caller persists
     * only what this returns.
     */
    public static function parseWeeks(array $weeksInput, int $rotationLength): array
    {
        $weeks = [];
        for ($i = 0; $i < $rotationLength; $i++) {
            $days = is_array($weeksInput[$i] ?? null) ? $weeksInput[$i] : [];
            $week = [];
            foreach (Commercial::WEEKDAYS as $day) {
                $value = trim((string) ($days[$day] ?? ''));
                $week[$day] = $value === '' ? null : $value;
            }
            $weeks[$i] = $week;
        }
        return $weeks;
    }

    private static function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }
}
