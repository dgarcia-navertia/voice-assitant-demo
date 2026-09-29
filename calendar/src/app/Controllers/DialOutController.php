<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Call;
use App\Models\Transcript;
use App\Services\BotClient;
use App\Services\PhoneNumber;

/**
 * Vista "Dial Out": lanza una llamada saliente del asistente de voz.
 *
 * El navegador habla con este controlador; este habla con el bot
 * (BotClient::dialOut). Twilio solo lo toca el bot.
 */
class DialOutController extends Controller
{
    public function index(array $params = []): void
    {
        $this->render('dial-out/index.php', [
            'csrf'  => $this->csrfToken(),
            'calls' => Call::recent(8),
        ]);
    }

    public function dial(array $params = []): void
    {
        if (!$this->csrfValid()) {
            $this->json(['error' => 'Sesión caducada. Recarga la página.'], 403);
            return;
        }

        $body = $this->requestBody();
        $to   = PhoneNumber::clean((string) ($body['to'] ?? ''));
        if (!PhoneNumber::isE164($to)) {
            $this->json(['error' => 'Teléfono no válido. Usa el formato internacional, por ejemplo +34612345678.'], 422);
            return;
        }

        try {
            $result = $this->bot()->dialOut($to);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 502);
            return;
        }

        // Puede que el callback de estado del bot llegue antes que esta respuesta.
        $existing = Call::findBySid($result['call_sid']);
        if ($existing === null) {
            Call::create([
                'call_sid'   => $result['call_sid'],
                'direction'  => 'outbound',
                'to_number'  => $to,
                'status'     => $result['status'] ?: 'queued',
                'created_by' => Auth::id(),
            ]);
        } else {
            Call::update((int) $existing['id'], ['created_by' => Auth::id(), 'to_number' => $to]);
        }

        $this->json(['call_sid' => $result['call_sid'], 'status' => $result['status'], 'to' => $to], 201);
    }

    /** Estado de una llamada; la vista lo consulta cada pocos segundos. */
    public function status(array $params = []): void
    {
        $call = Call::findBySid((string) ($params['sid'] ?? ''));
        if (!$call) {
            $this->json(['error' => 'Llamada no encontrada'], 404);
            return;
        }
        $terminal = Call::isTerminal($call['status']);
        $turns = Transcript::lastTurnIndex($call['call_sid']);
        $this->json([
            'call_sid'         => $call['call_sid'],
            'status'           => $call['status'],
            'terminal'         => $terminal,
            'to'               => $call['to_number'],
            'duration_seconds' => $call['duration_seconds'] !== null ? (int) $call['duration_seconds'] : null,
            'error'            => $call['error'],
            'has_transcript'   => $turns !== null,
            'transcript_url'   => $terminal && $turns !== null ? '/calls/' . rawurlencode($call['call_sid']) : null,
        ]);
    }

    protected function bot(): BotClient
    {
        return new BotClient();
    }
}
