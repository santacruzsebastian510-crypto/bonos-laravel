<?php
namespace App\Http\Controllers;

use App\Models\Notificacion;

/** Bandeja de notificaciones del usuario. */
class NotificacionesController extends Controller
{
    public function index()
    {
        $uid = user()['id'];
        // Se dibuja primero (la campana aún muestra las no leídas) y después se marcan como leídas.
        $html = view('notificaciones.index', ['lista' => Notificacion::recientes($uid), 'titulo' => 'Notificaciones'])->render();
        Notificacion::marcarLeidas($uid);
        return response($html);
    }
}
