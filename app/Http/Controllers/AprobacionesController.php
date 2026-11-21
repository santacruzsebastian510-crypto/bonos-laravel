<?php
namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Bono;
use App\Models\Configuracion;
use App\Models\Cupo;
use App\Models\Usuario;
use Illuminate\Http\Request;

/** Bienestar aprueba o rechaza solicitudes (y elige el modo manual/automático). */
class AprobacionesController extends Controller
{
    /** @return array{0:array,1:string,2:string} [fechas, fecha elegida, jornada ('' = ambas)] */
    private function filtros(Request $r): array
    {
        $fechas = Cupo::diasReservables();
        $f = $this->campo($r, 'fecha');
        $fecha = in_array($f, $fechas, true) ? $f : $fechas[0];
        $j = $this->campo($r, 'jornada');
        return [$fechas, $fecha, isset(HORARIOS[$j]) ? $j : ''];
    }

    public function index(Request $request)
    {
        [$fechas, $fecha, $jor] = $this->filtros($request);
        $pend = Bono::pendientes($fecha, $jor ?: null);
        return view('aprobaciones.index', [
            'u' => user(), 'fechas' => $fechas, 'fecha' => $fecha, 'jor' => $jor,
            'pend' => $pend, 'cont' => Bono::contadores($fecha), 'recientes' => Bono::recientesResueltos($fecha),
            'elegibles' => count(array_filter($pend, fn($r) => $r['matricula_activa'] && $r['paz_y_salvo'])),
            'manual' => Configuracion::aprobacionManual(), 'titulo' => 'Aprobaciones',
        ]);
    }

    public function store(Request $request)
    {
        [, $fecha, $jor] = $this->filtros($request);
        switch ($this->campo($request, 'accion')) {
            case 'aprobar':
                [$ok, $m] = Bono::resolver((int)$this->campo($request, 'id'), true);
                flash($ok ? 'ok' : 'error', $m);
                break;
            case 'rechazar':
                [$ok, $m] = Bono::resolver((int)$this->campo($request, 'id'), false, $this->campo($request, 'motivo'));
                flash($ok ? 'ok' : 'error', $m);
                break;
            case 'masivo':
                [$ok, $no] = Bono::aprobarMasivo($fecha, $jor ?: null);
                flash('ok', "$ok solicitudes aprobadas" . ($no ? " · $no no se aprobaron (estudiantes no elegibles; revísalas una por una)." : '.'));
                break;
            case 'modo':
                $manual = $this->campo($request, 'manual', '1') === '1';
                Configuracion::guardar('aprobacion_manual', $manual ? '1' : '0');
                Auditoria::registrar('config_modo_aprobacion', 'configuracion', null, ['manual' => $manual]);
                flash('ok', 'Modo de aprobación actualizado.');
                break;
            case 'demo':
                if (MODO_DEMO && user()['rol'] === 'admin') {
                    $n = 0;
                    foreach (Usuario::estudiantesAleatorios(60) as $id) {
                        [$ok] = Bono::reservar((int)$id, $fecha, $jor ?: (random_int(0, 1) ? 'diurna' : 'nocturna'));
                        $n += $ok ? 1 : 0;
                    }
                    flash('ok', "$n solicitudes de prueba creadas.");
                }
                break;
        }
        return redirect('aprobaciones?' . http_build_query(['fecha' => $fecha, 'jornada' => $jor]));
    }
}
