<?php

namespace App\Services;

/** Validacion y normalizacion de telefonos en E.164. */
final class PhoneNumber
{
    /** E.164: '+' , primer digito 1-9, 7 a 15 digitos en total. */
    public const E164 = '/^\+[1-9]\d{6,14}$/';

    public static function isE164(string $phone): bool
    {
        return preg_match(self::E164, $phone) === 1;
    }

    /** Quita espacios, guiones y parentesis. No inventa prefijos. */
    public static function clean(string $phone): string
    {
        return preg_replace('/[\s\-\(\)\.]/', '', $phone) ?? '';
    }

    /** Une prefijo ("+34") y numero nacional ("612 345 678") -> "+34612345678"; el 0 inicial de troncal se elimina. */
    public static function compose(string $dialCode, string $national): string
    {
        $dial = self::clean($dialCode);
        $num  = ltrim(self::clean($national), '0');
        if (str_starts_with(self::clean($national), '+')) {
            return self::clean($national);
        }
        return $dial . $num;
    }
}
