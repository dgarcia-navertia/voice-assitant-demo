<?php

declare(strict_types=1);

use App\Services\BotClient;
use PHPUnit\Framework\TestCase;

/** PHP nunca llama a Twilio: BotClient solo habla con el bot (aqui, simulado). */
final class BotClientTest extends TestCase
{
    public function testDialOutSendsTokenAndNumberToBot(): void
    {
        $seen = [];
        $client = new BotClient(function (string $url, string $token, array $payload, int $timeout) use (&$seen) {
            $seen = compact('url', 'token', 'payload');
            return [200, json_encode(['call_sid' => 'CA123', 'status' => 'queued', 'to' => $payload['to']])];
        }, 'http://bot:7860/', 'secret-token');

        $result = $client->dialOut('+34612345678');

        $this->assertSame('http://bot:7860/dial-out', $seen['url']);
        $this->assertSame('secret-token', $seen['token']);
        $this->assertSame(['to' => '+34612345678'], $seen['payload']);
        $this->assertSame(['call_sid' => 'CA123', 'status' => 'queued', 'to' => '+34612345678'], $result);
    }

    public function testBotRejectionBecomesUserFacingError(): void
    {
        $client = new BotClient(fn() => [502, json_encode(['detail' => 'Twilio error'])], 'http://bot', 't');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Twilio error');
        $client->dialOut('+34612345678');
    }

    public function testUnreachableBotBecomesGenericError(): void
    {
        $client = new BotClient(function () { throw new RuntimeException('connection refused'); }, 'http://bot', 't');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo contactar');
        $client->dialOut('+34612345678');
    }

    public function testResponseWithoutCallSidIsAnError(): void
    {
        $client = new BotClient(fn() => [200, '{}'], 'http://bot', 't');
        $this->expectException(RuntimeException::class);
        $client->dialOut('+34612345678');
    }
}
