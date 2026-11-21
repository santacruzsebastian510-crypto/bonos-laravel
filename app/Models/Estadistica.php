<?php
namespace App\Models;

use App\Support\Database;
use DateTime;
use PDO;

/** Cifras para el panel del administrador y las exportaciones. Solo cuentan días cerrados (hasta ayer). */
class Estadistica
{
    /** Filtros (desde, hasta, jornada) a partir de $_GET. @return array{0:string,1:string,2:?string} */
    public static function periodo(array $get): array
    {
        $ayer  = (new DateTime('yesterday'))->format('Y-m-d');
        $hasta = valid_fecha($get['hasta'] ?? '') ?: $ayer;
        if ($hasta > $ayer) {
            $hasta = $ayer;
        }
        $desde = valid_fecha($get['desde'] ?? '') ?: (new DateTime($hasta))->modify('-27 days')->format('Y-m-d');
        if ($desde > $hasta) {
            $desde = $hasta;
        }
        $j = $get['jornada'] ?? '';
        return [$desde, $hasta, isset(HORARIOS[$j]) ? $j : null];
    }

    /** Resumen por día y jornada. */
    public static function resumenDiario(string $desde, string $hasta, ?string $jornada): array
    {
        $ayer = (new DateTime('yesterday'))->format('Y-m-d');
        if ($hasta > $ayer) {
            $hasta = $ayer;
        }
        $sql = "SELECT c.fecha, c.jornada, c.cupos,
                       COUNT(b.id) AS reservados,
                       COALESCE(SUM(b.estado = 'entregado'), 0) AS entregados,
                       COALESCE(SUM(b.estado = 'entregado' AND b.categoria = 'becado'), 0) AS ent_becado,
                       COALESCE(SUM(b.estado = 'entregado' AND b.categoria = 'convocatoria'), 0) AS ent_convocatoria,
                       COALESCE(SUM(b.estado = 'entregado' AND b.categoria = 'regular'), 0) AS ent_regular
                  FROM cupos_dia c
                  LEFT JOIN bonos b ON b.fecha = c.fecha AND b.jornada = c.jornada AND b.estado IN ('solicitado','aprobado','entregado','no_recogido')
                 WHERE c.fecha BETWEEN ? AND ?" . ($jornada ? ' AND c.jornada = ?' : '') . '
                 GROUP BY c.fecha, c.jornada, c.cupos
                 ORDER BY c.fecha, c.jornada';
        $params = [$desde, $hasta];
        if ($jornada) {
            $params[] = $jornada;
        }
        $s = Database::pdo()->prepare($sql);
        $s->execute($params);

        $rows = [];
        foreach ($s->fetchAll() as $r) {
            $cupos = (int)$r['cupos'];
            $res = (int)$r['reservados'];
            $ent = (int)$r['entregados'];
            $rows[] = [
                'fecha'            => $r['fecha'],
                'dow'              => (int)(new DateTime($r['fecha']))->format('N'),
                'jornada'          => $r['jornada'],
                'cupos'            => $cupos,
                'reservados'       => $res,
                'entregados'       => $ent,
                'ent_becado'       => (int)$r['ent_becado'],
                'ent_convocatoria' => (int)$r['ent_convocatoria'],
                'ent_regular'      => (int)$r['ent_regular'],
                'no_recogidos'     => $res - $ent,        // separaron pero no recogieron
                'sobrantes'        => $cupos - $ent,      // cupos que no terminaron en un plato entregado
            ];
        }
        return $rows;
    }

    /** Agrupa por día de la semana (Lunes–Viernes). */
    public static function resumenSemanal(array $rows): array
    {
        $sem = [];
        foreach ([1, 2, 3, 4, 5] as $n) {
            $sem[$n] = ['dia' => DIAS[$n], 'fechas' => [], 'cupos' => 0, 'reservados' => 0,
                        'entregados' => 0, 'no_recogidos' => 0, 'sobrantes' => 0];
        }
        foreach ($rows as $r) {
            if (!isset($sem[$r['dow']])) {
                continue;
            }
            $w = &$sem[$r['dow']];
            $w['fechas'][$r['fecha']] = true;
            foreach (['cupos', 'reservados', 'entregados', 'no_recogidos', 'sobrantes'] as $k) {
                $w[$k] += $r[$k];
            }
            unset($w);
        }
        foreach ($sem as &$w) {
            $dias = count($w['fechas']);
            $w['dias'] = $dias;
            $w['prom_entregados']   = $dias ? round($w['entregados'] / $dias, 1) : 0;
            $w['prom_no_recogidos'] = $dias ? round($w['no_recogidos'] / $dias, 1) : 0;
            $w['prom_sobrantes']    = $dias ? round($w['sobrantes'] / $dias, 1) : 0;
            unset($w['fechas']);
        }
        unset($w);
        return $sem;
    }

