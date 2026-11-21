<?php
namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Estadistica;
use Illuminate\Http\Request;

/** Exporta las estadísticas a CSV, Excel, JSON o PDF (página lista para imprimir). */
class ExportarController extends Controller
{
    /** Convierte filas en texto CSV (separador ; para que Excel en español lo abra bien). */
    private static function csv(array $bloques): string
    {
        $o = fopen('php://temp', 'w+');
        foreach ($bloques as $fila) {
            fputcsv($o, $fila, ';');
        }
        rewind($o);
        $texto = stream_get_contents($o);
        fclose($o);
        return $texto;
    }

    public function index(Request $request)
    {
        $formato = (string)$request->query('formato', 'csv');
        Auditoria::registrar('exportacion', 'reporte', null, ['formato' => $formato, 'desde' => (string)$request->query('desde', ''), 'hasta' => (string)$request->query('hasta', '')]);

        [$desde, $hasta, $jor] = Estadistica::periodo($request->query());
        $rows = Estadistica::resumenDiario($desde, $hasta, $jor);
        $sem  = Estadistica::resumenSemanal($rows);
        $tot  = Estadistica::totales($rows);
        $cats = Estadistica::resumenCategorias($desde, $hasta, $jor);
        $archivo = "bonos_{$desde}_a_{$hasta}";

        $cabDetalle = ['Fecha', 'Día', 'Jornada', 'Cupos', 'Separados', 'Entregados', 'No recogidos', 'Sobrantes', 'Entregados becados', 'Entregados convocatoria', 'Entregados regulares'];
        $detalle = array_map(fn($r) => [$r['fecha'], DIAS[$r['dow']], HORARIOS[$r['jornada']]['nombre'], $r['cupos'],
                                        $r['reservados'], $r['entregados'], $r['no_recogidos'], $r['sobrantes'], $r['ent_becado'], $r['ent_convocatoria'], $r['ent_regular']], $rows);

        $cabSem = ['Día de la semana', 'Días contados', 'Entregados', 'Promedio entregados', 'No recogidos', 'Sobrantes', 'Promedio sobrantes'];
        $resSem = array_map(fn($w) => [$w['dia'], $w['dias'], $w['entregados'], $w['prom_entregados'],
                                       $w['no_recogidos'], $w['sobrantes'], $w['prom_sobrantes']], array_values($sem));

        $cabCat = ['Categoría', 'Cupos reservados (regulares: sobrantes base)', 'Separados', 'Entregados', 'No recogidos', '% recogida'];
        $resCat = [];
        foreach (CATEGORIAS as $k => $nombreCat) {
            $c = $cats['cat'][$k];
            $resCat[] = [$nombreCat, $c['cupos_reservados'], $c['reservados'], $c['entregados'], $c['no_recogidos'], $c['tasa']];
        }

        $d = compact('desde', 'hasta', 'jor', 'rows', 'sem', 'tot', 'cats', 'archivo', 'cabDetalle', 'detalle', 'cabSem', 'resSem', 'cabCat', 'resCat');

        switch ($formato) {
            case 'json':
                return response(json_encode([
                    'periodo' => ['desde' => $desde, 'hasta' => $hasta, 'jornada' => $jor ?? 'ambas'],
                    'totales' => $tot,
                    'por_categoria' => $cats,
                    'por_dia_de_la_semana' => array_values($sem),
                    'detalle_diario' => $rows,
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 200, [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Content-Disposition' => "attachment; filename=\"$archivo.json\"",
                ]);

            case 'xls': // HTML que Excel abre como hoja de cálculo (sin librerías externas)
                return response("\xEF\xBB\xBF" . view('exportar.xls', $d)->render(), 200, [
                    'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
                    'Content-Disposition' => "attachment; filename=\"$archivo.xls\"",
                ]);

            case 'pdf': // Página lista para "Guardar como PDF" desde el diálogo de impresión
                return response(view('exportar.pdf', $d)->render());

            default: // csv (separador ; y BOM para que Excel en español lo abra bien)
                $filas = [['Resumen por día de la semana'], $cabSem];
                foreach ($resSem as $r) $filas[] = $r;
                $filas[] = [];
                $filas[] = ['Por categoría'];
                $filas[] = $cabCat;
                foreach ($resCat as $r) $filas[] = $r;
                $filas[] = ['Cupos reservados liberados como sobrantes', $cats['liberados']];
                $filas[] = [];
                $filas[] = ['Detalle diario'];
                $filas[] = $cabDetalle;
                foreach ($detalle as $r) $filas[] = $r;
                return response("\xEF\xBB\xBF" . self::csv($filas), 200, [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => "attachment; filename=\"$archivo.csv\"",
                ]);
        }
    }
}
