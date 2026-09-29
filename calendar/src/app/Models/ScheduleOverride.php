<?php

namespace App\Models;

class ScheduleOverride extends Model
{
    protected static string $table = 'schedule_overrides';

    /**
     * The override for a commercial on a specific date, shaped for
     * App\Services\ScheduleRotation::resolveSchedule(): ['works' => bool,
     * 'schedule' => ?string]. Null when no override exists for that date.
     */
    public static function forCommercialOnDate(int $commercialId, string $date): ?array
    {
        $row = static::first(['commercial_id' => $commercialId, 'date' => $date]);
        if ($row === null) {
            return null;
        }
        return ['works' => (bool) $row['works'], 'schedule' => $row['schedule']];
    }

    /**
     * Future (>= today), non-inherited overrides for a commercial — used when
     * listing/managing exceptions. Included mainly for completeness; not
     * required by the resolver itself.
     */
    public static function upcomingForCommercial(int $commercialId, string $fromDate): array
    {
        return static::query(
            'SELECT * FROM schedule_overrides WHERE commercial_id = ? AND date >= ? ORDER BY date',
            [$commercialId, $fromDate]
        );
    }
}
