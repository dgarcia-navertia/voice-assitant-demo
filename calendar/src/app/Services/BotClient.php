<?php

namespace App\Services;

use App\Env;

/**
 * Cliente HTTP del bot de voz. PHP NUNCA tiene credenciales de Twilio: pide al
 * bot que marque (POST {BOT_BASE_URL}/dial-out) autenticandose con el secreto
 * compartido INTERNAL_API_TOKEN.
 */
class BotClient
{
    /** @var callable(string,string,array,int):array{0:int,1:string} */
    private $transport;

    /** @param callable|null $transport fn(url, token, payload, timeout): [httpStatus, body] (inyectable en tests) */
    public function __construct(?callable $transport = null, private ?string $baseUrl = null, private ?string $token = null)
    {
        $this->transport = $transport ?? [self::class, 'curlPost'];
    }

    /**
     * @return array{call_sid:string,status:string,to:string}
     * @throws \RuntimeException con un mensaje apto para mostrar al usuario
     */
    public function dialOut(string $to): array
    {
        $base  = rtrim($this->baseUrl ?? Env::get('BOT_BASE_URL', 'http://bot:7860'), '/');
        $token = $this->token ?? Env::get('INTERNAL_API_TOKEN', '');

        try {
            [$status, $body] = ($this->transport)($base . '/dial-out', (string) $token, ['to' => $to], 20);
        } catch (\Throwable $e) {
            error_log('Bot dial-out unreachable: ' . $e->getMessage());
            throw new \RuntimeException('No se pudo contactar con el servicio de voz.');
        }

        $data = json_decode($body, true);
        if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['call_sid'])) {
            $detail = is_array($data) ? (string) ($data['detail'] ?? $data['error'] ?? '') : '';
            error_log("Bot dial-out failed: HTTP {$status} {$detail}");
            throw new \RuntimeException($detail !== '' ? "El servicio de voz rechazó la llamada: {$detail}" : 'El servicio de voz rechazó la llamada.');
        }

        return [
            'call_sid' => (string) $data['call_sid'],
            'status'   => (string) ($data['status'] ?? 'queued'),
            'to'       => (string) ($data['to'] ?? $to),
        ];
    }

    /** @return array{0:int,1:string} */
    public static function curlPost(string $url, string $token, array $payload, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, (string) $body];
    }
}
