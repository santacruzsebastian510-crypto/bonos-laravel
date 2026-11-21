<?php
namespace App\Http\Controllers;

use App\Http\Middleware\Autorizar;
use App\Models\Configuracion;
use App\Models\Cupo;
use App\Models\Estadistica;
use App\Models\Usuario;
use DateTime;
use Illuminate\Http\Request;

/** Panel del administrador, dividido en sub-pestañas (dashboard, gráficas, categorías, detalle, cupos). */
class AdminController extends Controller
{
    private function pestanas(): array
    {
        $tabs = ['dashboard' => 'Dashboard', 'graficas' => 'Gráficas', 'categorias' => 'Categorías', 'detalle' => 'Detalle'];
        if (puede('gestionar_cupos')) {
            $tabs['cupos'] = 'Cupos';
        }
        return $tabs;
    }

    public function index(Request $request)
    {
        $tabs = $this->pestanas();
        $pedida = (string)$request->query('tab', '');
        $tab = isset($tabs[$pedida]) ? $pedida : 'dashboard';

        [$desde, $hasta, $jor] = Estadistica::periodo($request->query());
        $rows = Estadistica::resumenDiario($desde, $hasta, $jor);
        $sem = Estadistica::resumenSemanal($rows);
        $rc = Estadistica::resumenCategorias($desde, $hasta, $jor);
        [$maxEnt, $minEnt, $maxSob] = Estadistica::extremos($sem);
        [$porFecha, $porJornada] = Estadistica::agrupar($rows);
        $estudiantes = Usuario::conteoActivosPorCategoria();
        $filtro = ['desde' => $desde, 'hasta' => $hasta, 'jornada' => $jor ?? ''];

        $datos = [
            'tabs' => $tabs, 'tab' => $tab, 'desde' => $desde, 'hasta' => $hasta, 'jor' => $jor,
            'rows' => $rows, 'sem' => $sem, 'tot' => Estadistica::totales($rows), 'rc' => $rc, 'cats' => $rc['cat'],
            'maxEnt' => $maxEnt, 'minEnt' => $minEnt, 'maxSob' => $maxSob,
            'porFecha' => $porFecha, 'porJornada' => $porJornada,
            'estudiantes' => $estudiantes, 'totEst' => array_sum($estudiantes),
            'qs' => http_build_query($filtro), 'hoy' => date('Y-m-d'),
            'ayer' => (new DateTime('yesterday'))->format('Y-m-d'),
            'titulo' => 'Administración',
        ];
        if ($tab === 'categorias') {
            $datos['hoyCat'] = Estadistica::hoyPorCategoria();
        }
        if ($tab === 'cupos') {
            $cfg = [];
            foreach (HORARIOS as $j => $info) {
                $cfg[$j] = ['info' => $info, 'total' => Configuracion::cuposDefault($j),
                            'becado' => Configuracion::reservaDefault($j, 'becado'), 'convocatoria' => Configuracion::reservaDefault($j, 'convocatoria')];
            }
            $datos['cfg'] = $cfg;
            $datos['cuposDia'] = Configuracion::cuposDefault('diurna') + Configuracion::cuposDefault('nocturna');
        }
        return view('admin.index', $datos);
    }

    public function store(Request $request)
    {
        if ($this->campo($request, 'accion') === 'cupos') {
            if (!puede('gestionar_cupos')) {
                return Autorizar::prohibido();
            }
            [$ok, $msg] = Cupo::actualizar($request->post());
            flash($ok ? 'ok' : 'error', $msg);
            return redirect('admin?tab=cupos');
        }
        return redirect('admin');
    }
}
