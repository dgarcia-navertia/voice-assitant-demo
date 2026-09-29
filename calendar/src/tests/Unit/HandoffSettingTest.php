<?php

declare(strict_types=1);

use App\Database;
use App\Models\Setting;
use App\Services\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/create-admin.php';

/** Modelo Setting (con auditoria), validacion del numero y script create-admin. DB real, todo en una transaccion revertida. */
final class HandoffSettingTest extends TestCase
{
    protected function setUp(): void
    {
        Database::connection()->beginTransaction();
        putenv('HANDOFF_PHONE_NUMBER=+34600000000');
        unset($_ENV['HANDOFF_PHONE_NUMBER']);
    }

    protected function tearDown(): void
    {
        $db = Database::connection();
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        putenv('HANDOFF_PHONE_NUMBER');
    }

    #[DataProvider('validation')]
    public function testValidateHandoff(string $input, ?string $number, bool $hasError): void
    {
        [$n, $e] = PhoneNumber::validateHandoff($input);
        $this->assertSame($number, $n);
        $this->assertSame($hasError, $e !== null);
    }

    public static function validation(): array
    {
        return [
            ['+34 612 345 678', '+34612345678', false],
            ['+34612345678', '+34612345678', false],
            ['', null, true],
            ['612345678', null, true],
            ['+0123456789', null, true],
            ['+34abc', null, true],
        ];
    }

    public function testFallsBackToEnvWhenNotStored(): void
    {
        Database::connection()->exec("DELETE FROM settings WHERE `key` = 'handoff_phone_number'");
        $this->assertSame(['+34600000000', 'env'], Setting::handoffNumber());
    }

    public function testStoredValueWinsAndIsAudited(): void
    {
        $adminId = (int) Database::connection()->query("SELECT MIN(id) FROM users WHERE role = 'admin'")->fetchColumn();
        $this->assertGreaterThan(0, $adminId, 'requires seeded admin');

        Setting::setAudited(Setting::HANDOFF_KEY, '+34611111111', $adminId);
        $this->assertSame(['+34611111111', 'db'], Setting::handoffNumber());

        $row = Setting::row(Setting::HANDOFF_KEY);
        $this->assertSame($adminId, (int) $row['updated_by']);
        $this->assertNotNull($row['updated_at']);
        $this->assertNotEmpty($row['updated_by_name']);

        Setting::setAudited(Setting::HANDOFF_KEY, '+34622222222', $adminId);
        $this->assertSame('+34622222222', Setting::handoffNumber()[0], 'upsert replaces');
        $this->assertSame(1, (int) Database::connection()->query("SELECT COUNT(*) FROM settings WHERE `key`='handoff_phone_number'")->fetchColumn());
    }

    public function testCreateAdminIsIdempotentAndValidates(): void
    {
        $email = 'phpunit.admin@navertia.test';
        $this->assertSame(2, create_admin('nope', 'X', 'Contraseña-larga-123')[0]);
        $this->assertSame(2, create_admin($email, '', 'Contraseña-larga-123')[0]);
        $this->assertSame(2, create_admin($email, 'X', 'corta')[0]);

        $this->assertSame(0, create_admin($email, 'Primero', 'Contraseña-larga-123')[0]);
        $this->assertSame(0, create_admin(strtoupper($email), 'Segundo', 'Otra-contraseña-456')[0]);

        $rows = \App\Models\User::query('SELECT * FROM users WHERE email = ?', [$email]);
        $this->assertCount(1, $rows);
        $this->assertSame('admin', $rows[0]['role']);
        $this->assertSame('Segundo', $rows[0]['name']);
        $this->assertTrue(\App\Models\User::verifyPassword('Otra-contraseña-456', $rows[0]['password']));
    }

    public function testCreateAdminPromotesExistingUser(): void
    {
        $email = 'carlos.ruiz@navertia.demo';
        $this->assertSame(0, create_admin($email, 'Carlos', 'Contraseña-larga-123')[0]);
        $this->assertSame('admin', \App\Models\User::findByEmail($email)['role']); // revertido por rollBack
    }

    public function testApiEndpointReturnsCurrentNumber(): void
    {
        Setting::setAudited(Setting::HANDOFF_KEY, '+34633333333', null);
        ob_start();
        (new \App\Controllers\Api\InternalApiController())->handoffSetting();
        $body = json_decode((string) ob_get_clean(), true);
        $this->assertSame(['handoff_phone_number' => '+34633333333', 'source' => 'db'], $body);
    }

    public function testApiEndpointFallsBackToEnv(): void
    {
        Database::connection()->exec("DELETE FROM settings WHERE `key` = 'handoff_phone_number'");
        ob_start();
        (new \App\Controllers\Api\InternalApiController())->handoffSetting();
        $body = json_decode((string) ob_get_clean(), true);
        $this->assertSame(['handoff_phone_number' => '+34600000000', 'source' => 'env'], $body);
    }
}
