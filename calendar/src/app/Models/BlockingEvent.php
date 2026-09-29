<?php

namespace App\Models;

class BlockingEvent extends Model
{
    protected static string $table = 'blocking_events';

    /**
     * Blocking events for a commercial on a date (Y-m-d), used by availability.
     */
    public static function forCommercialOnDate(int $commercialId, string $date): array
    {
        return static::query(
            'SELECT * FROM blocking_events
             WHERE commercial_id = ? AND DATE(starts_at) = ?
             ORDER BY starts_at ASC',
            [$commercialId, $date]
        );
    }

    /**
     * Blocking events for a date with the commercial's name, optionally limited
     * to one commercial or to the commercials of one store.
     */
    public static function listForDate(string $date, ?int $commercialId = null, ?int $storeId = null): array
    {
        return static::listInRange($date, $date, $commercialId, $storeId);
    }

    /**
     * Blocking events for a date range with the commercial's name, optionally
     * limited to one commercial or to the commercials of one store.
     */
    public static function listInRange(
        string $dateFrom,
        string $dateTo,
        ?int $commercialId = null,
        ?int $storeId = null
    ): array {
        $where  = ['DATE(b.ends_at) >= ?', 'DATE(b.starts_at) <= ?'];
        $params = [$dateFrom, $dateTo];

        if ($commercialId !== null) {
            $where[]  = 'b.commercial_id = ?';
            $params[] = $commercialId;
        }
        if ($storeId !== null) {
            $where[]  = 'c.store_id = ?';
            $params[] = $storeId;
        }

        return static::query(
            'SELECT b.*, u.name AS commercial_name
             FROM blocking_events b
             JOIN commercials c ON c.id = b.commercial_id
             JOIN users u ON u.id = c.id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY b.starts_at ASC',
            $params
        );
    }

    /**
     * Blocking events of a commercial overlapping the [startsAt, endsAt) window.
     */
    public static function overlapping(int $commercialId, string $startsAt, string $endsAt): array
    {
        return static::query(
            'SELECT * FROM blocking_events
             WHERE commercial_id = ? AND starts_at < ? AND ends_at > ?',
            [$commercialId, $endsAt, $startsAt]
        );
    }

    /**
     * Blocking events of a commercial from $fromDate (Y-m-d) onward, for their
     * personal .ics calendar feed.
     */
    public static function forCommercialFeed(int $commercialId, string $fromDate): array
    {
        return static::query(
            'SELECT * FROM blocking_events
             WHERE commercial_id = ? AND DATE(starts_at) >= ?
             ORDER BY starts_at ASC',
            [$commercialId, $fromDate]
        );
    }
}
