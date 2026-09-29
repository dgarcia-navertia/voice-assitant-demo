<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Appointment;
use App\Models\BlockingEvent;
use App\Models\Client;
use App\Models\Store;
use App\Models\Commercial;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\AppointmentBookingService;
use App\Services\AvailabilityService;
use App\Services\ConflictService;
use Throwable;

class AppointmentController extends Controller
{
    public function index(array $params = []): void
    {
        $filters = $this->appointmentFilters();

        $conditions = [];
        if (Auth::isCommercial()) {
            $conditions['commercial_id'] = Auth::id();
        } elseif (Auth::isManager()) {
            $conditions['store_id'] = Auth::storeId();
        } else {
            if ($filters['store_id'] !== null) {
                $conditions['store_id'] = $filters['store_id'];
            }
            if ($filters['commercial_id'] !== null) {
                $conditions['commercial_id'] = $filters['commercial_id'];
            }
        }
        if (Auth::isManager() && $filters['commercial_id'] !== null) {
            $conditions['commercial_id'] = $filters['commercial_id'];
        }

        // The status filter is applied here, not in SQL, so the per-status
        // counts above the list still cover every status.
        $appointments = $filters['type'] === 'blocks'
            ? []
            : Appointment::listWithDetails($conditions, $filters['date_from'], $filters['date_to']);
        $statusCounts = ['pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
        foreach ($appointments as $appointment) {
            $statusCounts[$appointment['status']] = ($statusCounts[$appointment['status']] ?? 0) + 1;
        }
        if ($filters['status'] !== '') {
            $appointments = array_values(array_filter(
                $appointments,
                fn($appointment) => $appointment['status'] === $filters['status']
            ));
        }
        $page = $this->positiveInt($this->input('page')) ?? 1;

        $blockCommercialId = Auth::isCommercial() ? Auth::id() : $filters['commercial_id'];
        $blockStoreId      = Auth::isManager() ? Auth::storeId() : (Auth::isAdmin() ? $filters['store_id'] : null);
        $blocks            = $filters['type'] === 'appointments' || (Auth::isManager() && Auth::storeId() === null)
            ? []
            : BlockingEvent::listInRange($filters['date_from'], $filters['date_to'], $blockCommercialId, $blockStoreId);

        $date       = $filters['date_from'];
        $stores     = Auth::isAdmin() ? Store::all('name') : [];
        $attendants = $this->filterAttendants($filters['store_id']);

        $this->render('appointments/index.php', compact('appointments', 'blocks', 'date', 'filters', 'stores', 'attendants', 'statusCounts', 'page'));
    }

    private function appointmentFilters(): array
    {
        $today = date('Y-m-d');
        $legacyDate = $this->validDate($this->input('date'));
        $dateFrom = $this->validDate($this->input('date_from'));
        $dateTo = $this->validDate($this->input('date_to'));

        if (!$dateFrom && !$dateTo && $legacyDate) {
            $dateFrom = $legacyDate;
            $dateTo = $legacyDate;
        }
        $dateFrom ??= $today;
        $dateTo ??= $dateFrom;
        if (strtotime($dateTo) < strtotime($dateFrom)) {
            $dateTo = $dateFrom;
        }

        $status = (string) $this->input('status', '');
        if (!in_array($status, ['', 'pending', 'confirmed', 'cancelled'], true)) {
            $status = '';
        }

        $type = (string) $this->input('type', 'both');
        if (!in_array($type, ['both', 'appointments', 'blocks'], true)) {
            $type = 'both';
        }

        $storeId = $this->positiveInt($this->input('store_id'));
        $commercialId = $this->positiveInt($this->input('commercial_id'));

        if (Auth::isCommercial()) {
            $storeId = null;
            $commercialId = Auth::id();
        } elseif (Auth::isManager()) {
            $storeId = Auth::storeId();
            $allowedIds = $storeId === null ? [] : array_map(fn($c) => (int) $c['id'], Commercial::forStore($storeId));
            if ($commercialId !== null && !in_array($commercialId, $allowedIds, true)) {
                $commercialId = null;
            }
        } else {
            $storeIds = array_map(fn($s) => (int) $s['id'], Store::all('name'));
            if ($storeId !== null && !in_array($storeId, $storeIds, true)) {
                $storeId = null;
            }
            if ($commercialId !== null) {
                $commercial = Commercial::find($commercialId);
                if (!$commercial || ($storeId !== null && (int) $commercial['store_id'] !== $storeId)) {
                    $commercialId = null;
                }
            }
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => $status,
            'type' => $type,
            'store_id' => $storeId,
            'commercial_id' => $commercialId,
        ];
    }

    private function validDate(mixed $date): ?string
    {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $parts = explode('-', $date);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]) ? $date : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $int === false ? null : (int) $int;
    }

    private function filterAttendants(?int $storeId): array
    {
        if (Auth::isCommercial()) {
            return [];
        }

        if (Auth::isManager()) {
            return Auth::storeId() === null ? [] : Commercial::forStore(Auth::storeId());
        }

        return Commercial::forStore($storeId);
    }

    public function create(array $params = []): void
    {
        $this->render('appointments/create.php', $this->createFormData());
    }

    /** View data shared by the create form and its re-render on validation error. */
    private function createFormData(array $extra = []): array
    {
        $stores = $this->resolveStores();
        // La duración por defecto es la del servicio elegido, no una constante:
        // sin servicio, `durationFor(null)` cae al servicio genérico documentado.
        // En el re-render tras un error hay `service_id` en el POST, así que la
        // duración ofrecida sigue siendo la que corresponde a lo que se envió.
        $serviceId = $this->input('service_id') ? (int) $this->input('service_id') : null;

        return array_merge([
            'stores'           => $stores,
            'services'         => Service::all(),
            'defaultDuration'  => Service::durationFor($serviceId),
            'genericDuration'  => Service::durationFor(null),
            'storeCommercials' => $this->buildStoreCommercials($stores),
            'clients'          => Client::all(),
        ], $extra);
    }

    /**
     * Free times for a store/commercial/date, for the "Nueva cita" time picker.
     *
     * The form must offer exactly the slots the booking validation accepts, so
     * this is the same AvailabilityService grid the voice agent sees (store and
     * commercial schedules, holidays, overrides, bookings and blocks), not a
     * naive list of round hours.
     *
     * Responds { "date": "YYYY-MM-DD", "slots": ["09:30", ...] }.
     */
    public function slots(array $params = []): void
    {
        $storeId      = $this->positiveInt($this->input('store_id'));
        $commercialId = $this->positiveInt($this->input('commercial_id'));
        $date         = $this->validDate($this->input('date'));

        if ($storeId === null || $commercialId === null || $date === null) {
            $this->json(['error' => 'store_id, commercial_id y date son obligatorios'], 422);
            return;
        }

        // Same permission model as creating the appointment: never leak the
        // agenda of a store or a person the user may not book for.
        if (!in_array($storeId, $this->allowedStoreIds(), true)
            || !in_array($commercialId, $this->allowedCommercialIds(), true)) {
            $this->json(['error' => 'No autorizado'], 403);
            return;
        }

        $availability = AvailabilityService::getSlots(
            $storeId,
            $date,
            $commercialId,
            null,
            null,
            $this->requestedDuration()
        );

        $this->json([
            'date'  => $date,
            'slots' => array_map(fn($slot) => $slot['time'], $availability['slots']),
        ]);
    }

    public function store(array $params = []): void
    {
        $errors = $this->validateFields(['store_id', 'commercial_id', 'client_id', 'date', 'time']);
        if ($errors) {
            $this->render('appointments/create.php', $this->createFormData(['errors' => $errors]));
            return;
        }

        // Enforce who the appointment may be created for, independent of the
        // form: commercials only for themselves, managers for the commercials
        // of their store (and themselves), admins for anyone. The dropdown is
        // already filtered, so this guards against tampered POSTs.
        $storeId      = (int) $this->input('store_id');
        $commercialId = (int) $this->input('commercial_id');
        if (!in_array($storeId, $this->allowedStoreIds(), true)
            || !in_array($commercialId, $this->allowedCommercialIds(), true)) {
            $this->forbidden();
            return;
        }

        $date = $this->validDate($this->input('date'));
        $time = $this->validTime($this->input('time'));
        if ($date === null || $time === null) {
            $this->render('appointments/create.php', $this->createFormData([
                'errors' => ['starts_at' => 'Fecha u hora no válidas.'],
            ]));
            return;
        }

        $serviceId = $this->input('service_id') ? (int) $this->input('service_id') : null;
        $duration  = $this->requestedDuration() ?? Service::durationFor($serviceId);
        $startsAt  = $date . ' ' . $time . ':00';

        // The time must still be a real free slot for that person. This closes
        // the gap between listing availability and submitting the form, and it
        // is the only guard against a tampered POST with an arbitrary time.
        $id = AppointmentBookingService::book(
            $storeId,
            (int) $this->input('client_id'),
            $startsAt,
            $serviceId,
            $commercialId,
            $duration,
            Auth::id()
        );
        if ($id === null) {
            $this->render('appointments/create.php', $this->createFormData([
                'errors' => ['starts_at' => 'Esa hora no está disponible para el comercial elegido. Elige una de las horas ofrecidas.'],
            ]));
            return;
        }

        $this->redirect('/appointments/' . $id);
    }

    /** Stores the current user may book for, as ids. */
    private function allowedStoreIds(): array
    {
        return array_map(fn($s) => (int) $s['id'], $this->resolveStores());
    }

    /** "HH:MM" if the value is a valid time of day, otherwise null. */
    private function validTime(mixed $time): ?string
    {
        if (!is_string($time) || !preg_match('/^(\d{2}):(\d{2})$/', $time, $m)) {
            return null;
        }
        return ((int) $m[1] <= 23 && (int) $m[2] <= 59) ? $m[1] . ':' . $m[2] : null;
    }

    /** Custom duration from the form, capped to a sane range; null if absent. */
    private function requestedDuration(): ?int
    {
        $duration = $this->positiveInt($this->input('duration_minutes'));
        return ($duration === null || $duration > 480) ? null : $duration;
    }

    public function show(array $params = []): void
    {
        $appointment = Appointment::withDetails((int) $params['id']);
        if (!$appointment) { $this->notFound(); return; }

        if (!$this->canAccess($appointment)) {
            $this->forbidden(); return;
        }

        $this->render('appointments/show.php', $this->showData($appointment));
    }

    /** Detail view payload. */
    private function showData(array $appointment, array $extra = []): array
    {
        return ['appointment' => $appointment] + $extra;
    }

    /** Re-render the detail page with fresh data after an action failed. */
    private function renderShow(int $id, array $extra = [], ?array $fallback = null): void
    {
        $appointment = Appointment::withDetails($id) ?? $fallback;
        if (!$appointment) { $this->notFound(); return; }
        $this->render('appointments/show.php', $this->showData($appointment, $extra));
    }

    public function confirm(array $params = []): void
    {
        $id = (int) $params['id'];
        $appointment = Appointment::find($id);
        if (!$appointment) { $this->notFound(); return; }

        if (!$this->canAccess($appointment)) {
            $this->forbidden(); return;
        }

        Appointment::update($id, ['status' => 'confirmed']);
        $hasConflict = ConflictService::checkAndMark($id);


        $this->redirect('/appointments/' . $id);
    }

    public function cancel(array $params = []): void
    {
        $id = (int) $params['id'];
        $appointment = Appointment::find($id);
        if (!$appointment) { $this->notFound(); return; }

        if (!$this->canAccess($appointment)) {
            $this->forbidden(); return;
        }

        $reason = trim($this->input('reason', ''));
        if (empty($reason)) {
            $this->renderShow($id, ['cancelError' => 'El motivo de cancelación es obligatorio.']);
            return;
        }

        Appointment::update($id, [
            'status'              => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at'        => date('Y-m-d H:i:s'),
        ]);


        $this->redirect('/appointments/' . $id);
    }

    /**
     * Admins see every appointment, managers those of their store and
     * commercials only their own.
     */
    private function canAccess(array $appointment): bool
    {
        if (Auth::isCommercial()) {
            return (int) $appointment['commercial_id'] === Auth::id();
        }
        if (Auth::isManager()) {
            return (int) $appointment['store_id'] === Auth::storeId();
        }
        return Auth::isAdmin();
    }

    /**
     * Map of store_id => commercials the current user may book for in that store.
     * Built from the role-scoped allow-list so the dropdown only ever offers
     * permitted people.
     */
    private function buildStoreCommercials(array $stores): array
    {
        $byStore = [];
        foreach ($this->allowedCommercials() as $c) {
            $byStore[(int) $c['store_id']][] = $c;
        }

        $result = [];
        foreach ($stores as $store) {
            if ($store) {
                $result[$store['id']] = $byStore[(int) $store['id']] ?? [];
            }
        }
        return $result;
    }

    /**
     * Commercials the current user may create appointments for:
     * - admin: everyone;
     * - manager: the commercials of their store, plus themselves if bookable;
     * - commercial: only themselves.
     */
    private function allowedCommercials(): array
    {
        if (Auth::isAdmin()) {
            return Commercial::forStore(null);
        }

        if (Auth::isManager()) {
            $list = Commercial::forStore(Auth::storeId());
            $self = Commercial::find(Auth::id());
            $ids  = array_map(fn($c) => (int) $c['id'], $list);
            if ($self && !in_array((int) $self['id'], $ids, true)) {
                $list[] = $self;
            }
            return $list;
        }

        // Commercial: only their own record (empty if they have no commercials row).
        $self = Commercial::find(Auth::id());
        return $self ? [$self] : [];
    }

    /** Ids of the commercials the current user may book for. */
    private function allowedCommercialIds(): array
    {
        return array_map(fn($c) => (int) $c['id'], $this->allowedCommercials());
    }

    /**
     * Stores selectable by the current user: all stores for admins, the assigned
     * store for managers, otherwise the single store the logged-in commercial
     * belongs to (from the commercials table).
     */
    private function resolveStores(): array
    {
        if (Auth::isAdmin()) {
            return Store::all('name');
        }

        if (Auth::isManager()) {
            return Auth::storeId() !== null
                ? array_filter([Store::find(Auth::storeId())])
                : [];
        }

        $commercial = Commercial::find(Auth::id());
        if (!$commercial || $commercial['store_id'] === null) {
            return [];
        }

        return array_filter([Store::find((int) $commercial['store_id'])]);
    }
}
