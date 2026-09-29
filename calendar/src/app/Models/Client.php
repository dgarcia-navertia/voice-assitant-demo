<?php

namespace App\Models;

class Client extends Model
{
    protected static string $table = 'clients';

    public static function all(string $orderBy = 'client_name', string $direction = 'ASC'): array
    {
        return parent::all($orderBy, $direction);
    }

    public static function findByPhone(string $phone): ?array
    {
        $normalizedPhone = static::normalizePhone($phone);
        $matches = static::query(
            "SELECT * FROM clients
             WHERE REPLACE(REPLACE(REPLACE(REPLACE(client_phone, ' ', ''), '-', ''), '(', ''), ')', '') = ?
             ORDER BY id ASC
             LIMIT 1",
            [$normalizedPhone]
        );

        return $matches[0] ?? null;
    }

    public const CLIENT_TYPES = ['particular', 'empresa'];

    public static function createClient(string $name, string $phone, string $clientType, ?string $email = null): int
    {
        return static::create([
            'client_name'  => $name,
            'client_phone' => static::normalizePhone($phone),
            'client_email' => $email ?? '',
            'client_type'  => $clientType,
        ]);
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/[\s\-\(\)]/', '', $phone) ?? '';
    }
}
