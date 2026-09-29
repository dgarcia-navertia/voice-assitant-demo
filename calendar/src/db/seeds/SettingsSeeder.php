<?php

declare(strict_types=1);

use App\Env;
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

        // Numero de traspaso inicial desde HANDOFF_PHONE_NUMBER. INSERT IGNORE:
        // re-sembrar nunca pisa el valor que un admin haya editado en la web.
        require_once __DIR__ . '/../../app/Env.php';
        $handoff = Env::get('HANDOFF_PHONE_NUMBER');
        if ($handoff !== null) {
            $this->getAdapter()->getConnection()
                ->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('handoff_phone_number', ?)")
                ->execute([$handoff]);
        }
    }
}
