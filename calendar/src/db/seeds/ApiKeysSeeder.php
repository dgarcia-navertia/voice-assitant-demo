<?php

declare(strict_types=1);

use App\Env;
use Phinx\Seed\AbstractSeed;

/**
 * Registra INTERNAL_API_TOKEN en `api_keys` (tipo mcp), mismo patron que la
 * agenda de referencia. El middleware tambien acepta el token del entorno
 * directamente, asi que esto es opcional pero deja la clave auditable.
 */
final class ApiKeysSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../app/Env.php';
        $token = Env::get('INTERNAL_API_TOKEN');
        if ($token === null || strlen($token) < 16) {
            echo "INTERNAL_API_TOKEN vacio o corto: no se siembra ninguna api_key.\n";
            return;
        }
        $pdo = $this->getAdapter()->getConnection();
        $pdo->prepare(
            "INSERT INTO api_keys (name, `key`, type, active) VALUES ('bot de voz', ?, 'mcp', 1)
             ON DUPLICATE KEY UPDATE active = 1"
        )->execute([$token]);
    }
}
