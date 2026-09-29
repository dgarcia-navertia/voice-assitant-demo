<?php

namespace App\Controllers;

use App\Auth;
use App\Models\User;
use App\Models\Commercial;
use App\Models\Store;
use App\Models\Service;
use App\Models\WeekPattern;
use App\Services\ScheduleChangeGuard;
use App\Services\ScheduleFormInput;
use App\Services\ScheduleRotation;

class CommercialController extends Controller
{
    public function index(array $params = []): void
    {
        $commercials = Commercial::withStore(Auth::isManager() ? Auth::storeId() : null);
        $this->render('commercials/index.php', compact('commercials'));
    }

    public function create(array $params = []): void
    {
        $stores = $this->selectableStores();
        $this->render('commercials/form.php', [
            'commercial'     => null,
            'stores'         => $stores,
            'storeSchedules' => Store::schedulesByIds(array_column($stores, 'id')),
            'services'       => Service::all(),
            'action'         => '/commercials',
            'method'         => 'POST',
        ]);
    }

    public function store(array $params = []): void
    {
        $errors = $this->validateFields(['name', 'email', 'password', 'store_id']);
        if (!$errors && !$this->canManageStore((int) $this->input('store_id'))) {
            $this->forbidden();
            return;
        }

        $rotationLength = ScheduleFormInput::parseRotationLength($this->input('rotation_length', '1'));
        $anchorRaw      = trim((string) $this->input('rotation_anchor', ''));
        $weeksInput     = $this->input('weeks', []);
        $weeks          = ScheduleFormInput::parseWeeks(is_array($weeksInput) ? $weeksInput : [], $rotationLength);

        if (!$errors) {
            $scheduleError = ScheduleFormInput::validate($rotationLength, $anchorRaw);
            if ($scheduleError) {
                $errors['schedule'] = $scheduleError;
            }
        }

        if ($errors) {
            $stores = $this->selectableStores();
            $this->render('commercials/form.php', [
                'commercial'     => null,
                'weeks'          => $weeks,
                'errors'         => $errors,
                'stores'         => $stores,
                'storeSchedules' => Store::schedulesByIds(array_column($stores, 'id')),
                'services'       => Service::all(),
                'action'         => '/commercials',
                'method'         => 'POST',
            ]);
            return;
        }

        $anchor = $rotationLength > 1 ? ScheduleRotation::normalizeAnchor($anchorRaw) : null;

        // A bookable person is a users row (login + name) + a commercials row
        // (business data), same id, whatever the role. The store field on this
        // form means "works at" and lands in commercials.store_id; for a
        // manager it doubles as their access scope, so it is copied to
        // users.managed_store_id too.
        $role = $this->roleInput();
        $id   = User::create([
            'name'             => $this->input('name'),
            'email'            => $this->input('email'),
            'password'         => User::hashPassword($this->input('password')),
            'role'             => $role,
            'managed_store_id' => $role === 'manager' ? (int) $this->input('store_id') : null,
        ]);

        Commercial::create([
            'id'                     => $id,
            'phone'                  => $this->input('phone'),
            'store_id'               => (int) $this->input('store_id'),
            'specialties'            => $this->specialtiesInput(),
            'rotation_length'        => $rotationLength,
            'rotation_anchor'        => $anchor,
            'active'                 => $this->input('active') ? 1 : 0,
            'voice_transfer_enabled' => $this->input('voice_transfer_enabled') ? 1 : 0,
        ]);
        // No appointments can exist yet for a brand-new commercial id, so
        // ScheduleChangeGuard can never find a conflict here — every OTHER
        // write path to patterns/rotation still goes through it (see update()).
        WeekPattern::replaceForCommercial($id, $weeks);

        $this->redirect('/commercials');
    }

    public function edit(array $params = []): void
    {
        $id   = (int) $params['id'];
        $user = User::find($id);
        if (!$user || !$this->isBookableRole($user)) { $this->notFound(); return; }
        if ($user['role'] !== 'commercial' && !Auth::isAdmin()) { $this->forbidden(); return; }

        // Merge login data (email) with business data (phone, store, rotation).
        $commercial = array_merge($user, Commercial::find($id) ?? []);

        if (!$this->canManageStore((int) ($commercial['store_id'] ?? 0))) {
            $this->forbidden();
            return;
        }

        $stores  = $this->selectableStores();
        // All rotation weeks, indexed by week_index — shape expected by the form.
        $weeks   = WeekPattern::forCommercial($id);

        $this->render('commercials/form.php', [
            'commercial'     => $commercial,
            'weeks'          => $weeks,
            'stores'         => $stores,
            'storeSchedules' => Store::schedulesByIds(array_column($stores, 'id')),
            'services'       => Service::all(),
            'action'         => '/commercials/' . $id,
            'method'         => 'PUT',
        ]);
    }

