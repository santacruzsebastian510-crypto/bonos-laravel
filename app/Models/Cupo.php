<?php
namespace App\Models;

use App\Support\Database;
use DateTime;

/** Cupos por día y jornada, reservas por categoría, sobrantes y horarios. */
class Cupo
{
    /** Crea la fila de cupos del día si no existe (los cupos del día quedan "congelados" para las estadísticas). */
    public static function asegurar(string $fecha, string $jornada): void
    {
        $s = Database::pdo()->prepare('INSERT IGNORE INTO cupos_dia (fecha, jornada, cupos, reserva_becado, reserva_convocatoria) VALUES (?, ?, ?, ?, ?)');
        $s->execute([$fecha, $jornada, Configuracion::cuposDefault($jornada),
                     Configuracion::reservaDefault($jornada, 'becado'), Configuracion::reservaDefault($jornada, 'convocatoria')]);
    }

    /** @return array{cupos:int,usados:int,libres:int} */
    public static function disponibilidad(string $fecha, string $jornada): array
    {
        self::asegurar($fecha, $jornada);
        $s = Database::pdo()->prepare(
            'SELECT c.cupos, (SELECT COUNT(*) FROM bonos b WHERE b.fecha = c.fecha AND b.jornada = c.jornada AND b.estado IN ' . Bono::ACTIVOS . ') AS usados
               FROM cupos_dia c WHERE c.fecha = ? AND c.jornada = ?'
        );
        $s->execute([$fecha, $jornada]);
        $r = $s->fetch();
        $cupos = (int)$r['cupos'];
        $usados = (int)$r['usados'];
        return ['cupos' => $cupos, 'usados' => $usados, 'libres' => max(0, $cupos - $usados)];
    }

    /** Cupos totales, reservas y bonos vigentes por categoría de un día/jornada. */
    public static function estado(string $fecha, string $jornada, bool $bloquear = false): array
    {
        $pdo = Database::pdo();
        self::asegurar($fecha, $jornada);
        $s = $pdo->prepare('SELECT cupos, reserva_becado, reserva_convocatoria FROM cupos_dia WHERE fecha = ? AND jornada = ?' . ($bloquear ? ' FOR UPDATE' : ''));
        $s->execute([$fecha, $jornada]);
        $c = $s->fetch();
        $s = $pdo->prepare('SELECT categoria, COUNT(*) FROM bonos WHERE fecha = ? AND jornada = ? AND estado IN ' . Bono::ACTIVOS . ' GROUP BY categoria');
        $s->execute([$fecha, $jornada]);
        $u = $s->fetchAll(\PDO::FETCH_KEY_PAIR);
        $usados = ['becado' => (int)($u['becado'] ?? 0), 'convocatoria' => (int)($u['convocatoria'] ?? 0), 'regular' => (int)($u['regular'] ?? 0)];
        return [
            'cupos'   => (int)$c['cupos'],
            'reserva' => ['becado' => (int)$c['reserva_becado'], 'convocatoria' => (int)$c['reserva_convocatoria'], 'regular' => 0],
            'usados'  => $usados,
            'total'   => array_sum($usados),
        ];
    }

    /** ¿Ya se abrieron los cupos sobrantes (los reservados que nadie pidió) para los demás estudiantes? */
    public static function sobrantesAbiertos(string $fecha, string $jornada, ?DateTime $ahora = null): bool
    {
        $ahora ??= new DateTime('now');
        return $ahora >= new DateTime($fecha . ' ' . HORARIOS[$jornada]['sobrantes']);
    }

    /**
     * Cupos que puede tomar un estudiante de la categoría $cat:
     *  - antes de la hora de sobrantes: solo los reservados para su categoría (los regulares no tienen reserva);
     *  - desde la hora de sobrantes: cualquier cupo libre del día (los reservados que nadie pidió pasan a los demás).
     */
    public static function libresPara(string $cat, array $e, bool $abiertos): int
    {
        $libresTotal = max(0, $e['cupos'] - $e['total']);
        if ($abiertos) {
            return $libresTotal;
        }
        return min($libresTotal, max(0, ($e['reserva'][$cat] ?? 0) - ($e['usados'][$cat] ?? 0)));
    }

    /** Hoy (si es día hábil) y el siguiente día hábil. */
    public static function diasReservables(): array
    {
        $out = [];
        $d = new DateTime('today');
        for ($i = 0; $i < 7 && count($out) < 2; $i++) {
            if ((int)$d->format('N') <= 5) {
                $out[] = $d->format('Y-m-d');
            }
            $d->modify('+1 day');
        }
        return $out;
    }

    public static function limitePasado(string $fecha, string $jornada): bool
    {
        return new DateTime('now') >= new DateTime($fecha . ' ' . HORARIOS[$jornada]['limite']);
    }

    /** Momento en que termina la entrega de la jornada ("11:30 – 14:00" → 14:00). */
    public static function finEntrega(string $fecha, string $jornada): DateTime
    {
        $fin = trim(explode('–', HORARIOS[$jornada]['entrega'])[1] ?? '23:59');
        return new DateTime($fecha . ' ' . $fin);
    }

    /**
     * Guarda el total de cupos y las reservas de cada jornada (se aplican de hoy en adelante).
     * @return array{0:bool,1:string} [ok, mensaje]
     */
    public static function actualizar(array $post): array
    {
        $pdo = Database::pdo();
        $nuevos = [];
        foreach (HORARIOS as $j => $info) {
            $t = (int)($post["cupos_$j"] ?? 0);
            $b = (int)($post["reserva_{$j}_becado"] ?? 0);
            $c = (int)($post["reserva_{$j}_convocatoria"] ?? 0);
            $s = $pdo->prepare('SELECT COALESCE(MAX(x), 0) FROM (SELECT COUNT(*) AS x FROM bonos WHERE fecha >= CURDATE() AND jornada = ? AND estado IN ' . Bono::ACTIVOS . ' GROUP BY fecha) t');
            $s->execute([$j]);
            if ($t < 1 || $t > 5000 || $b < 0 || $c < 0) {
                return [false, 'Los cupos deben estar entre 1 y 5000 y las reservas no pueden ser negativas.'];
            }
            if ($b + $c > $t) {
                return [false, 'En la jornada ' . $info['nombre'] . ', las reservas (becados + convocatoria) no pueden superar el total de cupos.'];
            }
            if ($t < (int)$s->fetchColumn()) {
                return [false, 'No puedes bajar los cupos de la jornada ' . $info['nombre'] . ' por debajo de los bonos ya pedidos.'];
            }
            $nuevos[$j] = ['total' => $t, 'becado' => $b, 'convocatoria' => $c];
        }
        foreach ($nuevos as $j => $v) {
            Configuracion::guardar('cupos_' . $j, (string)$v['total']);
            Configuracion::guardar("reserva_{$j}_becado", (string)$v['becado']);
            Configuracion::guardar("reserva_{$j}_convocatoria", (string)$v['convocatoria']);
            $pdo->prepare('UPDATE cupos_dia SET cupos = ?, reserva_becado = ?, reserva_convocatoria = ? WHERE fecha >= CURDATE() AND jornada = ?')
                ->execute([$v['total'], $v['becado'], $v['convocatoria'], $j]);
        }
        Auditoria::registrar('cupos_modificados', 'configuracion', null, $nuevos);
        return [true, 'Cupos actualizados (se aplican de hoy en adelante).'];
    }
}
