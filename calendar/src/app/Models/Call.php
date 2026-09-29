<?php

namespace App\Models;

/**
 * Llamadas del asistente. `call_sid` = CallSid de Twilio = `transcripts.sid`.
 * La fila la crea PHP al pedir el dial-out y la actualizan los callbacks de
 * estado que el bot reenvia a POST /mcp/calls/status.
 */
class Call extends Model
{
    protected static string $table = 'calls';

    /** Estados que reconoce la UI. Twilio anade algunos (busy, no-answer, canceled). */
    public const STATUSES = ['queued', 'ringing', 'in-progress', 'completed', 'failed', 'busy', 'no-answer', 'canceled'];
    public const TERMINAL = ['completed', 'failed', 'busy', 'no-answer', 'canceled'];

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    public static function findBySid(string $sid): ?array
    {
        return static::first(['call_sid' => $sid]);
    }

    /** Inserta o actualiza por call_sid. Un estado terminal no se pisa con uno no terminal. */
    public static function upsertStatus(string $sid, string $status, array $extra = []): array
    {
        $existing = static::findBySid($sid);
        $fields = array_filter([
            'to_number'        => $extra['to'] ?? null,
            'from_number'      => $extra['from'] ?? null,
            'direction'        => $extra['direction'] ?? null,
            'duration_seconds' => isset($extra['duration']) ? (int) $extra['duration'] : null,
            'error'            => isset($extra['error']) ? mb_substr((string) $extra['error'], 0, 500) : null,
        ], fn($v) => $v !== null && $v !== '');

        if ($existing === null) {
            static::create(['call_sid' => $sid, 'status' => $status] + $fields);
        } else {
            if (self::isTerminal((string) $existing['status']) && !self::isTerminal($status)) {
                $status = (string) $existing['status'];
            }
            static::update((int) $existing['id'], ['status' => $status] + $fields);
        }
        return static::findBySid($sid);
    }

    public static function recent(int $limit = 50): array
    {
        return static::query(
            'SELECT c.*, u.name AS created_by_name,
                    (SELECT COUNT(*) FROM transcripts t WHERE t.sid = c.call_sid) AS turns
             FROM calls c LEFT JOIN users u ON u.id = c.created_by
             ORDER BY c.created_at DESC, c.id DESC LIMIT ' . max(1, $limit)
        );
    }
}
