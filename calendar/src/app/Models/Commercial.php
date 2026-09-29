<?php

namespace App\Models;

use App\Services\ScheduleRotation;

class Commercial extends Model
{
    protected static string $table = 'commercials';

    /** Weekday columns indexed to match PHP date('N') (1 = Monday … 7 = Sunday). */
    public const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * The person's name lives in `users`, never in `commercials` — one column,
     * one writer, so it cannot go stale when someone is edited from the
     * "Usuarios" window instead of "Comerciales".
     *
     * Every read in this model joins it back in as `name`, so callers keep
     * seeing the same array shape they always did. That is also why `find()`
     * is overridden: `Model::find()` would do a bare `SELECT *` and hand back
     * a row with no name at all, and fourteen call sites read it.
     */
    public static function find(int $id): ?array
    {
        $rows = static::query(
            'SELECT c.*, u.name AS name
             FROM commercials c
             JOIN users u ON u.id = c.id
             WHERE c.id = ?',
            [$id]
        );
        return $rows[0] ?? null;
    }

    public static function forStore(?int $storeId = null, ?int $serviceId = null): array
    {
        $where  = [];
        $params = [];
        if ($storeId !== null) {
            $where[]  = 'c.store_id = ?';
            $params[] = $storeId;
        }
        if ($serviceId !== null) {
            // specialties is a JSON array of services.id; NULL never matches,
            // so people without specialties only receive service-less bookings.
            $where[]  = 'JSON_CONTAINS(c.specialties, ?)';
            $params[] = (string) $serviceId;
        }

        // role drives the booking priority (commercial > manager > admin).
        return static::query(
            'SELECT c.*, u.name AS name, u.role AS role
             FROM commercials c
             JOIN users u ON u.id = c.id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY u.name',
            $params
        );
    }

    public static function searchByName(string $name): array
    {
        $name = trim($name);
        return static::query(
            'SELECT c.*, u.name AS name
             FROM commercials c
             JOIN users u ON u.id = c.id
             WHERE LOWER(u.name) LIKE LOWER(?)
             ORDER BY
                CASE
                    WHEN LOWER(u.name) = LOWER(?) THEN 0
                    WHEN LOWER(u.name) LIKE LOWER(?) THEN 1
                    ELSE 2
                END,
                u.name',
            ["%{$name}%", $name, "{$name}%"]
        );
    }

    public static function withStore(?int $storeId = null): array
    {
        $where = $storeId !== null ? 'WHERE c.store_id = ?' : '';
        return static::query(
            "SELECT c.*, u.name AS name, s.name AS store_name, u.email AS email, u.role AS role
             FROM commercials c
             JOIN users  u ON u.id = c.id
             LEFT JOIN stores s ON s.id = c.store_id
             {$where}
             ORDER BY u.name",
            $storeId !== null ? [$storeId] : []
        );
    }

    /**
     * Returns the effective schedule string for $date (Y-m-d), resolved as
     * override > pattern (rotation-aware). Returns null if the commercial does
     * not work that day. $commercial must include rotation_length and
     * rotation_anchor (present on any `SELECT c.*` from this table).
     */
    public static function scheduleForDate(array $commercial, string $date): ?string
    {
        $commercialId = (int) $commercial['id'];

        return ScheduleRotation::resolveSchedule(
            WeekPattern::forCommercial($commercialId),
            (int) ($commercial['rotation_length'] ?? 1),
            $commercial['rotation_anchor'] ?? null,
            $date,
            ScheduleOverride::forCommercialOnDate($commercialId, $date)
        );
    }
}
