<?php
namespace App\Http\Controllers;

use App\Models\Bono;
use App\Models\Cupo;
use Illuminate\Http\Request;

/** Pantalla del supervisor: entregar bonos (recibido / no recibido). */
class EntregaController extends Controller
{
    private function jornada(Request $r): string
    {
        $j = $this->campo($r, 'jornada', date('H') < 16 ? 'diurna' : 'nocturna');
        return isset(HORARIOS[$j]) ? $j : 'diurna';
    }

    public function index(Request $request)
    {
        $jornada = $this->jornada($request);
        $lista = Bono::deLaJornada($jornada);
        $porCat = [];
        foreach ($lista as $r) {
            $porCat[$r['categoria']][$r['estado']] = ($porCat[$r['categoria']][$r['estado']] ?? 0) + 1;
        }
        return view('entrega.index', [
            'hoy' => date('Y-m-d'), 'jornada' => $jornada, 'lista' => $lista, 'porCat' => $porCat,
            'cuenta' => array_count_values(array_column($lista, 'estado')),
            'd' => Cupo::disponibilidad(date('Y-m-d'), $jornada), 'titulo' => 'Entrega del día',
        ]);
    }

    public function store(Request $request)
    {
        $jornada = $this->jornada($request);
        $accion = $this->campo($request, 'accion');
        if ($accion === 'canjear') { // alternativa sin JavaScript
            $r = Bono::canjear($this->campo($request, 'codigo'));
            flash($r['ok'] ? 'ok' : 'error', $r['msg']);
        } elseif (in_array($accion, ['recibido', 'no_recibido', 'deshacer'], true)) {
            $id = (int)$this->campo($request, 'id');
            [$ok, $m] = $accion === 'recibido' ? Bono::marcarRecibido($id)
                : ($accion === 'no_recibido' ? Bono::marcarNoRecibido($id) : Bono::deshacerNoRecibido($id));
            flash($ok ? 'ok' : 'error', $m);
        } elseif ($accion === 'cerrar' && puede('cerrar_jornada')) {
            [$nr, $sa] = Bono::cerrarJornada($jornada);
            flash('ok', "Jornada cerrada: $nr bonos marcados como no recogidos" . ($sa ? " y $sa solicitudes sin aprobar." : '.'));
        }
        return redirect('validar?jornada=' . $jornada);
    }
}
