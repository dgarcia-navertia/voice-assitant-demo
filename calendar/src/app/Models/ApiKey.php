<?php

namespace App\Models;

class ApiKey extends Model
{
    protected static string $table = 'api_keys';

    public static function findByKey(string $key): ?array
    {
        return static::first(['key' => $key, 'active' => 1]);
    }
}
