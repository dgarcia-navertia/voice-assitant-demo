<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Call;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Store;
use App\Models\Transcript;
use App\Services\AppointmentBookingService;
use App\Services\AvailabilityService;
use DateTimeImmutable;

/**
 * API interna para el bot de voz. Rutas /mcp/* protegidas por
 * InternalApiMiddleware. Contrato: openspec/changes/initial-build/specs/calendar/spec.md
 */
class InternalApiController extends Controller
{
    // ---- Tiendas ---------------------------------------------------------

    public function stores(array $params = []): void
    {
        $this->json(['stores' => array_map(
            fn(array $store) => $this->storePayload($store),
            Store::all('name')
        )]);
    }

    public function storeById(array $params = []): void
    {
        $store = Store::find((int) ($params['id'] ?? 0));
        if (!$store) {
            $this->json(['error' => 'Store not found'], 404);
            return;
        }
        $this->json($this->storePayload($store));
    }

    private function storePayload(array $store): array
    {
        $schedule = Store::schedules((int) $store['id']);
        return [
            'id'           => (int) $store['id'],
            'name'         => $store['name'],
            'address'      => $store['address'],
            'type'         => $store['type'],
            'phone_number' => $store['phone_number'],
            'schedule'     => $schedule ? [
                'mon_to_friday' => $schedule['mon_to_friday'],
                'saturday'      => $schedule['saturday'],
            ] : null,
        ];
    }

    // ---- Disponibilidad --------------------------------------------------

    public function availability(array $params = []): void
    {
        $storeId   = (int) ($this->input('store_id') ?? 0);
        $date      = (string) $this->input('date', date('Y-m-d'));
        $serviceId = $this->input('service_id') ? (int) $this->input('service_id') : null;

        if (!$storeId) {
            $this->json(['error' => 'store_id is required'], 422);
            return;
        }
        if (!self::isValidDate($date)) {
            $this->json(['error' => 'date must be YYYY-MM-DD'], 422);
            return;
        }
        if ($serviceId !== null && !Service::find($serviceId)) {
            $this->json(['error' => 'Service not found'], 404);
            return;
        }

        $result = AvailabilityService::getSlots($storeId, $date, null, $serviceId);
        // Nunca ofrecer horas ya pasadas del dia de hoy.
        if ($date === date('Y-m-d')) {
            $now = date('H:i');
            $result['slots'] = array_values(array_filter($result['slots'], fn($s) => $s['time'] > $now));
        }
        $this->json($result);
    }

    // ---- Clientes --------------------------------------------------------

    public function clientByPhone(array $params = []): void
    {
        $phone = trim((string) $this->input('phone', ''));
        if ($phone === '') {
            $this->json(['error' => 'phone is required'], 422);
            return;
        }
        $client = Client::findByPhone($phone);
        if (!$client) {
            $this->json(['error' => 'Client not found'], 404);
            return;
        }
        $this->json(['client' => $this->clientPayload($client)]);
    }