    public function update(array $params = []): void
    {
        $id   = (int) $params['id'];
        $user = User::find($id);
        if (!$user || !$this->isBookableRole($user)) { $this->notFound(); return; }
        if ($user['role'] !== 'commercial' && !Auth::isAdmin()) { $this->forbidden(); return; }

        $current = Commercial::find($id);
        if (!$this->canManageStore((int) ($current['store_id'] ?? 0))
            || !$this->canManageStore((int) $this->input('store_id'))) {
            $this->forbidden();
            return;
        }

        $rotationLength = ScheduleFormInput::parseRotationLength($this->input('rotation_length', '1'));
        $anchorRaw      = trim((string) $this->input('rotation_anchor', ''));
        $weeksInput     = $this->input('weeks', []);
        $weeks          = ScheduleFormInput::parseWeeks(is_array($weeksInput) ? $weeksInput : [], $rotationLength);

        $scheduleError = ScheduleFormInput::validate($rotationLength, $anchorRaw);
        // Only normalize once validation passed: normalizeAnchor() throws on garbage input.
        $anchor        = ($scheduleError === null && $rotationLength > 1)
            ? ScheduleRotation::normalizeAnchor($anchorRaw)
            : null;

        // Block the save if the REAL proposed state (all weeks + length + anchor)
        // would strand a future, non-cancelled appointment outside the new schedule.
        if (!$scheduleError && $current) {
            $conflicts = ScheduleChangeGuard::checkRotationChange($id, $weeks, $rotationLength, $anchor);
            if ($conflicts) {
                $scheduleError = ScheduleChangeGuard::conflictMessage($conflicts);
            }
        }

        if ($scheduleError) {
            $stores = $this->selectableStores();
            $this->render('commercials/form.php', [
                'commercial'     => array_merge($user, $current ?? []),
                'weeks'          => $weeks,
                'errors'         => ['schedule' => $scheduleError],
                'stores'         => $stores,
                'storeSchedules' => Store::schedulesByIds(array_column($stores, 'id')),
                'services'       => Service::all(),
                'action'         => '/commercials/' . $id,
                'method'         => 'PUT',
            ]);
            return;
        }

        $role     = $this->roleInput($user);
        $userData = [
            'name'             => $this->input('name'),
            'email'            => $this->input('email'),
            'role'             => $role,
            'managed_store_id' => $role === 'manager' ? (int) $this->input('store_id') : null,
        ];
        if ($this->input('password')) {
            $userData['password'] = User::hashPassword($this->input('password'));
        }
        User::update($id, $userData);

        $businessData = [
            'phone'                  => $this->input('phone'),
            'store_id'               => (int) $this->input('store_id'),
            'specialties'            => $this->specialtiesInput(),
            'rotation_length'        => $rotationLength,
            'rotation_anchor'        => $anchor,
            'active'                 => $this->input('active') ? 1 : 0,
            'voice_transfer_enabled' => $this->input('voice_transfer_enabled') ? 1 : 0,
        ];

        // Managers created outside this window may lack a commercials row;
        // saving them here upgrades them to bookable.
        if ($current) {
            Commercial::update($id, $businessData);
        } else {
            Commercial::create(array_merge(['id' => $id], $businessData));
        }
        WeekPattern::replaceForCommercial($id, $weeks);

        $this->redirect('/commercials');
    }

    public function destroy(array $params = []): void
    {
        $user = User::find((int) $params['id']);
        if (($user['role'] ?? 'commercial') !== 'commercial' && !Auth::isAdmin()) {
            $this->forbidden();
            return;
        }

        $commercial = Commercial::find((int) $params['id']);
        if (!$this->canManageStore((int) ($commercial['store_id'] ?? 0))) {
            $this->forbidden();
            return;
        }

        // Deleting the users row cascades to commercials and api_keys.
        User::delete((int) $params['id']);
        $this->redirect('/commercials');
    }

    /**
     * Especialidades marcadas en el formulario, como JSON para `commercials.specialties`.
     *
     * Los ids se cotejan contra el catálogo: un POST manipulado no puede dejar
     * colgado un id que `JSON_CONTAINS` no encontraría nunca. "Ninguna marcada"
     * guarda NULL, que es el marcador documentado de "sin especialidades" — esas
     * personas quedan fuera de las búsquedas por servicio y solo reciben citas
     * genéricas (ver `Commercial::forStore`). Es decir: NULL y "todas" no son lo
     * mismo, y desmarcar todo es una decisión, no un formulario a medio rellenar.
     */
    private function specialtiesInput(): ?string
    {
        $submitted = $this->input('specialties', []);
        $submitted = is_array($submitted) ? $submitted : [$submitted];

        $known = array_map('intval', array_column(Service::all(), 'id'));
        $valid = array_values(array_intersect($known, array_map('intval', $submitted)));

        return $valid ? json_encode($valid) : null;
    }

    /** Roles managed from this window — any role can attend appointments. */
    private function isBookableRole(array $user): bool
    {
        return in_array($user['role'], ['commercial', 'manager', 'admin'], true);
    }

    /**
     * Resolves the role field: only admins may assign it (managers always
     * create commercials and cannot change roles).
     */
    private function roleInput(?array $existingUser = null): string
    {
        if (!Auth::isAdmin()) {
            return $existingUser['role'] ?? 'commercial';
        }
        $role = $this->input('role', $existingUser['role'] ?? 'commercial');
        return in_array($role, ['commercial', 'manager', 'admin'], true) ? $role : 'commercial';
    }

    /**
     * Managers only operate on commercials of their assigned store; admins on any.
     */
    private function canManageStore(int $storeId): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }
        return Auth::isManager() && Auth::storeId() !== null && $storeId === Auth::storeId();
    }

    /**
     * Stores offered in the form: all for admins, only the assigned one for managers.
     */
    private function selectableStores(): array
    {
        if (Auth::isManager()) {
            return Auth::storeId() !== null
                ? array_filter([Store::find(Auth::storeId())])
                : [];
        }
        return Store::all('name');
    }
}
