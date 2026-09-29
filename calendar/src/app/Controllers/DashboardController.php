<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Appointment;
use App\Models\Commercial;
use App\Models\Setting;
use App\Services\AvailabilityService;

/**
 * Single "Agenda" page. Renders both a list view (day + next day) and a weekly
 * calendar view; the client picks which one to show and remembers it in
 * localStorage. /calendar redirects here with ?view=calendar.
 *
 * An admin can point the whole page at another person with ?user=<id>.
 */
class DashboardController extends Controller
{
    public function index(array $params = []): void
    {
        $today = date('Y-m-d');
        $date  = $this->validDate($this->input('date')) ?? $today;

        $agents  = Auth::isAdmin() ? Commercial::withStore(null) : [];
        $agentId = $this->selectedAgent($agents);

        $this->render('dashboard/index.php', [
            'viewMode'  => $this->input('view') === 'calendar' ? 'calendar' : 'list',
            'date'      => $date,
            'today'     => $today,
            'agents'    => $agents,
            'agentId'   => $agentId,
            'listDays'  => $this->listDays($date, $agentId),
            'week'      => $this->week($this->validDate($this->input('week')) ?? $date, $today, $agentId ?? (int) Auth::id()),
            // Día que se abre en móvil al venir de la tira de días de otra semana.
            'dayParam'  => $this->validDate($this->input('day')),
        ]);
    }

    /** The ?user selection, only honoured for admins and only if that person is bookable. */
    private function selectedAgent(array $agents): ?int
    {
        if (!Auth::isAdmin()) {
            return null;
        }
        $id = (int) $this->input('user', 0);
        foreach ($agents as $agent) {
            if ((int) $agent['id'] === $id) {
                return $id;
            }
        }
        return null;
    }

    /** The day you are on plus the following day, each with its appointments. */
    private function listDays(string $date, ?int $agentId): array
    {
        $conditions = [];
        if ($agentId !== null) {
            $conditions['commercial_id'] = $agentId;
        } elseif (Auth::isCommercial()) {
            $conditions['commercial_id'] = Auth::id();
        } elseif (Auth::isManager()) {
            $conditions['store_id'] = Auth::storeId();
        }

        $days = [];
        foreach ([$date, date('Y-m-d', strtotime("{$date} +1 day"))] as $d) {
            $days[] = [
                'date'         => $d,
                'appointments' => Appointment::listWithDetails($conditions, $d, $d),
            ];
        }
        return $days;
    }

    /** Weekly calendar data for one person (their appointments + their free slots). */
    private function week(string $anchor, string $today, int $userId): array
    {
        $monday     = date('Y-m-d', strtotime('monday this week', strtotime($anchor)));
        $commercial = Commercial::find($userId);
        $interval   = max(5, (int) Setting::get('slot_interval_minutes', 15));

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = date('Y-m-d', strtotime("{$monday} +{$i} days"));

            $freeRanges = [];
            if ($commercial && $d >= $today) {
                $result = AvailabilityService::getSlots(
                    (int) $commercial['store_id'],
                    $d,
                    $userId,
                    null,
                    null,
                    $interval
                );
                $freeRanges = $this->coalesce(array_column($result['slots'], 'time'), $interval);
            }

            $days[] = [
                'date'         => $d,
                'appointments' => Appointment::listWithDetails(['commercial_id' => $userId], $d, $d),
                'freeRanges'   => $freeRanges,
            ];
        }

        [$startHour, $endHour] = $this->gridBounds($days);

        return [
            'monday'     => $monday,
            'prevWeek'   => date('Y-m-d', strtotime("{$monday} -7 days")),
            'nextWeek'   => date('Y-m-d', strtotime("{$monday} +7 days")),
            'thisMonday' => date('Y-m-d', strtotime('monday this week')),
            'isBookable' => $commercial !== null,
            'startHour'  => $startHour,
            'endHour'    => $endHour,
            'days'       => $days,
        ];
    }

    /**
     * [firstHour, lastHour] for the time grid. Always spans at least 07:00–20:00
     * (grey-filled when there is nothing there) and only widens to avoid clipping
     * an appointment or free window that falls outside that range.
     */
    private function gridBounds(array $days): array
    {
        $min = 7 * 60;
        $max = 20 * 60;
        foreach ($days as $day) {
            foreach ($day['appointments'] as $apt) {
                $s = (int) date('G', strtotime($apt['starts_at'])) * 60 + (int) date('i', strtotime($apt['starts_at']));
                $min = min($min, $s);
                $max = max($max, $s + (int) $apt['duration_minutes']);
            }
            foreach ($day['freeRanges'] as $r) {
                $min = min($min, $this->toMinutes($r['from']));
                $max = max($max, $this->toMinutes($r['to']));
            }
        }
        return [intdiv($min, 60), (int) ceil($max / 60)];
    }

    private function toMinutes(string $hhmm): int
    {
        return (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 3, 2);
    }

    /**
     * Turns a sorted list of slot start times (spaced by $interval within each
     * open block) into [['from' => 'HH:MM', 'to' => 'HH:MM'], ...] free windows.
     * The window end is the last start plus one interval, exact because slots
     * were generated with duration == interval.
     */
    private function coalesce(array $times, int $interval): array
    {
        sort($times);
        $ranges = [];
        $start  = null;
        $prev   = null;

        foreach ($times as $time) {
            $mins = $this->toMinutes($time);
            if ($start === null) {
                $start = $time;
            } elseif ($mins !== $prev + $interval) {
                $ranges[] = ['from' => $start, 'to' => $this->addMinutes($prev, $interval)];
                $start    = $time;
            }
            $prev = $mins;
        }
        if ($start !== null) {
            $ranges[] = ['from' => $start, 'to' => $this->addMinutes($prev, $interval)];
        }
        return $ranges;
    }

    private function addMinutes(int $minsOfDay, int $add): string
    {
        $total = $minsOfDay + $add;
        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    private function validDate(mixed $date): ?string
    {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        return checkdate($m, $d, $y) ? $date : null;
    }
}
