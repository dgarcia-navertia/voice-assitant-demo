<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

require_once __DIR__ . '/../support/SeedHelpers.php';

/** Un unico tipo de cita: la demo no expone catalogo de servicios. */
final class ServicesSeeder extends AbstractSeed
{
    use SeedHelpers;

    public function run(): void
    {
        $this->upsert('services', [
            ['id' => 1, 'name' => 'Cita comercial', 'appointment_time' => 30, 'public' => 1],
        ], ['name', 'appointment_time', 'public']);
    }
}
