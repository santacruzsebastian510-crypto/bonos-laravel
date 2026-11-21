<?php
/* Funciones de ayuda globales, pensadas sobre todo para las vistas. No tocan la base de datos. */

if (!function_exists('h')) {
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
}

/* ---- Sesión y permisos (atajos a App\Support\Auth) ---- */
if (!function_exists('user')) {
function user(): ?array
{
    return \App\Support\Auth::user();
}
}

if (!function_exists('puede')) {
function puede(string $permiso): bool
{
    return \App\Support\Auth::puede($permiso);
}
}

/* ---- Mensajes flash (sesión de Laravel). El CSRF lo maneja Laravel: csrf_field() / csrf_token() ---- */
if (!function_exists('flash')) {
function flash(string $tipo, string $msg): void
{
    session()->flash('flash', [$tipo, $msg]);
}
}

/* ---- Fechas ---- */
if (!function_exists('valid_fecha')) {
function valid_fecha($f): ?string
{
    if (!is_string($f) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m)) {
        return null;
    }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $f : null;
}
}

if (!function_exists('fecha_larga')) {
function fecha_larga(string $f): string
{
    $d = new DateTime($f);
    return DIAS[(int)$d->format('N')] . ' ' . $d->format('j') . ' de ' . MESES[(int)$d->format('n')];
}
}

if (!function_exists('dia_semana')) {
function dia_semana(string $f): string
{
    return DIAS[(int)(new DateTime($f))->format('N')];
}
}

/* ---- Presentación ---- */
if (!function_exists('etiqueta_estado')) {
function etiqueta_estado(string $estado, string $fecha): array
{
    $pasado = $fecha < date('Y-m-d');
    switch ($estado) {
        case 'solicitado':   return $pasado ? ['Vencido', 'warn'] : ['En revisión', 'info'];
        case 'aprobado':     return $pasado ? ['No recogido', 'warn'] : ['Aprobado', 'ok'];
        case 'entregado':    return ['Entregado', 'ok'];
        case 'no_recogido':  return ['No recogido', 'warn'];
        case 'rechazado':    return ['Rechazado', 'err'];
        case 'cancelado':    return ['Cancelado', 'muted'];
    }
    return [ucfirst($estado), 'info'];
}
}

if (!function_exists('etiqueta_categoria')) {
function etiqueta_categoria(string $cat): array
{
    return [CATEGORIA_UNO[$cat] ?? ucfirst($cat), ['becado' => 'ok', 'convocatoria' => 'info', 'regular' => 'muted'][$cat] ?? 'muted'];
}
}

/** Línea de tiempo del bono: Solicitado → Aprobado → Entregado. */
if (!function_exists('linea_tiempo')) {
function linea_tiempo(string $estado): string
{
    $pasos = ['solicitado' => 'Solicitado', 'aprobado' => 'Aprobado', 'entregado' => 'Entregado'];
    $nivel = ['solicitado' => 1, 'aprobado' => 2, 'entregado' => 3, 'no_recogido' => 2][$estado] ?? 1;
    $html = '<ol class="steps">';
    $i = 0;
    foreach ($pasos as $txt) {
        $i++;
        $html .= '<li class="' . ($i < $nivel ? 'done' : ($i === $nivel ? 'now' : '')) . '"><span></span>' . h($txt) . '</li>';
    }
    return $html . '</ol>';
}
}
