<?php

namespace App\Models;

/** Contactos derivados a un comercial por el asistente de voz (handoff). */
class Lead extends Model
{
    protected static string $table = 'leads';

    public static function listWithStore(int $limit = 200): array
    {
        return static::query(
            'SELECT l.*, s.name AS store_name
             FROM leads l LEFT JOIN stores s ON s.id = l.store_id
             ORDER BY l.created_at DESC, l.id DESC LIMIT ' . max(1, $limit)
        );
    }
}