    public static function totales(array $rows): array
    {
        $t = ['cupos' => 0, 'reservados' => 0, 'entregados' => 0, 'no_recogidos' => 0, 'sobrantes' => 0];
        foreach ($rows as $r) {
            foreach ($t as $k => $_) {
                $t[$k] += $r[$k];
            }
        }
        $t['tasa'] = $t['reservados'] ? round(100 * $t['entregados'] / $t['reservados'], 1) : 0;
        return $t;
    }

    /** Resumen por categoría. @return array{cat:array,liberados:int} */
    public static function resumenCategorias(string $desde, string $hasta, ?string $jornada): array
    {
        $pdo = Database::pdo();
        $ayer = (new DateTime('yesterday'))->format('Y-m-d');
        if ($hasta > $ayer) {
            $hasta = $ayer;
        }
        $par = [$desde, $hasta];
        $fj = '';
        if ($jornada) {
            $fj = ' AND jornada = ?';
            $par[] = $jornada;
        }
        $out = [];
        foreach (array_keys(CATEGORIAS) as $c) {
            $out[$c] = ['reservados' => 0, 'entregados' => 0, 'no_recogidos' => 0, 'cupos_reservados' => 0, 'tasa' => 0];
        }
        $s = $pdo->prepare("SELECT categoria, COUNT(*) AS res, SUM(estado = 'entregado') AS ent FROM bonos
                             WHERE fecha BETWEEN ? AND ? AND estado IN " . Bono::ACTIVOS . $fj . ' GROUP BY categoria');
        $s->execute($par);
        foreach ($s->fetchAll() as $r) {
            $out[$r['categoria']]['reservados'] = (int)$r['res'];
            $out[$r['categoria']]['entregados'] = (int)$r['ent'];
            $out[$r['categoria']]['no_recogidos'] = (int)$r['res'] - (int)$r['ent'];
        }
        $s = $pdo->prepare('SELECT COALESCE(SUM(reserva_becado),0), COALESCE(SUM(reserva_convocatoria),0), COALESCE(SUM(cupos),0)
                              FROM cupos_dia WHERE fecha BETWEEN ? AND ?' . $fj);
        $s->execute($par);
        [$rb, $rc, $cupos] = $s->fetch(PDO::FETCH_NUM);
        $out['becado']['cupos_reservados'] = (int)$rb;
        $out['convocatoria']['cupos_reservados'] = (int)$rc;
        $out['regular']['cupos_reservados'] = (int)$cupos - (int)$rb - (int)$rc;   // sobrantes "de base" (sin contar los liberados)
        // reservados que nadie pidió y pasaron a ser sobrantes
        $s = $pdo->prepare("SELECT COALESCE(SUM(GREATEST(c.reserva_becado - COALESCE(u.ub,0), 0) + GREATEST(c.reserva_convocatoria - COALESCE(u.uc,0), 0)), 0)
                              FROM cupos_dia c LEFT JOIN (
                                   SELECT fecha, jornada, SUM(categoria = 'becado') AS ub, SUM(categoria = 'convocatoria') AS uc
                                     FROM bonos WHERE estado IN " . Bono::ACTIVOS . " GROUP BY fecha, jornada) u ON u.fecha = c.fecha AND u.jornada = c.jornada
                             WHERE c.fecha BETWEEN ? AND ?" . ($jornada ? ' AND c.jornada = ?' : ''));
        $s->execute($par);
        $liberados = (int)$s->fetchColumn();
        foreach ($out as &$o) {
            $o['tasa'] = $o['reservados'] ? round(100 * $o['entregados'] / $o['reservados'], 1) : 0;
        }
        unset($o);
        return ['cat' => $out, 'liberados' => $liberados];
    }

    /** Día de la semana con más/menos entregas y con más sobrantes. @return array{0:?array,1:?array,2:?array} */
    public static function extremos(array $sem): array
    {
        $conDatos = array_filter($sem, fn($w) => $w['dias'] > 0);
        if (!$conDatos) {
            return [null, null, null];
        }
        return [
            array_reduce($conDatos, fn($c, $w) => (!$c || $w['prom_entregados'] > $c['prom_entregados']) ? $w : $c),
            array_reduce($conDatos, fn($c, $w) => (!$c || $w['prom_entregados'] < $c['prom_entregados']) ? $w : $c),
            array_reduce($conDatos, fn($c, $w) => (!$c || $w['prom_sobrantes'] > $c['prom_sobrantes']) ? $w : $c),
        ];
    }

    /** Entregados y sobrantes por fecha, y entregados por jornada (para las gráficas). */
    public static function agrupar(array $rows): array
    {
        $porFecha = [];
        $porJornada = ['diurna' => 0, 'nocturna' => 0];
        foreach ($rows as $r) {
            $porFecha[$r['fecha']]['entregados'] = ($porFecha[$r['fecha']]['entregados'] ?? 0) + $r['entregados'];
            $porFecha[$r['fecha']]['sobrantes']  = ($porFecha[$r['fecha']]['sobrantes'] ?? 0) + $r['sobrantes'];
            $porJornada[$r['jornada']] += $r['entregados'];
        }
        return [$porFecha, $porJornada];
    }

    /** Estado de hoy por categoría y jornada (para el panel). */
    public static function hoyPorCategoria(): array
    {
        $hoy = date('Y-m-d');
        $out = [];
        foreach (HORARIOS as $j => $info) {
            $out[$j] = ['info' => $info, 'estado' => Cupo::estado($hoy, $j), 'abiertos' => Cupo::sobrantesAbiertos($hoy, $j)];
        }
        return $out;
    }

    /** Panel en vivo (lo consulta el navegador cada 10 s). */
    public static function enVivo(): array
    {
        $pdo = Database::pdo();
        $out = [];
        foreach (HORARIOS as $j => $info) {
            $d = Cupo::disponibilidad(date('Y-m-d'), $j);
            $s = $pdo->prepare('SELECT estado, COUNT(*) FROM bonos WHERE fecha = CURDATE() AND jornada = ? GROUP BY estado');
            $s->execute([$j]);
            $e = $s->fetchAll(PDO::FETCH_KEY_PAIR);
            $e2 = Cupo::estado(date('Y-m-d'), $j);
            $s = $pdo->prepare("SELECT categoria, estado, COUNT(*) FROM bonos WHERE fecha = CURDATE() AND jornada = ? AND estado IN ('solicitado','aprobado','entregado','no_recogido') GROUP BY categoria, estado");
            $s->execute([$j]);
            $cats = ['becado' => ['pedidos' => 0, 'entregados' => 0], 'convocatoria' => ['pedidos' => 0, 'entregados' => 0], 'regular' => ['pedidos' => 0, 'entregados' => 0]];
            foreach ($s->fetchAll(PDO::FETCH_NUM) as [$ca, $es, $n]) {
                $cats[$ca]['pedidos'] += (int)$n;
                if ($es === 'entregado') {
                    $cats[$ca]['entregados'] += (int)$n;
                }
            }
            $cats['becado']['reserva'] = $e2['reserva']['becado'];
            $cats['convocatoria']['reserva'] = $e2['reserva']['convocatoria'];
            $out[$j] = [
                'categorias' => $cats, 'sobrantes_abiertos' => Cupo::sobrantesAbiertos(date('Y-m-d'), $j), 'sobrantes_desde' => HORARIOS[$j]['sobrantes'],
                'nombre' => $info['nombre'], 'cupos' => $d['cupos'], 'libres' => $d['libres'],
                'solicitados' => (int)($e['solicitado'] ?? 0), 'aprobados' => (int)($e['aprobado'] ?? 0),
                'entregados' => (int)($e['entregado'] ?? 0), 'no_recogidos' => (int)($e['no_recogido'] ?? 0),
                'rechazados' => (int)($e['rechazado'] ?? 0),
            ];
        }
        return $out;
    }
}
