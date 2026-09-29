<?php

/**
 * Crea (o actualiza) un usuario administrador desde la linea de comandos.
 *
 *   ADMIN_EMAIL=ana@empresa.com ADMIN_NAME="Ana Lopez" \
 *     printf '%s\n' "$PASSWORD" | php scripts/create-admin.php
 *
 * La contrasena SOLO se lee de la entrada estandar (nunca de argv, para que no
 * quede en `ps` ni en el historial). Idempotente: si el email ya existe se
 * actualizan nombre, rol=admin y contrasena, sin duplicar. Lo invoca
 * `make prod-admin` / `make admin` (scripts/create-admin.sh).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

use App\Env;
use App\Models\User;
use App\PasswordPolicy;

/** @return array{0:int,1:string} [exit code, message]. Separada para poder testearse. */
function create_admin(string $email, string $name, string $password): array
{
    $email = strtolower(trim($email));
    $name  = trim($name);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [2, 'Email no válido.'];
    }
    if ($name === '') {
        return [2, 'El nombre es obligatorio.'];
    }
    if ($error = PasswordPolicy::validate($password)) {
        return [2, $error];
    }

    $hash     = User::hashPassword($password);
    $existing = User::findByEmail($email);
    if ($existing) {
        User::update((int) $existing['id'], ['name' => $name, 'role' => 'admin', 'password' => $hash, 'managed_store_id' => null]);
        return [0, "Administrador actualizado: {$email}"];
    }
    User::create(['name' => $name, 'email' => $email, 'password' => $hash, 'role' => 'admin']);
    return [0, "Administrador creado: {$email}"];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $password = rtrim((string) fgets(STDIN), "\r\n");
    [$code, $message] = create_admin((string) Env::get('ADMIN_EMAIL', ''), (string) Env::get('ADMIN_NAME', ''), $password);
    fwrite($code === 0 ? STDOUT : STDERR, $message . PHP_EOL);
    exit($code);
}
