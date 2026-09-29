<?php

declare(strict_types=1);

use App\Env;
use Phinx\Seed\AbstractSeed;

require_once __DIR__ . '/../support/SeedHelpers.php';

/**
 * Usuarios de desarrollo: 1 admin, 1 manager y 4 comerciales (2 de ellos son
 * los "staff" que se usan en las pruebas). Contrasena comun: SEED_USER_PASSWORD
 * (docs/CREDENTIALS.md). SOLO DESARROLLO: en produccion cambiala o no siembres.
 *
 * `password` no se refresca al re-sembrar: no pisa la de quien ya la cambio.
 */
final class StaffSeeder extends AbstractSeed
{
    use SeedHelpers;

    private const DEFAULT_PASSWORD = 'Navertia-Dev-2026!';

    public function getDependencies(): array
    {
        return ['StoresSeeder', 'ServicesSeeder'];
    }

    public function run(): void
    {
        require_once __DIR__ . '/../../app/Env.php';
        $hash = password_hash(Env::get('SEED_USER_PASSWORD', self::DEFAULT_PASSWORD), PASSWORD_BCRYPT);

        $people = [
            // id, nombre, email, rol, tienda de acceso (manager), tienda donde trabaja, telefono
            [1, 'Admin Navertia', 'admin@navertia.demo',  'admin',      null, null, null],
            [2, 'Marta Sanz',     'marta.sanz@navertia.demo',     'manager',    1, 1, '+34960000201'],
            [3, 'Carlos Ruiz',    'carlos.ruiz@navertia.demo',    'commercial', null, 1, '+34960000202'],
            [4, 'Elena Torres',   'elena.torres@navertia.demo',   'commercial', null, 2, '+34960000203'],
            [5, 'Pablo Ferrer',   'pablo.ferrer@navertia.demo',   'commercial', null, 3, '+34960000204'],
            [6, 'Lucia Gil',      'lucia.gil@navertia.demo',      'commercial', null, 2, '+34960000205'],
        ];

        $this->upsert('users', array_map(fn (array $p) => [
            'id' => $p[0], 'name' => $p[1], 'email' => $p[2], 'password' => $hash,
            'role' => $p[3], 'managed_store_id' => $p[4],
        ], $people), ['name', 'email', 'role', 'managed_store_id']);

        $bookable = array_values(array_filter($people, fn (array $p) => $p[5] !== null));
        $this->upsert('commercials', array_map(fn (array $p) => [
            'id' => $p[0], 'phone' => $p[6], 'store_id' => $p[5], 'specialties' => null,
            'rotation_length' => 1, 'rotation_anchor' => null, 'active' => 1, 'voice_transfer_enabled' => 0,
        ], $bookable), ['phone', 'store_id', 'rotation_length', 'active']);

        $weekday = '09:30-14:00 y 16:00-20:00';
        $this->upsert('commercial_week_patterns', array_map(fn (array $p) => [
            'commercial_id' => $p[0], 'week_index' => 0,
            'monday' => $weekday, 'tuesday' => $weekday, 'wednesday' => $weekday,
            'thursday' => $weekday, 'friday' => $p[0] === 5 ? '09:30-14:00' : $weekday,
            'saturday' => $p[5] === 3 ? null : '10:00-14:00', 'sunday' => null,
        ], $bookable), ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);

        $this->resetAutoIncrement('users', 100);
    }
}