    public function createClient(array $params = []): void
    {
        $body  = $this->requestBody();
        $name  = trim((string) ($body['client_name'] ?? ''));
        $phone = Client::normalizePhone((string) ($body['client_phone'] ?? ''));
        $email = trim((string) ($body['client_email'] ?? ''));
        $type  = trim((string) ($body['client_type'] ?? '')) ?: 'particular';

        if ($name === '') {
            $this->json(['error' => 'client_name is required'], 422);
            return;
        }
        if ($phone === '') {
            $this->json(['error' => 'client_phone is required'], 422);
            return;
        }
        if (!in_array($type, Client::CLIENT_TYPES, true)) {
            $this->json(['error' => 'client_type must be one of: ' . implode(', ', Client::CLIENT_TYPES)], 422);
            return;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'client_email must be a valid email'], 422);
            return;
        }

        $id = Client::createClient($name, $phone, $type, $email === '' ? null : $email);
        $this->json(['client' => $this->clientPayload(Client::find($id))], 201);
    }

    private function clientPayload(array $client): array
    {
        return [
            'id'           => (int) $client['id'],
            'client_name'  => $client['client_name'],
            'client_phone' => $client['client_phone'],
            'client_email' => $client['client_email'] !== '' ? $client['client_email'] : null,
            'client_type'  => $client['client_type'],
        ];
    }

    // ---- Citas -----------------------------------------------------------

    public function createAppointment(array $params = []): void
    {
        $body = $this->requestBody();
        foreach (['store_id', 'client_id', 'starts_at'] as $field) {
            if (empty($body[$field])) {
                $this->json(['error' => "Field {$field} is required"], 422);
                return;
            }
        }

        $startsAt = self::normalizeDateTime((string) $body['starts_at']);
        if ($startsAt === null) {
            $this->json(['error' => 'starts_at must be YYYY-MM-DD HH:MM[:SS]'], 422);
            return;
        }
        if (strtotime($startsAt) < time()) {
            $this->json(['error' => 'starts_at is in the past'], 422);
            return;
        }
        if (!Store::find((int) $body['store_id'])) {
            $this->json(['error' => 'Store not found'], 404);
            return;
        }
        if (!Client::find((int) $body['client_id'])) {
            $this->json(['error' => 'Client not found'], 404);
            return;
        }

        $serviceId    = !empty($body['service_id']) ? (int) $body['service_id'] : null;
        $commercialId = !empty($body['commercial_id']) ? (int) $body['commercial_id'] : null;
        if ($serviceId !== null && !Service::find($serviceId)) {
            $this->json(['error' => 'Service not found'], 404);
            return;
        }

        // La eleccion de comercial se revalida aqui, dentro de la transaccion:
        // cierra la carrera entre "consultar huecos" y "reservar".
        $id = AppointmentBookingService::book(
            (int) $body['store_id'],
            (int) $body['client_id'],
            $startsAt,
            $serviceId,
            $commercialId,
            null,
            null
        );
        if ($id === null) {
            $this->json(['error' => 'No commercial is available for the requested slot'], 409);
            return;
        }
        $this->json(Appointment::withDetails($id), 201);
    }

    // ---- Transcripciones -------------------------------------------------

    public function createTranscript(array $params = []): void
    {
        $body = $this->requestBody();
        $turn = $this->validTurn($body, $error);
        $sid  = trim((string) ($body['sid'] ?? ''));
        if ($sid === '') {
            $this->json(['error' => 'sid is required'], 422);
            return;
        }
        if ($turn === null) {
            $this->json(['error' => $error], 422);
            return;
        }
        $id = Transcript::record($sid, $turn['role'], $turn['text'], $turn['index'], $turn['interrupted']);
        $this->json(['transcript' => Transcript::find($id)], 201);
    }

    public function createTranscriptBatch(array $params = []): void
    {
        $body = $this->requestBody();
        $sid  = trim((string) ($body['sid'] ?? ''));
        $turns = $body['turns'] ?? null;
        if ($sid === '') {
            $this->json(['error' => 'sid is required'], 422);
            return;
        }
        if (!is_array($turns) || !$turns || count($turns) > 2000) {
            $this->json(['error' => 'turns must be a non-empty array (max 2000)'], 422);
            return;
        }

        $valid = [];
        foreach ($turns as $i => $raw) {
            $turn = is_array($raw) ? $this->validTurn($raw, $error) : null;
            if ($turn === null) {
                $this->json(['error' => "turns[{$i}]: " . ($error ?? 'invalid turn')], 422);
                return;
            }
            $valid[] = $turn;
        }

        $db = \App\Database::connection();
        $db->beginTransaction();
        try {
            // Idempotente: reenviar el mismo lote (reintento del bot) reemplaza, no duplica.
            $db->prepare('DELETE FROM transcripts WHERE sid = ?')->execute([$sid]);
            foreach ($valid as $t) {
                Transcript::record($sid, $t['role'], $t['text'], $t['index'], $t['interrupted']);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $this->json(['saved' => count($valid), 'sid' => $sid], 201);
    }

    /** @return array{role:string,text:string,index:int,interrupted:bool}|null */
    private function validTurn(array $t, ?string &$error = null): ?array
    {
        $role = trim((string) ($t['role'] ?? ''));
        $text = (string) ($t['transcript_text'] ?? '');
        if (!in_array($role, Transcript::ROLES, true)) {
            $error = 'role must be one of: ' . implode(', ', Transcript::ROLES);
            return null;
        }
        if (trim($text) === '') {
            $error = 'transcript_text is required';
            return null;
        }
        $index = filter_var($t['turn_index'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($index === false) {
            $error = 'turn_index must be a non-negative integer';
            return null;
        }
        return [
            'role'        => $role,
            'text'        => $text,
            'index'       => $index,
            'interrupted' => filter_var($t['interrupted'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    // ---- Leads -----------------------------------------------------------

    public function createLead(array $params = []): void
    {
        $body  = $this->requestBody();
        $phone = Client::normalizePhone((string) ($body['phone'] ?? ''));
        if ($phone === '') {
            $this->json(['error' => 'phone is required'], 422);
            return;
        }
        $storeId = !empty($body['store_id']) ? (int) $body['store_id'] : null;
        if ($storeId !== null && !Store::find($storeId)) {
            $this->json(['error' => 'Store not found'], 404);
            return;
        }
        $callSid = trim((string) ($body['call_sid'] ?? '')) ?: null;

        $id = Lead::create([
            'call_sid'    => $callSid,
            'name'        => trim((string) ($body['name'] ?? '')) ?: null,
            'phone'       => $phone,
            'store_id'    => $storeId,
            'reason'      => trim((string) ($body['reason'] ?? '')) ?: null,
            'source'      => mb_substr(trim((string) ($body['source'] ?? '')) ?: 'handoff', 0, 30),
            'transferred' => filter_var($body['transferred'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ]);
        $this->json(['lead' => Lead::find($id)], 201);
    }

    // ---- Llamadas (estado) ----------------------------------------------

    public function callStatus(array $params = []): void
    {
        $body   = $this->requestBody();
        $sid    = trim((string) ($body['call_sid'] ?? ''));
        $status = strtolower(trim((string) ($body['status'] ?? '')));
        if ($sid === '') {
            $this->json(['error' => 'call_sid is required'], 422);
            return;
        }
        if (!in_array($status, Call::STATUSES, true)) {
            $this->json(['error' => 'status must be one of: ' . implode(', ', Call::STATUSES)], 422);
            return;
        }
        $direction = $body['direction'] ?? null;
        if ($direction !== null && !in_array($direction, ['outbound', 'inbound', 'web'], true)) {
            $direction = null;
        }
        $call = Call::upsertStatus($sid, $status, [
            'to'        => $body['to'] ?? null,
            'from'      => $body['from'] ?? null,
            'direction' => $direction,
            'duration'  => $body['duration'] ?? null,
            'error'     => $body['error'] ?? null,
        ]);
        $this->json(['call' => $call]);
    }

    // ---- Utilidades ------------------------------------------------------

    private static function isValidDate(string $date): bool
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    /** Acepta "Y-m-d H:i[:s]" o ISO con "T"; devuelve "Y-m-d H:i:s" o null. */
    public static function normalizeDateTime(string $value): ?string
    {
        $value = str_replace('T', ' ', trim($value));
        foreach (['!Y-m-d H:i:s', '!Y-m-d H:i'] as $format) {
            $d = DateTimeImmutable::createFromFormat($format, $value);
            if ($d !== false && $d->format(substr($format, 1)) === $value) {
                return $d->format('Y-m-d H:i:s');
            }
        }
        return null;
    }
}
