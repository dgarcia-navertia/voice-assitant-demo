<?php

namespace App\Models;

class Appointment extends Model
{
    protected static string $table = 'appointments';

    public static function withDetails(int $id): ?array
    {
        $rows = static::query(
            'SELECT a.*,
                    u.name  AS commercial_name,
                    u.email AS commercial_email,
                    s.name  AS store_name,
                    s.address AS store_address,
                    s.phone_number AS store_phone,
                    cb.name AS created_by_name,
                    cl.client_name AS client_name,
                    cl.client_phone AS client_phone,
                    sv.name AS service_name
             FROM appointments a
             JOIN      users  u  ON u.id  = a.commercial_id
             JOIN      stores s  ON s.id  = a.store_id
             JOIN      clients cl ON cl.id = a.client_id
             LEFT JOIN users  cb ON cb.id = a.created_by
             LEFT JOIN services sv ON sv.id = a.service_id
             WHERE a.id = ?',
            [$id]
        );
        return $rows[0] ?? null;
    }

    public static function listWithDetails(
        array   $conditions = [],
        ?string $dateFrom   = null,
        ?string $dateTo     = null
    ): array {
        $where  = [];
        $params = [];

        $allowedConditions = ['store_id', 'commercial_id', 'status'];
        foreach ($conditions as $k => $v) {
            if (!in_array($k, $allowedConditions, true)) {
                continue;
            }
            $where[]  = "a.`{$k}` = ?";
            $params[] = $v;
        }
        if ($dateFrom) {
            $where[]  = 'DATE(a.starts_at) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[]  = 'DATE(a.starts_at) <= ?';
            $params[] = $dateTo;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return static::query(
            "SELECT a.*,
                    u.name AS commercial_name,
                    s.name AS store_name,
                    cl.client_name AS client_name,
                    cl.client_phone AS client_phone,
                    sv.name AS service_name
             FROM appointments a
             JOIN users  u  ON u.id = a.commercial_id
             JOIN stores s  ON s.id = a.store_id
             JOIN clients cl ON cl.id = a.client_id
             LEFT JOIN services sv ON sv.id = a.service_id
             {$whereClause}
             ORDER BY a.starts_at ASC",
            $params
        );
    }

    /**
     * Non-cancelled appointments of a commercial from $fromDate (Y-m-d) onward,
     * for their personal .ics calendar feed. Includes client/store details.
     */
    public static function forCommercialFeed(int $commercialId, string $fromDate): array
    {
        return static::query(
            'SELECT a.*,
                    s.name AS store_name,
                    s.address AS store_address,
                    cl.client_name AS client_name,
                    cl.client_phone AS client_phone
             FROM appointments a
             JOIN stores  s  ON s.id  = a.store_id
             JOIN clients cl ON cl.id = a.client_id
             WHERE a.commercial_id = ?
               AND a.status = "confirmed"
               AND DATE(a.starts_at) >= ?
             ORDER BY a.starts_at ASC',
            [$commercialId, $fromDate]
        );
    }

    /**
     * Non-cancelled appointments of a commercial from $fromDateTime (Y-m-d H:i:s)
     * onward. Used by ScheduleChangeGuard as a coarse DB-side filter; the guard
     * itself re-checks the exact cutoff and status.
     */
    public static function nonCancelledFromDate(int $commercialId, string $fromDateTime): array
    {
        return static::query(
            'SELECT * FROM appointments
             WHERE commercial_id = ? AND status != "cancelled" AND starts_at >= ?
             ORDER BY starts_at',
            [$commercialId, $fromDateTime]
        );
    }

    /** Non-cancelled appointments of a commercial on one specific date (Y-m-d). */
    public static function nonCancelledOnDate(int $commercialId, string $date, ?int $excludeId = null): array
    {
        $sql    = 'SELECT * FROM appointments
                   WHERE commercial_id = ? AND DATE(starts_at) = ? AND status != "cancelled"';
        $params = [$commercialId, $date];
        if ($excludeId !== null) {
            $sql     .= ' AND id != ?';
            $params[] = $excludeId;
        }
        return static::query($sql, $params);
    }

    public static function confirmedOverlapping(
        int     $commercialId,
        string  $startsAt,
        int     $durationMinutes,
        ?int    $excludeId = null
    ): array {
        $endsAt = date('Y-m-d H:i:s', strtotime($startsAt) + $durationMinutes * 60);
        $sql    = 'SELECT * FROM appointments
                   WHERE commercial_id = ? AND status = "confirmed"
                     AND starts_at < ? AND DATE_ADD(starts_at, INTERVAL duration_minutes MINUTE) > ?';
        $params = [$commercialId, $endsAt, $startsAt];
        if ($excludeId !== null) {
            $sql     .= ' AND id != ?';
            $params[] = $excludeId;
        }
        return static::query($sql, $params);
    }
}
