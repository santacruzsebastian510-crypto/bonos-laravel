<?php
namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Correo;

/** Cola de correos (los avisos que se mandan al correo institucional). */
class CorreosController extends Controller
{
    public function index()
    {
        return view('correos.index', ['cont' => Correo::conteos(), 'filas' => Correo::recientes(), 'titulo' => 'Correos']);
    }

    public function store()
    {
        if (MAIL_MODO === 'mail') {
            [$ok, $err] = Correo::procesarCola();
            Auditoria::registrar('correos_enviados', 'correo', null, ['enviados' => $ok, 'errores' => $err]);
            flash($err ? 'error' : 'ok', "$ok correos enviados" . ($err ? ", $err con error." : '.'));
        } else {
            flash('error', 'El envío está desactivado (MAIL_MODO = ' . MAIL_MODO . ' en app/Support/constantes.php).');
        }
        return redirect('correos');
    }
}
