<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

require_once __DIR__ . '/../support/SeedHelpers.php';

/** Ajustes globales que lee la aplicacion (tabla `settings`). */
final class SettingsSeeder extends AbstractSeed
{
    use SeedHelpers;

    public function run(): void
    {
        $this->upsert('settings', [
            ['key' => 'slot_interval_minutes', 'value' => '30'],
            ['key' => 'default_appointment_duration', 'value' => '30'],
        ], ['value']);
    }
}
