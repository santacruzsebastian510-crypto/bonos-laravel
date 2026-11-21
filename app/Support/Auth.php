<?php
namespace App\Support;

/** Sesión del usuario (guardada en la sesión de Laravel) y matriz de permisos (ROLES y PERMISOS: constantes.php). */
class Auth
{
    public static function user(): ?array
    {
        return session('user');
    }

    public static function puede(string $permiso): bool
    {
        $u = self::user();
        return $u && in_array($u['rol'], PERMISOS[$permiso] ?? [], true);
    }

    /** Ruta de inicio de cada rol (relativa, se usa con url() o redirect()). */
    public static function homePara(string $rol): string
    {
        return ['estudiante' => 'estudiante', 'bienestar' => 'aprobaciones', 'supervisor' => 'validar',
                'auditor' => 'auditoria', 'admin' => 'admin'][$rol] ?? '/';
    }

    public static function iniciarSesion(array $fila): void
    {
        request()->session()->regenerate();
        session(['user' => ['id' => (int)$fila['id'], 'codigo' => $fila['codigo'], 'nombre' => $fila['nombre'], 'rol' => $fila['rol']]]);
        session()->forget(['intentos', 'bloqueo_hasta']);
    }

    public static function cerrarSesion(): void
    {
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
