<?php

namespace App\Models;

class Setting extends Model
{
    protected static string $table      = 'settings';
    protected static string $primaryKey = 'key';

    public static function get(string $key, mixed $default = null): mixed
    {
        $stmt = static::db()->prepare('SELECT `value` FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = static::db()->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = ?'
        );
        $stmt->execute([$key, $value, $value]);
    }
}
