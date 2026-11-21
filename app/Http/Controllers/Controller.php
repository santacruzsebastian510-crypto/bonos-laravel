<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Campo de formulario como texto (vacío si no viene). Reemplaza al antiguo $this->post(). */
    protected function campo(Request $r, string $clave, string $def = ''): string
    {
        return (string)($r->input($clave) ?? $def);
    }
}
