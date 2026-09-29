<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

require_once __DIR__ . '/../support/SeedHelpers.php';

/** Clientes inventados. Telefonos ficticios (rango 600 000 xxx). */
final class ClientsSeeder extends AbstractSeed
{
    use SeedHelpers;

    public function run(): void
    {
        $this->upsert('clients', [
            ['id' => 1, 'client_name' => 'Ana Martínez',   'client_phone' => '+34600000001', 'client_email' => 'ana.martinez@example.com', 'client_type' => 'particular'],
            ['id' => 2, 'client_name' => 'Jorge Molina',   'client_phone' => '+34600000002', 'client_email' => '',                          'client_type' => 'particular'],
            ['id' => 3, 'client_name' => 'Reformas Levante S.L.', 'client_phone' => '+34600000003', 'client_email' => 'info@reformaslevante.example.com', 'client_type' => 'empresa'],
        ], ['client_name', 'client_phone', 'client_email', 'client_type']);
        $this->resetAutoIncrement('clients', 100);
    }
}
