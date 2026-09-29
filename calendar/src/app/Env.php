<?php

namespace App;

/**
 * Lectura de variables de entorno. En Docker llegan por `env_file` (la raiz
 * .env) como variables de proceso; no se lee ningun fichero .env dentro de la
 * app. Primero $_ENV (si variables_order lo incluye) y despues getenv().
 */
final class Env
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string) $value;
    }
}
