<?php

namespace App\Models;

class User extends Model
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        return static::first(['email' => $email]);
    }

    public static function findByIcalToken(string $token): ?array
    {
        return static::first(['ical_token' => $token]);
    }

    /**
     * Returns the user's personal calendar feed token, generating and storing
     * one on first use. The token is the only credential for the public .ics
     * feed, so it must be unguessable.
     */
    public static function ensureIcalToken(int $userId): ?string
    {
        $user = static::find($userId);
        if (!$user) {
            return null;
        }
        if (!empty($user['ical_token'])) {
            return $user['ical_token'];
        }
        $token = bin2hex(random_bytes(32));
        static::update($userId, ['ical_token' => $token]);
        return $token;
    }

    public static function admins(): array
    {
        return static::where(['role' => 'admin'], 'name');
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
