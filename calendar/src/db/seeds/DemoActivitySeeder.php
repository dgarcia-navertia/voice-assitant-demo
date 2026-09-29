<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

require_once __DIR__ . '/../support/SeedHelpers.php';

/**
 * Actividad de ejemplo: citas en los proximos dias, un lead y una llamada con
 * transcripcion, para que todas las pantallas tengan contenido en la demo.
 * Las fechas son relativas a hoy; re-sembrar no duplica (ids fijos).
 */
final class DemoActivitySeeder extends AbstractSeed
{
    use SeedHelpers;

    public function getDependencies(): array
    {
        return ['StaffSeeder', 'ClientsSeeder'];
    }

    public function run(): void
    {
        $day = function (int $offset): string {
            $d = new DateTimeImmutable("+{$offset} weekday", new DateTimeZone('Europe/Madrid'));
            return $d->format('Y-m-d');
        };

        $this->upsert('appointments', [
            ['id' => 1, 'store_id' => 1, 'commercial_id' => 3, 'client_id' => 1, 'service_id' => 1,
             'starts_at' => $day(1) . ' 10:00:00', 'duration_minutes' => 30, 'status' => 'confirmed'],
            ['id' => 2, 'store_id' => 2, 'commercial_id' => 4, 'client_id' => 2, 'service_id' => 1,
             'starts_at' => $day(1) . ' 17:00:00', 'duration_minutes' => 30, 'status' => 'pending'],
            ['id' => 3, 'store_id' => 1, 'commercial_id' => 3, 'client_id' => 3, 'service_id' => 1,
             'starts_at' => $day(2) . ' 11:30:00', 'duration_minutes' => 30, 'status' => 'pending'],
        ], ['store_id', 'commercial_id', 'client_id', 'service_id', 'starts_at', 'duration_minutes', 'status']);
        $this->resetAutoIncrement('appointments', 100);

        $sid = 'CAdemo00000000000000000000000000001';
        $this->upsert('calls', [[
            'call_sid' => $sid, 'direction' => 'outbound', 'to_number' => '+34600000001',
            'from_number' => null, 'status' => 'completed', 'duration_seconds' => 74,
        ]], ['status', 'duration_seconds', 'to_number']);

        $turns = [
            ['assistant', 'Soy el asistente virtual de Navertia. ¿En qué te puedo ayudar?'],
            ['user', 'Hola, quería reservar una cita en la tienda de Ruzafa.'],
            ['assistant', 'Perfecto. ¿Para qué día te vendría bien?'],
            ['user', 'Mañana por la mañana, si puede ser.'],
            ['assistant', 'Tengo hueco mañana a las diez. ¿Te lo reservo a nombre de Ana Martínez?'],
            ['user', 'Sí, perfecto.'],
            ['assistant', 'Hecho. Tu cita queda confirmada para mañana a las diez en Navertia Ruzafa. ¡Hasta pronto!'],
        ];
        $pdo = $this->getAdapter()->getConnection();
        $pdo->prepare('DELETE FROM transcripts WHERE sid = ?')->execute([$sid]);
        $insert = $pdo->prepare('INSERT INTO transcripts (sid, role, transcript_text, turn_index, interrupted) VALUES (?, ?, ?, ?, 0)');
        foreach ($turns as $i => [$role, $text]) {
            $insert->execute([$sid, $role, $text, $i]);
        }

        $pdo->prepare('DELETE FROM leads WHERE id = 1')->execute();
        $pdo->prepare(
            "INSERT INTO leads (id, call_sid, name, phone, store_id, reason, source, transferred)
             VALUES (1, NULL, 'Jorge Molina', '+34600000002', 2, 'Quiere presupuesto para reforma integral', 'handoff', 1)"
        )->execute();
    }
}
