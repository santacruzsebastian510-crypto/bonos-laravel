<?php
namespace App\Http\Controllers;

use App\Models\Bono;
use App\Models\Estadistica;
use App\Models\Notificacion;
use App\Support\Auth;
use Illuminate\Http\Request;

/**
 * Respuestas JSON para el navegador: campana, panel en vivo y entrega (en línea y sincronización sin conexión).
 * Las peticiones POST llevan el token CSRF en la cabecera X-CSRF-TOKEN (Laravel lo valida solo).
 */
class ApiController extends Controller
{
    public function __invoke(Request $request)
    {
        $u = Auth::user();
        if (!$u) {
            return $this->salida(['error' => 'sesion'], 401);
        }
        $esPost = $request->isMethod('POST');
        $body = $esPost ? (json_decode($request->getContent(), true) ?: []) : [];

        switch ((string)$request->query('accion', '')) {
            case 'notif':
                $desde = (int)$request->query('desde', 0);
                return $this->salida(['no_leidas' => Notificacion::noLeidas($u['id']), 'nuevas' => Notificacion::nuevasDesde($u['id'], $desde)]);

            case 'en_vivo':
                if (!puede('ver_stats')) return $this->salida(['error' => 'permiso'], 403);
                return $this->salida(['hora' => date('H:i:s'), 'jornadas' => Estadistica::enVivo()]);

            case 'lista_dia': // lista del día para trabajar sin conexión
                if (!puede('canjear')) return $this->salida(['error' => 'permiso'], 403);
                return $this->salida(['fecha' => date('Y-m-d'), 'generada' => date('c'), 'items' => Bono::listaOffline()]);

            case 'canjear': // canje en línea
                if (!puede('canjear')) return $this->salida(['error' => 'permiso'], 403);
                if (!$esPost) return $this->salida(['error' => 'metodo'], 405);
                return $this->salida(Bono::canjear((string)($body['codigo'] ?? ''), 'online', isset($body['uuid']) ? substr((string)$body['uuid'], 0, 36) : null));

            case 'sync': // canjes hechos sin conexión (idempotente por uuid)
                if (!puede('canjear')) return $this->salida(['error' => 'permiso'], 403);
                if (!$esPost) return $this->salida(['error' => 'metodo'], 405);
                $res = [];
                foreach (array_slice((array)($body['items'] ?? []), 0, 200) as $it) {
                    $uuid = substr((string)($it['uuid'] ?? ''), 0, 36);
                    $r = Bono::canjear((string)($it['codigo'] ?? ''), 'offline', $uuid ?: null, (string)($it['fecha'] ?? ''), isset($it['ts']) ? (int)$it['ts'] : null);
                    $res[] = ['uuid' => $uuid, 'estado' => $r['ok'] ? 'ok' : 'conflicto', 'detalle' => $r['estado'], 'msg' => $r['msg']];
                }
                return $this->salida(['resultados' => $res]);
        }
        return $this->salida(['error' => 'accion'], 400);
    }

    private function salida($data, int $code = 200)
    {
        return response()->json($data, $code, ['Cache-Control' => 'no-store'], JSON_UNESCAPED_UNICODE);
    }
}
