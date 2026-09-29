<?php

namespace App\Middlewares;

use App\Env;
use App\Models\ApiKey;

/**
 * Autenticacion de la API interna (/mcp/*) que consume el bot de voz.
 *
 * El secreto es INTERNAL_API_TOKEN (raiz .env), en `Authorization: Bearer` o
 * `X-API-Key`. Ademas se aceptan claves activas de tipo `mcp` en `api_keys`
 * (mismo patron que la agenda de referencia) por si se quieren emitir claves
 * adicionales sin tocar el entorno.
 */
class InternalApiMiddleware implements Middleware
{
    public function handle(): bool
    {
        $key = self::extractKey();
        if ($key !== null && self::isValid($key)) {
            return true;
        }

        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'error'   => 'UNAUTHORIZED',
            'message' => 'A valid internal API token is required.',
        ]);
        return false;
    }

    public static function isValid(string $key): bool
    {
        $expected = Env::get('INTERNAL_API_TOKEN');
        if ($expected !== null && hash_equals($expected, $key)) {
            return true;
        }
        $apiKey = ApiKey::findByKey($key);
        return $apiKey !== null && $apiKey['type'] === 'mcp';
    }

    public static function extractKey(): ?string
    {
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return (string) $_SERVER['HTTP_X_API_KEY'];
        }
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }
}
