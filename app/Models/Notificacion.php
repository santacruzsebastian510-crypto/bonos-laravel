<?php
namespace App\Models;

use App\Support\Database;
use DateTime;

/** Notificaciones dentro de la app (campana) y su copia en la cola de correos. */
class Notificacion
{
    public static function crear(int $uid, string $tipo, string $titulo, string $mensaje, ?string $ref = null): void
    {
        $pdo = Database::pdo();
        $s = $pdo->prepare('INSERT IGNORE INTO notificaciones (usuario_id, tipo, titulo, mensaje, ref) VALUES (?,?,?,?,?)');
        $s->execute([$uid, $tipo, $titulo, $mensaje, $ref]);
        if ($s->rowCount() === 0 || MAIL_MODO === 'off') {
            return;
        }
        $e = $pdo->prepare('SELECT email FROM usuarios WHERE id = ?');
        $e->execute([$uid]);
        if ($mail = $e->fetchColumn()) {
            $pdo->prepare('INSERT INTO cola_correos (usuario_id, para, asunto, cuerpo) VALUES (?,?,?,?)')
                ->execute([$uid, $mail, $titulo, $mensaje]);
        }
    }

    public static function noLeidas(int $uid): int
    {
        $s = Database::pdo()->prepare('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0');
        $s->execute([$uid]);
        return (int)$s->fetchColumn();
    }

    public static function ultimoId(int $uid): int
    {
        $s = Database::pdo()->prepare('SELECT COALESCE(MAX(id),0) FROM notificaciones WHERE usuario_id = ?');
        $s->execute([$uid]);
        return (int)$s->fetchColumn();
    }

    public static function nuevasDesde(int $uid, int $desde): array
    {
        $s = Database::pdo()->prepare('SELECT id, titulo, mensaje FROM notificaciones WHERE usuario_id = ? AND id > ? ORDER BY id LIMIT 10');
        $s->execute([$uid, $desde]);
        return $s->fetchAll();
    }

    public static function recientes(int $uid, int $max = 50): array
    {
        $s = Database::pdo()->prepare('SELECT id, tipo, titulo, mensaje, leida, creado_en FROM notificaciones WHERE usuario_id = ? ORDER BY id DESC LIMIT ' . (int)$max);
        $s->execute([$uid]);
        return $s->fetchAll();
    }

    public static function marcarLeidas(int $uid): void
    {
        Database::pdo()->prepare('UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0')->execute([$uid]);
    }

    /** Aviso "tu bono vence pronto": 90 minutos antes de que termine la entrega de su jornada. */
    public static function recordatorioVencimiento(int $uid): void
    {
        $s = Database::pdo()->prepare("SELECT id, jornada FROM bonos WHERE usuario_id = ? AND fecha = CURDATE() AND estado = 'aprobado'");
        $s->execute([$uid]);
        foreach ($s->fetchAll() as $b) {
            $fin = trim(explode('–', HORARIOS[$b['jornada']]['entrega'])[1] ?? '');
            if (!$fin) {
                continue;
            }
            $limite = new DateTime(date('Y-m-d') . ' ' . $fin);
            $ahora = new DateTime('now');
            if ($ahora < $limite && $ahora >= (clone $limite)->modify('-90 minutes')) {
                self::crear($uid, 'recordatorio', 'Tu bono vence pronto',
                    'La entrega de la jornada ' . HORARIOS[$b['jornada']]['nombre'] . ' termina a las ' . $fin . '. Recógelo a tiempo.', 'b' . $b['id']);
            }
        }
    }
}
