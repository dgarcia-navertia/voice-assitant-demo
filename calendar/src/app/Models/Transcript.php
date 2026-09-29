<?php

namespace App\Models;

class Transcript extends Model
{
    protected static string $table = 'transcripts';

    public const ROLES = ['user', 'assistant'];

    /**
     * Highest turn_index already stored for a call, or null if none yet.
     * Lets a resumed call pick up where it left off (SELECT MAX(turn_index)).
     */
    public static function lastTurnIndex(string $sid): ?int
    {
        $stmt = static::db()->prepare(
            'SELECT MAX(turn_index) FROM transcripts WHERE sid = ?'
        );
        $stmt->execute([$sid]);
        $max = $stmt->fetchColumn();

        return $max === null ? null : (int) $max;
    }

    public static function record(
        string $sid,
        string $role,
        string $transcriptText,
        int $turnIndex,
        bool $interrupted = false
    ): int {
        return static::create([
            'sid'             => $sid,
            'role'            => $role,
            'transcript_text' => $transcriptText,
            'turn_index'      => $turnIndex,
            'interrupted'     => $interrupted ? 1 : 0,
        ]);
    }

    /** Turnos de una llamada en orden. */
    public static function forSid(string $sid): array
    {
        return static::query(
            'SELECT * FROM transcripts WHERE sid = ? ORDER BY turn_index ASC, id ASC',
            [$sid]
        );
    }
}
