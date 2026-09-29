<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

require_once __DIR__ . '/../support/SeedHelpers.php';

/**
 * Comerciales de demo aptos para PRODUCCION (`make prod-seed`): las mismas
 * personas que StaffSeeder, pero sin admin ni manager y sin contrasena conocida.
 *
 * - Se identifican por email, no por id: en produccion los ids 1 y 2 ya son los
 *   admins reales (creados con `make prod-admin`) y StaffSeeder los pisaria.
 * - La contrasena es aleatoria y no se guarda en ningun sitio: la cuenta existe
 *   para poder asignar citas, no para entrar. Un admin puede ponerle una desde
 *   el panel (Usuarios). Re-sembrar nunca la cambia.
 *
 * En desarrollo corre despues de StaffSeeder (dependencia) y encuentra las
 * mismas filas por email: no cambia nada. Con `seed:run -s` Phinx no ejecuta
 * dependencias, asi que en produccion StaffSeeder nunca se lanza.
 */
final class ProdCommercialsSeeder extends AbstractSeed
{
    use SeedHelpers;

    public function getDependencies(): array
    {
        return ['StoresSeeder', 'StaffSeeder'];
    }

    public function run(): void
    {
        $weekday = '09:30-14:00 y 16:00-20:00';
        // nombre, email, tienda donde trabaja, telefono, horario del viernes, sabado
        $people = [
            ['Carlos Ruiz',  'carlos.ruiz@navertia.demo',  1, '+34960000202', $weekday,      '10:00-14:00'],
            ['Elena Torres', 'elena.torres@navertia.demo', 2, '+34960000203', $weekday,      '10:00-14:00'],
            ['Pablo Ferrer', 'pablo.ferrer@navertia.demo', 3, '+34960000204', '09:30-14:00', null],
            ['Lucia Gil',    'lucia.gil@navertia.demo',    2, '+34960000205', $weekday,      '10:00-14:00'],
        ];

        $pdo = $this->getAdapter()->getConnection();
        // `password` no esta en el UPDATE: re-sembrar no toca la de una cuenta existente.
        $upsertUser = $pdo->prepare(
            "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'commercial')
             ON DUPLICATE KEY UPDATE name = VALUES(name)"
        );
        $findId = $pdo->prepare('SELECT id FROM users WHERE email = ?');

        foreach ($people as [$name, $email, $storeId, $phone, $friday, $saturday]) {
            $upsertUser->execute([$name, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT)]);
            $findId->execute([$email]);
            $id = (int) $findId->fetchColumn();

            $this->upsert('commercials', [[
                'id' => $id, 'phone' => $phone, 'store_id' => $storeId, 'specialties' => null,
                'rotation_length' => 1, 'rotation_anchor' => null, 'active' => 1, 'voice_transfer_enabled' => 0,
            ]], ['phone', 'store_id', 'rotation_length', 'active']);

            $this->upsert('commercial_week_patterns', [[
                'commercial_id' => $id, 'week_index' => 0,
                'monday' => $weekday, 'tuesday' => $weekday, 'wednesday' => $weekday, 'thursday' => $weekday,
                'friday' => $friday, 'saturday' => $saturday, 'sunday' => null,
            ]], ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
        }
    }
}
