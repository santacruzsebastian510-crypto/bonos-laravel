<?php
namespace App\Providers;

use App\Models\Notificacion;
use App\Support\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Constantes del sistema (horarios, roles, permisos…) y funciones de ayuda para las vistas.
        require_once app_path('Support/constantes.php');
        require_once app_path('Support/helpers.php');
    }

    public function boot(): void
    {
        // Datos que necesita el diseño común (menú, campana de notificaciones, mensaje flash).
        View::composer('layout.main', function ($view) {
            $u = Auth::user();
            $view->with([
                'u'        => $u,
                'noLeidas' => $u ? Notificacion::noLeidas($u['id']) : 0,
                'ultimoId' => $u ? Notificacion::ultimoId($u['id']) : 0,
                'actual'   => request()->path(),
                'flash'    => session('flash'),
            ]);
        });
    }
}
