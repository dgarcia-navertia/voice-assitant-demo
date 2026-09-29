<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Commercial;
use App\Models\Store;
use App\Models\User;
use App\PasswordPolicy;

/**
 * Gestión de logins (tabla `users`), admin-only e independiente de si la
 * persona es reservable — eso sigue viviendo en /commercials (tabla
 * `commercials`). Aquí se ve y edita CUALQUIER usuario, tenga o no fila en
 * `commercials`.
 */
class UserController extends Controller
{
    public function index(array $params = []): void
    {
        $users = User::query(
            'SELECT u.*, s.name AS store_name,
                    EXISTS(SELECT 1 FROM commercials c WHERE c.id = u.id) AS bookable
             FROM users u
             LEFT JOIN stores s ON s.id = u.managed_store_id
             ORDER BY u.name'
        );
        $this->render('users/index.php', compact('users'));
    }

    public function create(array $params = []): void
    {
        $this->render('users/form.php', [
            'targetUser' => null,
            'stores'     => Store::all('name'),
            'action'     => '/users',
            'method'     => 'POST',
        ]);
    }

    public function store(array $params = []): void
    {
        $errors = $this->validateInput(null);
        if ($errors) {
            $this->render('users/form.php', [
                'targetUser' => null,
                'errors'     => $errors,
                'stores'     => Store::all('name'),
                'action'     => '/users',
                'method'     => 'POST',
            ]);
            return;
        }

        $role = $this->roleInput();
        User::create([
            'name'             => $this->input('name'),
            'email'            => $this->input('email'),
            'password'         => User::hashPassword($this->input('password')),
            'role'             => $role,
            'managed_store_id' => $this->managedStoreIdInput($role),
        ]);

        $this->redirect('/users');
    }

    public function edit(array $params = []): void
    {
        $targetUser = User::find((int) $params['id']);
        if (!$targetUser) { $this->notFound(); return; }

        $this->render('users/form.php', [
            'targetUser' => $targetUser,
            'stores'     => Store::all('name'),
            'action'     => '/users/' . $targetUser['id'],
            'method'     => 'PUT',
        ]);
    }

    public function update(array $params = []): void
    {
        $id         = (int) $params['id'];
        $targetUser = User::find($id);
        if (!$targetUser) { $this->notFound(); return; }

        $errors = $this->validateInput($targetUser);
        if ($errors) {
            $this->render('users/form.php', [
                'targetUser' => $targetUser,
                'errors'     => $errors,
                'stores'     => Store::all('name'),
                'action'     => '/users/' . $id,
                'method'     => 'PUT',
            ]);
            return;
        }

        $role = $this->roleInput();
        $data = [
            'name'             => $this->input('name'),
            'email'            => $this->input('email'),
            'role'             => $role,
            'managed_store_id' => $this->managedStoreIdInput($role),
        ];

        // Autoedición: la contraseña propia se cambia siempre desde "Mi cuenta"
        // (exige la actual). Sin ese requisito, aplicar aquí una contraseña
        // para uno mismo sería el mismo hueco de CSRF que /account existe para
        // cerrar — así que se ignora cualquier valor recibido en ese campo.
        $isSelf = $id === Auth::id();
        if (!$isSelf && $this->input('password')) {
            $data['password'] = User::hashPassword($this->input('password'));
        }

        User::update($id, $data);
        $this->syncCommercialStore($id, $data['managed_store_id']);

        if ($isSelf) {
            Auth::setUser(User::find($id) ?? $targetUser);
        }

        $this->redirect('/users');
    }

    /** Store a manager's access is scoped to; meaningless for the other roles. */
    private function managedStoreIdInput(string $role): ?int
    {
        return $role === 'manager' ? (int) $this->input('managed_store_id') : null;
    }

    /**
     * Keep commercials.store_id in step when a bookable manager's store is
     * changed from this window.
     *
     * The two columns mean different things — where you work vs. what your
     * role lets you reach — but for a bookable manager they have to agree.
     * Without this, moving a manager here would scope their access to one
     * store while leaving them listed as working at another: they would
     * administer store B's appointments and still show up in store A's
     * availability.
     *
     * Only managers are synced. A commercial's `managed_store_id` is null and
     * writing that through would wipe the store they actually work at, which
     * belongs to the "Comerciales" window.
     */
    private function syncCommercialStore(int $id, ?int $managedStoreId): void
    {
        if ($managedStoreId === null || Commercial::find($id) === null) {
            return;
        }

        Commercial::update($id, ['store_id' => $managedStoreId]);
    }

    private function validateInput(?array $existingUser): array
    {
        $errors = $this->validateFields(['name', 'email', 'role']);

        $email = trim((string) $this->input('email', ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El email no es válido.';
        } elseif ($email !== '') {
            $existing = User::findByEmail($email);
            if ($existing && (int) $existing['id'] !== (int) ($existingUser['id'] ?? 0)) {
                $errors['email'] = 'Ya existe un usuario con ese email.';
            }
        }

        if ($this->roleInput() === 'manager' && !$this->input('managed_store_id')) {
            $errors['managed_store_id'] = 'Un manager necesita una tienda asignada.';
        }

        $isSelf   = $existingUser && (int) $existingUser['id'] === Auth::id();
        $password = (string) $this->input('password', '');
        if (!$existingUser && $password === '') {
            $errors['password'] = 'La contraseña es obligatoria.';
        } elseif (!$isSelf && $password !== '') {
            if ($error = PasswordPolicy::validate($password)) {
                $errors['password'] = $error;
            }
        }

        return $errors;
    }

    private function roleInput(): string
    {
        $role = $this->input('role', 'commercial');
        return in_array($role, ['commercial', 'manager', 'admin'], true) ? $role : 'commercial';
    }
}
