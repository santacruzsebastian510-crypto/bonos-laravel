<?php
namespace App\Models;

use App\Support\Database;

/** Tabla `configuracion` (clave/valor): cupos por defecto, reservas, modo de aprobación. */
class Configuracion
{
    public static function valor(string $clave, string $def = ''): string
    {
        $s = Database::pdo()->prepare('SELECT valor FROM configuracion WHERE clave = ?');
        $s->execute([$clave]);
        $v = $s->fetchColumn();
        return $v === false ? $def : (string)$v;
    }

    public static function guardar(string $clave, string $valor): void
    {
        Database::pdo()->prepare('REPLACE INTO configuracion (clave, valor) VALUES (?, ?)')->execute([$clave, $valor]);
    }

    public static function aprobacionManual(): bool
    {
        return self::valor('aprobacion_manual', '1') === '1';
    }

    public static function cuposDefault(string $jornada): int
    {
        $v = self::valor('cupos_' . $jornada, '');
        return $v === '' ? 250 : (int)$v;
    }

    public static function reservaDefault(string $jornada, string $cat): int
    {
        return (int)self::valor("reserva_{$jornada}_{$cat}", '0');
    }
}
