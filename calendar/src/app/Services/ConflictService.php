<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BlockingEvent;

class ConflictService
{
    public static function checkAndMark(int $appointmentId): bool
    {
        $appointment = Appointment::find($appointmentId);
        if (!$appointment) {
            return false;
        }

        $overlapping = Appointment::confirmedOverlapping(
            (int) $appointment['commercial_id'],
            $appointment['starts_at'],
            (int) $appointment['duration_minutes'],
            $appointmentId
        );

        $endsAt = date(
            'Y-m-d H:i:s',
            strtotime($appointment['starts_at']) + (int) $appointment['duration_minutes'] * 60
        );
        $blocks = BlockingEvent::overlapping(
            (int) $appointment['commercial_id'],
            $appointment['starts_at'],
            $endsAt
        );

        if (empty($overlapping) && empty($blocks)) {
            Appointment::update($appointmentId, ['has_conflict' => 0]);
            return false;
        }

        Appointment::update($appointmentId, ['has_conflict' => 1]);
        foreach ($overlapping as $other) {
            Appointment::update((int) $other['id'], ['has_conflict' => 1]);
        }

        return true;
    }
}
