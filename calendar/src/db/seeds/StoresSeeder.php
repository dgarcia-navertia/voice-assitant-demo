<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

require_once __DIR__ . '/../support/SeedHelpers.php';

/**
 * Tiendas inventadas de Navertia en Valencia (datos de demo, no reales).
 * Formato de horario: "09:30-14:00 y 16:00-20:00"; NULL = cerrado.
 */
final class StoresSeeder extends AbstractSeed
{
    use SeedHelpers;

    public function run(): void
    {
        $this->upsert('stores', [
            ['id' => 1, 'name' => 'Navertia Ruzafa',   'address' => 'Calle Sueca 18, 46006 Valencia',
             'type' => 'showroom', 'phone_number' => '960000101'],
            ['id' => 2, 'name' => 'Navertia Campanar', 'address' => 'Avenida de Tirso de Molina 25, 46015 Valencia',
             'type' => 'showroom', 'phone_number' => '960000102'],
            ['id' => 3, 'name' => 'Navertia Puerto',   'address' => 'Avenida del Puerto 120, 46023 Valencia',
             'type' => 'oficina',  'phone_number' => '960000103'],
        ], ['name', 'address', 'type', 'phone_number']);

        $this->upsert('store_schedules', [
            ['store_id' => 1, 'mon_to_friday' => '09:30-14:00 y 16:00-20:00', 'saturday' => '10:00-14:00'],
            ['store_id' => 2, 'mon_to_friday' => '09:30-14:00 y 16:00-20:00', 'saturday' => '10:00-14:00'],
            ['store_id' => 3, 'mon_to_friday' => '09:00-15:00',               'saturday' => null],
        ], ['mon_to_friday', 'saturday']);

        $this->resetAutoIncrement('stores', 10);
    }
}
