<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Commercial;
use App\Models\ScheduleOverride;
use App\Models\User;
use App\Models\WeekPattern;
use App\Services\ScheduleChangeGuard;
use App\Services\ScheduleRotation;

/**
 * Per-date schedule exceptions ("libra este día concreto", "trabaja un
 * horario distinto un día puntual") for a single commercial, reachable from
 * the commercial edit view. Follows the same admin/manager permission model
 * as CommercialController: admins manage anyone, managers only commercials
 * of their own store — re-checked server-side on every action.
 */
class ScheduleOverrideController extends Controller
{
    public function index(array $params = []): void
    {
        $commercial = $this->authorizedCommercial((int) $params['id']);
        if ($commercial === null) {
            return;
        }

        $this->render('schedule_overrides/index.php', [
            'commercial' => $commercial,
            'overrides'  => ScheduleOverride::upcomingForCommercial((int) $commercial['id'], date('Y-m-d')),
            'success'    => $this->input('success'),
        ]);
    }

    public function store(array $params = []): void
    {
        $commercial = $this->authorizedCommercial((int) $params['id']);
        if ($commercial === null) {
            return;
        }
        $commercialId = (int) $commercial['id'];

        $date       = trim((string) $this->input('date', ''));
        $worksInput = $this->input('works', '1');
        $works      = $worksInput === '1' || $worksInput === 'true';
        $scheduleIn = trim((string) $this->input('schedule', ''));
        $schedule   = $scheduleIn === '' ? null : $scheduleIn;

        $errors = [];
        if ($date === '' || !$this->isValidDate($date)) {
            $errors['date'] = 'La fecha es obligatoria y debe ser válida.';
        }

        if (!$errors) {
            $rotationLength  = (int) ($commercial['rotation_length'] ?? 1);
            $anchor          = $commercial['rotation_anchor'] ?? null;
            $patternWeeks    = WeekPattern::forCommercial($commercialId);
            $patternDayValue = ScheduleRotation::patternValueForDate($patternWeeks, $rotationLength, $anchor, $date);

            $overrideError = ScheduleChangeGuard::validateOverride($patternDayValue, $works, $schedule);
            if ($overrideError) {
                $errors['schedule'] = $overrideError;
            }
        }

        if (!$errors) {
            $conflicts = ScheduleChangeGuard::checkOverrideChange($commercialId, $date, $works, $schedule);
            if ($conflicts) {
                $errors['schedule'] = ScheduleChangeGuard::conflictMessage($conflicts);
            }
        }

        if ($errors) {
            $this->render('schedule_overrides/index.php', [
                'commercial' => $commercial,
                'overrides'  => ScheduleOverride::upcomingForCommercial($commercialId, date('Y-m-d')),
                'errors'     => $errors,
            ]);
            return;
        }

        // Upsert on duplicate date (UNIQUE commercial_id + date).
        $existing = ScheduleOverride::first(['commercial_id' => $commercialId, 'date' => $date]);
        if ($existing) {
            ScheduleOverride::update((int) $existing['id'], ['works' => $works ? 1 : 0, 'schedule' => $schedule]);
        } else {
            ScheduleOverride::create([
                'commercial_id' => $commercialId,
                'date'          => $date,
                'works'         => $works ? 1 : 0,
                'schedule'      => $schedule,
            ]);
        }

        $this->redirect('/commercials/' . $commercialId . '/overrides?success=1');
    }

    public function destroy(array $params = []): void
    {
        $commercial = $this->authorizedCommercial((int) $params['id']);
        if ($commercial === null) {
            return;
        }

        $override = ScheduleOverride::find((int) $params['overrideId']);
        if (!$override || (int) $override['commercial_id'] !== (int) $commercial['id']) {
            $this->notFound();
            return;
        }

        ScheduleOverride::delete((int) $override['id']);
        $this->redirect('/commercials/' . $commercial['id'] . '/overrides?success=1');
    }

    /**
     * Resolves the target commercial and re-checks permissions server-side
     * (admin: any commercial; manager: only their own store). Renders the
     * appropriate error response and returns null when access is denied.
     */
    private function authorizedCommercial(int $id): ?array
    {
        $user = User::find($id);
        if (!$user || !in_array($user['role'], ['commercial', 'manager', 'admin'], true)) {
            $this->notFound();
            return null;
        }

        $commercial = Commercial::find($id);
        if (!$commercial) {
            $this->notFound();
            return null;
        }

        if (!$this->canManageStore((int) ($commercial['store_id'] ?? 0))) {
            $this->forbidden();
            return null;
        }

        return array_merge($user, $commercial);
    }

    private function canManageStore(int $storeId): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }
        return Auth::isManager() && Auth::storeId() !== null && $storeId === Auth::storeId();
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }
}
