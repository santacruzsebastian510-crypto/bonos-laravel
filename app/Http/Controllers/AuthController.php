<?php
namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use App\Support\Auth;
use Illuminate\Http\Request;

/** Inicio y cierre de sesión. */
class AuthController extends Controller
{
    public function mostrar(string $error = '')
    {
        if ($u = Auth::user()) {
            return redirect(Auth::homePara($u['rol']));
        }
        return view('auth.login', ['error' => $error, 'titulo' => 'Iniciar sesión']);
    }

    public function procesar(Request $request)
    {
        if ($u = Auth::user()) {
            return redirect(Auth::homePara($u['rol']));
        }
        // Freno básico a la fuerza bruta: 5 intentos fallidos => 5 minutos de espera.
        if ((int)session('bloqueo_hasta', 0) > time()) {
            return $this->mostrar('Demasiados intentos. Espera unos minutos e inténtalo de nuevo.');
        }
        $codigo = trim($this->campo($request, 'codigo'));
        $pass = $this->campo($request, 'password');
        $fila = Usuario::activoPorCodigo($codigo);

        if ($fila && password_verify($pass, $fila['password_hash'])) {
            Auth::iniciarSesion($fila);
            Auditoria::registrar('login', 'usuario', (int)$fila['id'], []);
            return redirect(Auth::homePara($fila['rol']));
        }
        $intentos = (int)session('intentos', 0) + 1;
        session(['intentos' => $intentos]);
        if ($intentos >= 5) {
            session(['bloqueo_hasta' => time() + 300, 'intentos' => 0]);
            Auditoria::registrar('login_bloqueado', 'usuario', null, ['codigo' => $codigo], null, true);
        }
        return $this->mostrar('Código o contraseña incorrectos.');
    }

    public function salir()
    {
        if ($u = Auth::user()) {
            Auditoria::registrar('logout', 'usuario', $u['id'], []);
        }
        Auth::cerrarSesion();
        return redirect('/');
    }
}
