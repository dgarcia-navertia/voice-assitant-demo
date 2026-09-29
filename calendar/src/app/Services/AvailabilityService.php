<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Commercial;
use App\Models\Appointment;
use App\Models\BlockingEvent;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Holiday;

class AvailabilityService
{
    /**
     * Computes appointment availability for a store on a date.
     *
     * For each commercial the slots are
     * HORARIO_COMERCIAL ∩ HORARIO_TIENDA − CITAS_COMERCIAL − BLOQUEOS_COMERCIAL.
     *
     * CITAS_COMERCIAL is every appointment that is not cancelled. A pending
     * appointment holds its slot: every booking is born pending, so if only
     * confirmed ones counted, the same person and time would be offered and
     * booked again for every caller until somebody confirmed the first one.
     *
     * $commercialId restricts to one commercial. Otherwise $serviceId restricts
     * to commercials whose specialties include that service. With neither, every
     * commercial of the store is considered.
     *
     * $excludeAppointmentId ignores one appointment when subtracting bookings.
     * It is what lets an appointment be moved within its own time span: the
     * appointment being replaced must not block the times around it.
     *
     * $durationMinutes overrides the duration derived from $serviceId. The web
     * form lets the user set a custom duration, and the offered times must be
     * the ones where THAT duration fits — otherwise the picker would show slots
     * the booking validation then rejects.
     *
     * Each time is offered if anybody is free then (the availability range stays
     * as wide as possible), but only ONE person is returned per time — the one
     * picked by booking priority (commercial > manager > admin, then lowest id).
     * This keeps the output trivial for an LLM: one choice per time, no IDs to
     * reason about, no duplicates.
     *
     * Returns an LLM-friendly shape:
     *   [
     *     'date'        => '2026-06-10',
     *     'slots'       => [ ['time' => '09:00', 'commercial_id' => 8, 'commercial_name' => 'Laura Pérez'], ... ],
     *     'commercials' => [ '8' => 'Laura Pérez', ... ],  // only those present in slots
     *   ]
     */
    public static function getSlots(
        int $storeId,
        string $date,
        ?int $commercialId = null,
        ?int $serviceId = null,
        ?int $excludeAppointmentId = null,
        ?int $durationMinutes = null
    ): array {
        $empty = ['date' => $date, 'slots' => [], 'commercials' => []];

        $store = Store::find($storeId);
        if (!$store) {
            return self::shape($empty);
        }

        if (Holiday::isClosed($storeId, $date)) {
            return self::shape($empty);
        }

        // Store opening hours bound every commercial's availability.
        $storeRanges = self::storeRangesForDate(Store::schedules($storeId), $date);
        if (!$storeRanges) {
            return self::shape($empty);
        }

        $commercials = self::candidateCommercials($storeId, $commercialId, $serviceId);

        // time (HH:MM) => list of people free then, each with its priority rank.
        $byTime = [];
        foreach ($commercials as $commercial) {
            $scheduleStr = Commercial::scheduleForDate($commercial, $date);
            if (!$scheduleStr) {
                continue; // el comercial no trabaja ese día
            }

            $ranges = self::intersectRanges(
                self::parseSchedule($scheduleStr, $date),
                $storeRanges
            );
            if (!$ranges) {
                continue;
            }

            $duration = $durationMinutes ?? Service::durationFor($serviceId);

            $cid      = (int) $commercial['id'];
            $priority = self::priorityForRole($commercial['role'] ?? 'commercial');
            foreach (self::slotsForCommercial($cid, $date, $ranges, $duration, $excludeAppointmentId) as $time) {
                $byTime[$time][] = [
                    'id'       => $cid,
                    'name'     => $commercial['name'],
                    'priority' => $priority,
                ];
            }
        }

        $slots = [];
        $names = [];
        foreach ($byTime as $time => $people) {
            $winner = self::pickByPriority($people);
            $slots[] = [
                'time'            => $time,
                'commercial_id'   => $winner['id'],
                'commercial_name' => $winner['name'],
            ];
            $names[(string) $winner['id']] = $winner['name'];
        }

        usort($slots, fn($a, $b) => [$a['time'], $a['commercial_id']] <=> [$b['time'], $b['commercial_id']]);

        return self::shape(['date' => $date, 'slots' => $slots, 'commercials' => $names]);
    }

