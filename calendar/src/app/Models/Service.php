<?php

namespace App\Models;

/**
 * Tipos de cita (tabla `services`). En la demo no hay pantalla de servicios:
 * se siembra un unico tipo "Cita comercial" y la duracion sale de aqui.
 */
class Service extends Model
{
    protected static string $table = 'services';

    /** Id of the default appointment type (lowest id), or null if none seeded. */
    public static function defaultId(): ?int
    {
        $id = static::db()->query('SELECT MIN(id) FROM services')->fetchColumn();
        return $id === false || $id === null ? null : (int) $id;
    }

    public static function durationFor(?int $serviceId): int
    {
        $id = $serviceId ?? static::defaultId();
        $service = $id !== null ? static::find($id) : null;
        if ($service && isset($service['appointment_time'])) {
            return (int) $service['appointment_time'];
        }

        return (int) Setting::get('default_appointment_duration', 30);
    }
}
