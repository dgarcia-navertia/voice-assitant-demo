<?php

namespace App\Models;

class Holiday extends Model
{
    protected static string $table = 'holidays';

    /**
     * The single guard used everywhere. A date is closed if a global holiday
     * exists OR a holiday for this specific store exists.
     */
    public static function isClosed(?int $storeId, string $date): bool
    {
        $rows = static::query(
            'SELECT 1 FROM holidays
             WHERE date = ? AND (store_id IS NULL OR store_id = ?) LIMIT 1',
            [$date, $storeId]
        );
        return $rows !== [];
    }

    /** Dedup for global holidays (store_id IS NULL requires IS NULL, not = ?). */
    public static function existsGlobal(string $date): bool
    {
        return static::query(
            'SELECT 1 FROM holidays WHERE date = ? AND store_id IS NULL LIMIT 1',
            [$date]
        ) !== [];
    }

    /** Dedup for a concrete store. */
    public static function existsForStore(string $date, int $storeId): bool
    {
        return static::first(['date' => $date, 'store_id' => $storeId]) !== null;
    }

    /**
     * Admin listing: upcoming first, with store name (LEFT JOIN so global rows
     * keep NULL store_name). Pass null to list all holidays regardless of date.
     */
    public static function listUpcoming(?string $fromDate = null): array
    {
        $where  = '';
        $params = [];
        if ($fromDate !== null) {
            $where    = 'WHERE h.date >= ?';
            $params[] = $fromDate;
        }
        return static::query(
            "SELECT h.*, s.name AS store_name
             FROM holidays h
             LEFT JOIN stores s ON s.id = h.store_id
             {$where}
             ORDER BY h.date ASC, h.store_id ASC",
            $params
        );
    }
}
