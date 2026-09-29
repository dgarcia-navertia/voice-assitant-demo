<?php

declare(strict_types=1);

use App\Services\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function testAcceptsE164(string $number): void
    {
        $this->assertTrue(PhoneNumber::isE164($number));
    }

    #[DataProvider('invalidNumbers')]
    public function testRejectsNonE164(string $number): void
    {
        $this->assertFalse(PhoneNumber::isE164($number));
    }

    public static function validNumbers(): array
    {
        return [['+34612345678'], ['+14155552671'], ['+442071838750'], ['+3491234567']];
    }

    public static function invalidNumbers(): array
    {
        return [[''], ['612345678'], ['+0612345678'], ['+34 612 345 678'], ['+34abc'], ['+123456'], ['+1234567890123456'], ['0034612345678']];
    }

    public function testCleanStripsFormatting(): void
    {
        $this->assertSame('+34612345678', PhoneNumber::clean('+34 (612) 345-678'));
    }

    public function testComposeJoinsDialCodeAndNationalNumber(): void
    {
        $this->assertSame('+34612345678', PhoneNumber::compose('+34', '612 345 678'));
        $this->assertSame('+34612345678', PhoneNumber::compose('+34', '0612345678'));
        $this->assertSame('+441234567890', PhoneNumber::compose('+34', '+44 1234 567890'), 'a full international number wins over the selected prefix');
    }
}