    /**
     * Resolves which person should attend an appointment at $startsAt, applying
     * the same selection used by getSlots(): among everyone free at that exact
     * slot (specialty-filtered when $serviceId is given), pick by priority
     * (commercial > manager > admin, then lowest id). When $commercialId is
     * given, only that person is considered (an explicit choice overrides both
     * priority and the service filter).
     *
     * $excludeAppointmentId leaves one appointment out of the booked ranges, so
     * a reschedule is not blocked by the very appointment it replaces.
     *
     * Returns ['id', 'name', 'duration'] for the chosen person, or null if no
     * eligible person is free at that slot. Use it at booking time so the person
     * is re-validated at the moment of truth, not at the (possibly stale) moment
     * availability was listed.
     */
    public static function resolveCommercial(
        int     $storeId,
        string  $startsAt,
        ?int    $serviceId      = null,
        ?int    $commercialId    = null,
        ?int    $durationMinutes = null,
        ?int    $excludeAppointmentId = null
    ): ?array {
        $date = date('Y-m-d', strtotime($startsAt));

        if (!Store::find($storeId)) {
            return null;
        }
        if (Holiday::isClosed($storeId, $date)) {
            return null;
        }
        $storeRanges = self::storeRangesForDate(Store::schedules($storeId), $date);
        if (!$storeRanges) {
            return null;
        }

        $wanted     = date('H:i', strtotime($startsAt));
        $candidates = [];
        foreach (self::candidateCommercials($storeId, $commercialId, $serviceId) as $commercial) {
            $scheduleStr = Commercial::scheduleForDate($commercial, $date);
            if (!$scheduleStr) {
                continue;
            }
            $ranges = self::intersectRanges(self::parseSchedule($scheduleStr, $date), $storeRanges);
            if (!$ranges) {
                continue;
            }
            $duration = $durationMinutes ?? Service::durationFor($serviceId);
            $cid = (int) $commercial['id'];

            // The requested time must land on this person's free slot grid.
            $free = self::slotsForCommercial($cid, $date, $ranges, $duration, $excludeAppointmentId);
            if (!in_array($wanted, $free, true)) {
                continue;
            }

            $candidates[] = [
                'id'       => $cid,
                'name'     => $commercial['name'],
                'duration' => $duration,
                'priority' => self::priorityForRole($commercial['role'] ?? 'commercial'),
            ];
        }

        if (!$candidates) {
            return null;
        }

        $winner = self::pickByPriority($candidates);
        return ['id' => $winner['id'], 'name' => $winner['name'], 'duration' => $winner['duration']];
    }

    /**
     * The pool of commercials to consider for a store:
     * concrete commercial > service specialty > everyone of the store.
     */
    private static function candidateCommercials(int $storeId, ?int $commercialId, ?int $serviceId): array
    {
        if ($commercialId !== null) {
            return array_filter(
                [Commercial::find($commercialId)],
                fn($c) => $c && (int) $c['store_id'] === $storeId
            );
        }
        return Commercial::forStore($storeId, $serviceId);
    }

    /** Booking priority: lower wins. Commercials first, then managers, then admins. */
    private static function priorityForRole(?string $role): int
    {
        return match ($role) {
            'manager' => 2,
            'admin'   => 3,
            default   => 1, // commercial (or unknown) attends first
        };
    }

    /** Picks the winner from candidates by (priority asc, id asc). */
    private static function pickByPriority(array $people): array
    {
        usort($people, fn($a, $b) => [$a['priority'], $a['id']] <=> [$b['priority'], $b['id']]);
        return $people[0];
    }

