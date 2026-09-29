<?php

namespace App\Controllers;

use App\Auth;
use App\Models\User;
use App\PasswordPolicy;

/**
 * "Mi cuenta" — datos de la persona conectada. Disponible para los tres roles.
 *
 * Deliberadamente separado de /settings: esa ventana es configuración global
 * del sistema (locuciones del asistente) y sigue siendo solo de admin. Aquí no
 * hay nada global, y por eso el usuario sobre el que se actúa sale SIEMPRE de
 * Auth::id() y nunca del formulario: no existe forma de que un POST manipulado
 * cambie la contraseña de otra persona.
 */
class AccountController extends Controller
{
    public function index(array $params = []): void
    {
        // render() define su propio $user DESPUÉS de capturar la vista (es para
        // el layout), así que la vista no lo ve: hay que pasárselo aquí.
        $this->render('account/index.php', [
            'user'    => Auth::user(),
            'success' => $this->input('success'),
        ]);
    }

    public function updatePassword(array $params = []): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->forbidden();
            return;
        }

        $current      = (string) $this->input('current_password', '');
        $password     = (string) $this->input('password', '');
        $confirmation = (string) $this->input('password_confirmation', '');

        // Exigir la contraseña actual no es cortesía: es lo que hace inútil un
        // CSRF contra esta ruta, porque el atacante no la conoce.
        if (!User::verifyPassword($current, (string) $user['password'])) {
            $this->render('account/index.php', ['user' => $user, 'error' => 'La contraseña actual no es correcta.']);
            return;
        }
        if ($password !== $confirmation) {
            $this->render('account/index.php', ['user' => $user, 'error' => 'Las dos contraseñas nuevas no coinciden.']);
            return;
        }
        if ($error = PasswordPolicy::validate($password)) {
            $this->render('account/index.php', ['user' => $user, 'error' => $error]);
            return;
        }
        if ($password === $current) {
            $this->render('account/index.php', ['user' => $user, 'error' => 'La contraseña nueva debe ser distinta de la actual.']);
            return;
        }

        User::update((int) $user['id'], ['password' => User::hashPassword($password)]);

        // Sesión nueva tras cambiar credenciales: si alguien tenía secuestrada
        // la cookie anterior, deja de servirle.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        Auth::setUser(User::find((int) $user['id']) ?? $user);

        $this->redirect('/account?success=1');
    }
}
