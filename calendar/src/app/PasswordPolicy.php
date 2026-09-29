<?php

namespace App;

/**
 * Requisitos de una contraseña de la agenda.
 *
 * Vive fuera de los controladores porque la aplican dos flujos distintos
 * (cambiar la contraseña desde "Mi cuenta" y restablecerla desde el email) y
 * tienen que exigir exactamente lo mismo: si uno fuese más laxo, sería el que
 * usaría cualquiera para saltarse al otro.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 24;

    /** Mensaje de error en español, o null si la contraseña es válida. */
    public static function validate(string $password): ?string
    {
        // mb_strlen, no strlen: con strlen una contraseña con acentos o eñes
        // contaría bytes y no caracteres, y "Contraseñaañ1" ya no cabría en 24.
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return 'La contraseña debe tener entre ' . self::MIN_LENGTH . ' y ' . self::MAX_LENGTH . ' caracteres.';
        }
        if (!preg_match('/\p{Lu}/u', $password)) {
            return 'La contraseña debe incluir al menos una letra mayúscula.';
        }
        if (!preg_match('/\d/', $password)) {
            return 'La contraseña debe incluir al menos un número.';
        }
        if (preg_match('/[\r\n\t]/', $password)) {
            return 'La contraseña no puede contener saltos de línea ni tabulaciones.';
        }
        return null;
    }

    /** Texto de ayuda que se muestra bajo los campos de contraseña. */
    public static function help(): string
    {
        return 'Entre ' . self::MIN_LENGTH . ' y ' . self::MAX_LENGTH
            . ' caracteres, con al menos una mayúscula y un número.';
    }
}
