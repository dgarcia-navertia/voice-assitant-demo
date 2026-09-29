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

    public const HANDOFF_KEY = 'handoff_phone_number';

    /** Fila completa (con quien y cuando la cambio), o null. */
    public static function row(string $key): ?array
    {
        $rows = static::query(
            'SELECT s.`key`, s.`value`, s.updated_at, s.updated_by, u.name AS updated_by_name
             FROM settings s LEFT JOIN users u ON u.id = s.updated_by WHERE s.`key` = ?',
            [$key]
        );
        return $rows[0] ?? null;
    }

    /** Guarda un ajuste registrando quien lo cambia. */
    public static function setAudited(string $key, string $value, ?int $userId): void
    {
        static::query(
            'INSERT INTO settings (`key`, `value`, updated_by, updated_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
            [$key, $value, $userId]
        );
    }

    /**
     * Numero de traspaso vigente: el guardado por el admin y, si no hay, el de
     * HANDOFF_PHONE_NUMBER (entorno). Devuelve [numero, origen].
     *
     * @return array{0:string,1:string}
     */
    public static function handoffNumber(): array
    {
        $stored = trim((string) static::get(self::HANDOFF_KEY, ''));
        if ($stored !== '') {
            return [$stored, 'db'];
        }
        return [trim((string) \App\Env::get('HANDOFF_PHONE_NUMBER', '')), 'env'];
    }
}
