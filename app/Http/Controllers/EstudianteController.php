<?php
namespace App\Http\Controllers;

use App\Models\Bono;
use App\Models\Cupo;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Http\Request;

/** "Mi bono": el estudiante solicita, ve y cancela su bono. */
class EstudianteController extends Controller
{
    public function index()
    {
        $u = user();
        $uid = $u['id'];
        Notificacion::recordatorioVencimiento($uid);
        [$elegible, $motivoNoElegible] = Usuario::elegibilidad($uid);
        $categoria = Usuario::categoria($uid);
        [$mis, $fallidos] = Bono::delEstudiante($uid);
        $activo = Bono::activoVigente($uid);   // regla: un solo bono activo a la vez

        $dias = [];
        foreach (Cupo::diasReservables() as $fecha) {
            $mio = $mis[$fecha] ?? null;
            $jornadas = [];
            if (!$mio) {
                foreach (HORARIOS as $j => $info) {
                    $e = Cupo::estado($fecha, $j);
                    $abiertos = Cupo::sobrantesAbiertos($fecha, $j);
                    $jornadas[$j] = [
                        'info'     => $info,
                        'mios'     => Cupo::libresPara($categoria, $e, $abiertos),
                        'cupos'    => $e['cupos'],
                        'usados'   => $e['total'],
                        'libres'   => max(0, $e['cupos'] - $e['total']),
                        'abiertos' => $abiertos,
                        'cerrada'  => Cupo::limitePasado($fecha, $j),
                    ];
                }
            }
            $dias[] = [
                'fecha'         => $fecha,
                'mio'           => $mio,
                'puedeCancelar' => $mio && in_array($mio['estado'], ['solicitado', 'aprobado'], true) && !Cupo::limitePasado($mio['fecha'], $mio['jornada']),
                'fallido'       => $fallidos[$fecha] ?? null,
                'jornadas'      => $jornadas,
            ];
        }

        return view('estudiante.index', [
            'u' => $u, 'elegible' => $elegible, 'motivoNoElegible' => $motivoNoElegible,
            'categoria' => $categoria, 'activo' => $activo, 'dias' => $dias,
            'historial' => Bono::historial($uid), 'titulo' => 'Mi bono',
        ]);
    }

    public function store(Request $request)
    {
        $uid = user()['id'];
        $accion = $this->campo($request, 'accion');
        if ($accion === 'reservar') {
            [$ok, $r] = Bono::reservar($uid, $this->campo($request, 'fecha'), $this->campo($request, 'jornada'));
            flash($ok ? 'ok' : 'error', $ok ? (strlen($r) === 6 ? "¡Listo! Tu bono quedó aprobado. Código: $r" : $r) : $r);
        } elseif ($accion === 'cancelar') {
            [$ok, $r] = Bono::cancelar($uid, (int)$this->campo($request, 'id'));
            flash($ok ? 'ok' : 'error', $r);
        }
        return redirect('estudiante');
    }
}
