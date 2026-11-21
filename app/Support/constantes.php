<?php
/* Constantes del sistema (antes estaban en config.php). La conexión a la base de datos ahora vive en .env */

const DIAS  = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
const MESES = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
               7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];

// 'limite'    = hora hasta la que el estudiante puede solicitar o cancelar su bono ese mismo día.
// 'sobrantes' = hora a partir de la cual los cupos reservados que nadie pidió se abren a los estudiantes regulares.
const HORARIOS = [
    'diurna'   => ['nombre' => 'Diurna',   'entrega' => '11:30 – 14:00', 'limite' => '10:00', 'sobrantes' => '08:00'],
    'nocturna' => ['nombre' => 'Nocturna', 'entrega' => '18:00 – 20:30', 'limite' => '16:00', 'sobrantes' => '14:00'],
];
const CAPACIDAD_MINIMA = 200;

const ROLES = [
    'estudiante' => 'Estudiante',
    'bienestar'  => 'Funcionario de Bienestar',
    'supervisor' => 'Supervisor de comedor',
    'auditor'    => 'Auditor',
    'admin'      => 'Administrador',
];
// permiso => roles que lo tienen
const PERMISOS = [
    'solicitar'          => ['estudiante'],
    'aprobar'            => ['bienestar', 'admin'],
    'canjear'            => ['supervisor', 'admin'],
    'cerrar_jornada'     => ['bienestar', 'admin'],
    'ver_stats'          => ['bienestar', 'auditor', 'admin'],
    'gestionar_cupos'    => ['bienestar', 'admin'],
    'ver_auditoria'      => ['auditor', 'admin'],
    'gestionar_usuarios' => ['admin'],
];

// 'off' = no genera correos · 'cola' = los guarda en cola_correos · 'mail' = además los envía con mail()
const MAIL_MODO = 'cola';
const MAIL_REMITENTE = 'no-reply@bonos.local';

// Muestra el botón "Generar solicitudes de prueba" en Aprobaciones (apágalo en producción).
const MODO_DEMO = true;

const CATEGORIAS    = ['becado' => 'Becados', 'convocatoria' => 'Convocatoria', 'regular' => 'Regulares (sobrantes)'];
const CATEGORIA_UNO = ['becado' => 'Becado', 'convocatoria' => 'Convocatoria', 'regular' => 'Regular'];