    /**
     * Ensures `commercials` serialises as a JSON object ({}), never an empty array ([]).
     */
    private static function shape(array $result): array
    {
        $result['commercials'] = (object) $result['commercials'];
        return $result;
    }

    /**
     * Store ranges for the weekday of $date. Sundays are closed; Saturdays use the
     * `saturday` column, the rest use `mon_to_friday`.
     */
    private static function storeRangesForDate(?array $schedule, string $date): array
    {
        if (!$schedule) {
            return [];
        }

        $dayOfWeek = (int) date('N', strtotime($date));
        if ($dayOfWeek === 7) {
            return []; // domingo: tienda cerrada
        }

        $scheduleStr = $dayOfWeek === 6 ? $schedule['saturday'] : $schedule['mon_to_friday'];
        if (!$scheduleStr) {
            return [];
        }

        return self::parseSchedule($scheduleStr, $date);
    }

    /**
     * Parses "09:30-14:00 y 16:00-21:00" into [{start: ts, end: ts}, ...]
     */
    private static function parseSchedule(string $schedule, string $date): array
    {
        $ranges = [];
        foreach (explode(' y ', $schedule) as $part) {
            [$open, $close] = explode('-', trim($part));
            $ranges[] = [
                'start' => strtotime("{$date} " . trim($open)),
                'end'   => strtotime("{$date} " . trim($close)),
            ];
        }
        return $ranges;
    }

    /**
     * Intersects two sets of {start, end} ranges, returning the overlapping pieces.
     */
    private static function intersectRanges(array $a, array $b): array
    {
        $result = [];
        foreach ($a as $r1) {
            foreach ($b as $r2) {
                $start = max($r1['start'], $r2['start']);
                $end   = min($r1['end'], $r2['end']);
                if ($start < $end) {
                    $result[] = ['start' => $start, 'end' => $end];
                }
            }
        }
        return $result;
    }

    private static function slotsForCommercial(
        int $commercialId,
        string $date,
        array $ranges,
        int $durationMinutes,
        ?int $excludeAppointmentId = null
    ): array {
        $appointments = Appointment::nonCancelledOnDate($commercialId, $date, $excludeAppointmentId);
        $bookedRanges = array_map(fn($a) => [
            'start' => strtotime($a['starts_at']),
            'end'   => strtotime($a['starts_at']) + (int) $a['duration_minutes'] * 60,
        ], $appointments);

        foreach (BlockingEvent::forCommercialOnDate($commercialId, $date) as $block) {
            $bookedRanges[] = [
                'start' => strtotime($block['starts_at']),
                'end'   => strtotime($block['ends_at']),
            ];
        }

        $interval  = (int) Setting::get('slot_interval_minutes', 15);
        $notBefore = (date('Y-m-d') === $date) ? time() : 0;

        return self::generateSlots($ranges, $bookedRanges, $durationMinutes, $interval, $notBefore);
    }

    /**
     * Pure slot generator: steps through $ranges by $intervalMinutes and offers
     * each candidate start where $durationMinutes fit without overlapping $bookedRanges.
     *
     * Decoupling the step (interval) from the slot width (duration) is what allows
     * different services with different durations to coexist without dead-time gaps.
     */
    private static function generateSlots(array $ranges, array $bookedRanges, int $durationMinutes, int $intervalMinutes, int $notBefore = 0): array
    {
        $durationSecs = $durationMinutes * 60;
        $intervalSecs = $intervalMinutes * 60;

        $slots = [];
        foreach ($ranges as $range) {
            $current = $range['start'];
            while ($current + $durationSecs <= $range['end']) {
                if ($current < $notBefore) {
                    $current += $intervalSecs;
                    continue;
                }
                $slotEnd  = $current + $durationSecs;
                $occupied = false;
                foreach ($bookedRanges as $booked) {
                    if ($current < $booked['end'] && $slotEnd > $booked['start']) {
                        $occupied = true;
                        break;
                    }
                }
                if (!$occupied) {
                    $slots[] = date('H:i', $current);
                }
                $current += $intervalSecs;
            }
        }

        return $slots;
    }
}
