<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AprobacionesController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CorreosController;
use App\Http\Controllers\EntregaController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\ExportarController;
use App\Http\Controllers\NotificacionesController;
use App\Http\Controllers\UsuariosController;
use App\Http\Middleware\Autorizar;
use Illuminate\Support\Facades\Route;

// ---- Públicas ----
Route::get('/', [AuthController::class, 'mostrar'])->name('login');
Route::post('/', [AuthController::class, 'procesar']);
Route::get('/logout', [AuthController::class, 'salir'])->name('logout');

// ---- Estudiante ----
Route::middleware(Autorizar::class . ':rol,estudiante')->group(function () {
    Route::get('/estudiante', [EstudianteController::class, 'index'])->name('estudiante');
    Route::post('/estudiante', [EstudianteController::class, 'store']);
});

// ---- Bienestar: aprobaciones ----
Route::middleware(Autorizar::class . ':permiso,aprobar')->group(function () {
    Route::get('/aprobaciones', [AprobacionesController::class, 'index'])->name('aprobaciones');
    Route::post('/aprobaciones', [AprobacionesController::class, 'store']);
});

// ---- Supervisor: entrega de bonos ----
Route::middleware(Autorizar::class . ':permiso,canjear')->group(function () {
    Route::get('/validar', [EntregaController::class, 'index'])->name('validar');
    Route::post('/validar', [EntregaController::class, 'store']);
});

// ---- Estadísticas y exportación ----
Route::middleware(Autorizar::class . ':permiso,ver_stats')->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin');
    Route::post('/admin', [AdminController::class, 'store']);
    Route::get('/exportar', [ExportarController::class, 'index'])->name('exportar');
});

// ---- Auditoría ----
Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria')
    ->middleware(Autorizar::class . ':permiso,ver_auditoria');

// ---- Administrador: usuarios y correos ----
Route::middleware(Autorizar::class . ':permiso,gestionar_usuarios')->group(function () {
    Route::get('/usuarios', [UsuariosController::class, 'index'])->name('usuarios');
    Route::post('/usuarios', [UsuariosController::class, 'store']);
    Route::get('/correos', [CorreosController::class, 'index'])->name('correos');
    Route::post('/correos', [CorreosController::class, 'store']);
});

// ---- Notificaciones (cualquier usuario con sesión) ----
Route::get('/notificaciones', [NotificacionesController::class, 'index'])->name('notificaciones')
    ->middleware(Autorizar::class . ':rol,estudiante,bienestar,supervisor,auditor,admin');

// ---- API JSON (campana, panel en vivo, entrega y sincronización sin conexión) ----
Route::match(['get', 'post'], '/api', ApiController::class)->name('api');
