<?php

declare(strict_types=1);

use App\Controllers\Api\InternalApiController;
use App\Middlewares\InternalApiMiddleware;
use App\Models\Call;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InternalApiTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_API_KEY']);
    }

    #[DataProvider('dateTimes')]
    public function testNormalizeDateTime(string $input, ?string $expected): void
    {
        $this->assertSame($expected, InternalApiController::normalizeDateTime($input));
    }

    public static function dateTimes(): array
    {
        return [
            ['2026-10-06 10:00:00', '2026-10-06 10:00:00'],
            ['2026-10-06 10:00', '2026-10-06 10:00:00'],
            ['2026-10-06T10:30:00', '2026-10-06 10:30:00'],
            ['2026-13-06 10:00:00', null],
            ['2026-10-06', null],
            ['mañana a las 10', null],
            ['', null],
        ];
    }

    public function testExtractsBearerToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123';
        $this->assertSame('abc123', InternalApiMiddleware::extractKey());
    }

    public function testExtractsApiKeyHeader(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'k-1';
        $this->assertSame('k-1', InternalApiMiddleware::extractKey());
    }

    public function testNoCredentialsYieldsNull(): void
    {
        $this->assertNull(InternalApiMiddleware::extractKey());
    }

    public function testCallTerminalStates(): void
    {
        foreach (['completed', 'failed', 'busy', 'no-answer', 'canceled'] as $s) {
            $this->assertTrue(Call::isTerminal($s), $s);
        }
        foreach (['queued', 'ringing', 'in-progress'] as $s) {
            $this->assertFalse(Call::isTerminal($s), $s);
        }
    }
}
