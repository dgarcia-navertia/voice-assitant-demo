<?php

namespace App\Models;

class Store extends Model
{
    protected static string $table = 'stores';

    public static function schedules(int $storeId): ?array
    {
        $rows = static::query(
            'SELECT mon_to_friday, saturday FROM store_schedules WHERE store_id = ? LIMIT 1',
            [$storeId]
        );
        return $rows[0] ?? null;
    }

    /** [storeId => ['mon_to_friday' => ?string, 'saturday' => ?string]] for the given stores. */
    public static function schedulesByIds(array $storeIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $storeIds)));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = static::query(
            "SELECT store_id, mon_to_friday, saturday
             FROM store_schedules WHERE store_id IN ({$placeholders})",
            $ids
        );

        $map = [];
        foreach ($ids as $id) {
            $map[$id] = ['mon_to_friday' => null, 'saturday' => null];
        }
        foreach ($rows as $row) {
            $map[(int) $row['store_id']] = [
                'mon_to_friday' => $row['mon_to_friday'],
                'saturday'      => $row['saturday'],
            ];
        }
        return $map;
    }

    public static function saveSchedule(int $storeId, ?string $monToFriday, ?string $saturday): void
    {
        static::query(
            'INSERT INTO store_schedules (store_id, mon_to_friday, saturday)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE mon_to_friday = VALUES(mon_to_friday), saturday = VALUES(saturday)',
            [$storeId, $monToFriday, $saturday]
        );
    }

    public static function withCommercialCount(): array
    {
        return static::query(
            'SELECT s.*, ss.mon_to_friday, ss.saturday, COUNT(c.id) AS commercial_count
             FROM stores s
             LEFT JOIN store_schedules ss ON ss.store_id = s.id
             LEFT JOIN commercials c ON c.store_id = s.id
             GROUP BY s.id, ss.mon_to_friday, ss.saturday
             ORDER BY s.name'
        );
    }
}
