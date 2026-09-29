<?php

namespace App\Models;

use App\Database;
use PDO;

abstract class Model
{
    protected static string $table      = '';
    protected static string $primaryKey = 'id';

    protected static function db(): PDO
    {
        return Database::connection();
    }

    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        return static::db()
            ->query('SELECT * FROM `' . static::$table . "` ORDER BY {$orderBy} {$direction}")
            ->fetchAll();
    }

    public static function where(array $conditions, string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $where = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($conditions)));
        $stmt  = static::db()->prepare(
            'SELECT * FROM `' . static::$table . "` WHERE {$where} ORDER BY {$orderBy} {$direction}"
        );
        $stmt->execute(array_values($conditions));
        return $stmt->fetchAll();
    }

    public static function first(array $conditions): ?array
    {
        $where = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($conditions)));
        $stmt  = static::db()->prepare(
            'SELECT * FROM `' . static::$table . "` WHERE {$where} LIMIT 1"
        );
        $stmt->execute(array_values($conditions));
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $columns      = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt         = static::db()->prepare(
            'INSERT INTO `' . static::$table . "` ({$columns}) VALUES ({$placeholders})"
        );
        $stmt->execute(array_values($data));
        return (int) static::db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $set  = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $stmt = static::db()->prepare(
            'UPDATE `' . static::$table . "` SET {$set} WHERE `" . static::$primaryKey . '` = ?'
        );
        $stmt->execute([...array_values($data), $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = static::db()->prepare(
            'DELETE FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = ?'
        );
        $stmt->execute([$id]);
    }

    public static function query(string $sql, array $params = []): array
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            return (int) static::db()
                ->query('SELECT COUNT(*) FROM `' . static::$table . '`')
                ->fetchColumn();
        }
        $where = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($conditions)));
        $stmt  = static::db()->prepare(
            'SELECT COUNT(*) FROM `' . static::$table . "` WHERE {$where}"
        );
        $stmt->execute(array_values($conditions));
        return (int) $stmt->fetchColumn();
    }
}
