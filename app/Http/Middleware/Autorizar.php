<?php
namespace App\Http\Middleware;

use App\Models\Bono;
use App\Support\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control de acceso por rol o por permiso (los permisos están en app/Support/constantes.php).
 *   ->middleware(Autorizar::class . ':rol,estudiante')        exige uno de esos roles
 *   ->middleware(Autorizar::class . ':permiso,aprobar')       exige ese permiso
 * Sin sesión manda al inicio de sesión; con sesión pero sin permiso muestra la página 403.
 */
class Autorizar
{
    public function handle(Request $request, Closure $next, string $modo, string ...$args): Response
    {
        $u = Auth::user();
        if (!$u) {
            return redirect('/');
        }
        if ($modo === 'rol') {
            if (!in_array($u['rol'], $args, true)) {
                return redirect('/');
            }
        } elseif (!Auth::puede($args[0])) {
            return self::prohibido();
        }
        Bono::cerrarVencidos();
        return $next($request);
    }

    /** Página 403 "Sin permiso". */
    public static function prohibido(): Response
    {
        $u = Auth::user();
        return response()->view('errores.403', [
            'rol' => ROLES[$u['rol']] ?? $u['rol'],
            'home' => url(Auth::homePara($u['rol'])),
            'titulo' => 'Sin permiso',
        ], 403);
    }
}
