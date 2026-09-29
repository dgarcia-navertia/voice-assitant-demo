<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Appointment;
use App\Models\BlockingEvent;
use App\Models\Commercial;

class BlockingEventController extends Controller
{
    public function create(array $params = []): void
    {
        $this->render('blocking-events/create.php', [
            'commercials' => $this->selectableCommercials(),
        ]);
    }

    public function store(array $params = []): void
    {
        $commercialId = (int) (Auth::isCommercial() ? Auth::id() : $this->input('commercial_id'));
        $startDate    = trim((string) $this->input('start_date', ''));
        $endDate      = trim((string) $this->input('end_date', '')) ?: $startDate;
        $allDay       = $this->input('all_day') === '1';
        $startTime    = trim((string) $this->input('start_time', ''));
        $endTime      = trim((string) $this->input('end_time', ''));
        $reason       = trim((string) $this->input('reason', ''));

        $errors = [];
        if (!$commercialId) {
            $errors['commercial_id'] = 'El comercial es obligatorio.';
        }
        if ($startDate === '') {
            $errors['start_date'] = 'La fecha de inicio es obligatoria.';
        } elseif ($endDate < $startDate) {
            $errors['end_date'] = 'La fecha de fin debe ser igual o posterior a la de inicio.';
        } elseif ((strtotime($endDate) - strtotime($startDate)) / 86400 > 366) {
            $errors['end_date'] = 'El rango no puede superar un año.';
        }
        if (!$allDay) {
            if ($startTime === '' || $endTime === '') {
                $errors['time'] = 'La hora de inicio y de fin son obligatorias.';
            } elseif ($endTime <= $startTime) {
                $errors['time'] = 'La hora de fin debe ser posterior a la de inicio.';
            }
        }

        if (!$errors && !$this->canManage($commercialId)) {
            $this->forbidden();
            return;
        }

        if (!$errors) {
            // Un bloqueo por día del rango; en multi-día las horas aplican a cada día.
            $windows = [];
            for ($day = new \DateTime($startDate); $day->format('Y-m-d') <= $endDate; $day->modify('+1 day')) {
                $date      = $day->format('Y-m-d');
                $windows[] = [
                    'starts_at' => $allDay ? "{$date} 00:00:00" : "{$date} {$startTime}:00",
                    'ends_at'   => $allDay ? "{$date} 23:59:59" : "{$date} {$endTime}:00",
                ];
            }

            // Solaparse con otro bloqueo no está permitido (p. ej. ya hay un día completo).
            $blockedDays = [];
            foreach ($windows as $window) {
                if (BlockingEvent::overlapping($commercialId, $window['starts_at'], $window['ends_at'])) {
                    $blockedDays[] = date('d/m/Y', strtotime($window['starts_at']));
                }
            }
            if ($blockedDays) {
                $errors['overlap'] = 'Ya existe un bloqueo que se solapa los días: ' . implode(', ', $blockedDays) . '.';
            }
        }

        if ($errors) {
            $this->render('blocking-events/create.php', [
                'commercials' => $this->selectableCommercials(),
                'errors'      => $errors,
            ]);
            return;
        }

        // Citas confirmadas que quedarían dentro del bloqueo: avisar antes de crear.
        $overlapping = [];
        foreach ($windows as $window) {
            $durationMinutes = (int) ceil((strtotime($window['ends_at']) - strtotime($window['starts_at'])) / 60);
            $overlapping     = array_merge(
                $overlapping,
                Appointment::confirmedOverlapping($commercialId, $window['starts_at'], $durationMinutes)
            );
        }
        if ($overlapping && $this->input('force') !== '1') {
            $this->render('blocking-events/create.php', [
                'commercials' => $this->selectableCommercials(),
                'overlapping' => $overlapping,
            ]);
            return;
        }

        foreach ($windows as $window) {
            BlockingEvent::create([
                'commercial_id' => $commercialId,
                'starts_at'     => $window['starts_at'],
                'ends_at'       => $window['ends_at'],
                'all_day'       => $allDay ? 1 : 0,
                'reason'        => $reason !== '' ? $reason : null,
                'created_by'    => Auth::id(),
            ]);
        }

        $this->redirect('/appointments?date=' . $startDate);
    }

    public function destroy(array $params = []): void
    {
        $block = BlockingEvent::find((int) $params['id']);
        if (!$block) { $this->notFound(); return; }

        if (!$this->canManage((int) $block['commercial_id'])) {
            $this->forbidden();
            return;
        }

        BlockingEvent::delete((int) $params['id']);
        $this->redirect('/appointments?date=' . date('Y-m-d', strtotime($block['starts_at'])));
    }

    /**
     * Admins manage any commercial's blocks, managers those of their store,
     * commercials only their own.
     */
    private function canManage(int $commercialId): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }
        if (Auth::isCommercial()) {
            return $commercialId === Auth::id();
        }
        if (Auth::isManager()) {
            $commercial = Commercial::find($commercialId);
            return $commercial
                && $commercial['store_id'] !== null
                && (int) $commercial['store_id'] === Auth::storeId();
        }
        return false;
    }

    /**
     * Commercials selectable in the form: all for admins, the store's for
     * managers, empty for commercials (they only block their own schedule).
     */
    private function selectableCommercials(): array
    {
        if (Auth::isAdmin()) {
            return Commercial::forStore(null);
        }
        if (Auth::isManager() && Auth::storeId() !== null) {
            return Commercial::forStore(Auth::storeId());
        }
        return [];
    }
}
