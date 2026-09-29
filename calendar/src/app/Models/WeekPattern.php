<?php

namespace App\Models;

/**
 * A commercial's weekly schedule pattern, one row per rotation week
 * (week_index 0..rotation_length-1). Non-rotating commercials (rotation_length
 * = 1) just have a single row at week_index 0.
 */
class WeekPattern extends Model
{
    protected static string $table = 'commercial_week_patterns';

    /**
     * All pattern rows for a commercial, indexed by week_index, each an
     * associative array of weekday => schedule|null. Shape expected by
     * App\Services\ScheduleRotation::resolveSchedule().
     */
    public static function forCommercial(int $commercialId): array
    {
        $rows = static::query(
            'SELECT * FROM commercial_week_patterns WHERE commercial_id = ? ORDER BY week_index',
            [$commercialId]
        );

        $weeks = [];
        foreach ($rows as $row) {
            $weeks[(int) $row['week_index']] = array_intersect_key($row, array_flip(Commercial::WEEKDAYS));
        }
        return $weeks;
    }

    /** Replaces a commercial's whole set of pattern weeks (used when saving rotation_length weeks at once). */
    public static function replaceForCommercial(int $commercialId, array $weeks): void
    {
        static::db()->beginTransaction();
        try {
            static::db()->prepare('DELETE FROM commercial_week_patterns WHERE commercial_id = ?')
                ->execute([$commercialId]);

            $stmt = static::db()->prepare(
                'INSERT INTO commercial_week_patterns
                    (commercial_id, week_index, monday, tuesday, wednesday, thursday, friday, saturday, sunday)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($weeks as $weekIndex => $days) {
                $stmt->execute([
                    $commercialId,
                    $weekIndex,
                    $days['monday'] ?? null,
                    $days['tuesday'] ?? null,
                    $days['wednesday'] ?? null,
                    $days['thursday'] ?? null,
                    $days['friday'] ?? null,
                    $days['saturday'] ?? null,
                    $days['sunday'] ?? null,
                ]);
            }
            static::db()->commit();
        } catch (\Throwable $e) {
            static::db()->rollBack();
            throw $e;
        }
    }

    /** Upserts a single week row (used by the single-week form: week_index 0). */
    public static function upsertWeek(int $commercialId, int $weekIndex, array $days): void
    {
        static::db()->prepare(
            'INSERT INTO commercial_week_patterns
                (commercial_id, week_index, monday, tuesday, wednesday, thursday, friday, saturday, sunday)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                monday = VALUES(monday), tuesday = VALUES(tuesday), wednesday = VALUES(wednesday),
                thursday = VALUES(thursday), friday = VALUES(friday), saturday = VALUES(saturday),
                sunday = VALUES(sunday)'
        )->execute([
            $commercialId,
            $weekIndex,
            $days['monday'] ?? null,
            $days['tuesday'] ?? null,
            $days['wednesday'] ?? null,
            $days['thursday'] ?? null,
            $days['friday'] ?? null,
            $days['saturday'] ?? null,
            $days['sunday'] ?? null,
        ]);
    }
}
