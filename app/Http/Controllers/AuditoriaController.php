<?php
namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;

/** Consulta de la bitácora de auditoría y verificación de la cadena de hashes. */
class AuditoriaController extends Controller
{
    private function filtros(Request $r): array
    {
        return [
            'accion' => (string)$r->query('accion', ''),
            'actor'  => trim((string)$r->query('actor', '')),
            'desde'  => valid_fecha((string)$r->query('desde', '')) ?: '',
            'hasta'  => valid_fecha((string)$r->query('hasta', '')) ?: '',
        ];
    }

    public function index(Request $request)
    {
        $f = $this->filtros($request);
        if ($request->query->has('csv')) {
            return $this->csv($f);
        }
        $pag = max(1, (int)$request->query('p', 1));
        $por = 100;
        return view('auditoria.index', [
            'f' => $f, 'pag' => $pag, 'por' => $por,
            'verif' => $request->query->has('verificar') ? Auditoria::verificarCadena() : null,
            'total' => Auditoria::contar($f), 'filas' => Auditoria::pagina($f, $pag, $por),
            'acciones' => Auditoria::acciones(), 'ultimo' => Auditoria::ultimo(),
            'qs' => http_build_query($f), 'titulo' => 'Auditoría',
        ]);
    }

    private function csv(array $f)
    {
        $o = fopen('php://temp', 'w+');
        fputcsv($o, ['id', 'fecha_hora', 'actor', 'rol', 'accion', 'entidad', 'entidad_id', 'detalle', 'ip', 'hash'], ';');
        foreach (Auditoria::paraCsv($f) as $r) {
            fputcsv($o, $r, ';');
        }
        rewind($o);
        $contenido = "\xEF\xBB\xBF" . stream_get_contents($o);
        fclose($o);
        return response($contenido, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="auditoria_' . date('Ymd_His') . '.csv"',
        ]);
    }
}
