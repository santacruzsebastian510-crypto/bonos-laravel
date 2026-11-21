<?php
namespace App\Models;

use App\Support\Database;
use DateTime;
use PDO;
use PDOException;
use Throwable;

/**
 * Bonos de alimentación y su ciclo de vida:
 *   solicitado → aprobado → entregado   (o no_recogido; también rechazado / cancelado)
 */
class Bono
{
    public const ACTIVOS = "('solicitado','aprobado','entregado','no_recogido')";

    /* ================================================================ Consultas para las pantallas */

    /** Bonos del estudiante de hoy en adelante: [vigente por fecha, último fallido por fecha]. */
    public static function delEstudiante(int $uid): array
    {
        $s = Database::pdo()->prepare('SELECT * FROM bonos WHERE usuario_id = ? AND fecha >= CURDATE() ORDER BY id');
        $s->execute([$uid]);
        $mis = $fallidos = [];
        foreach ($s->fetchAll() as $b) {
            if (in_array($b['estado'], ['rechazado', 'cancelado'], true)) {
                $fallidos[$b['fecha']] = $b;
            } else {
                $mis[$b['fecha']] = $b;
            }
        }
        return [$mis, $fallidos];
    }

    public static function historial(int $uid, int $max = 8): array
    {
        $s = Database::pdo()->prepare('SELECT * FROM bonos WHERE usuario_id = ? AND fecha < CURDATE() ORDER BY fecha DESC, id DESC LIMIT ' . (int)$max);
        $s->execute([$uid]);
        return $s->fetchAll();
    }

    /**
     * Regla "un solo bono activo": el bono solicitado o aprobado que el estudiante todavía puede usar
     * (de hoy en adelante y cuya entrega no ha terminado). Con uno así no puede pedir otro.
     * @return ?array{fecha:string,jornada:string}
     */
    public static function activoVigente(int $uid): ?array
    {
        $s = Database::pdo()->prepare("SELECT fecha, jornada FROM bonos WHERE usuario_id = ? AND estado IN ('solicitado','aprobado') AND fecha >= CURDATE() ORDER BY fecha, id");
        $s->execute([$uid]);
        $ahora = new DateTime('now');
        foreach ($s->fetchAll() as $b) {
            if (Cupo::finEntrega($b['fecha'], $b['jornada']) > $ahora) {
                return $b;
            }
        }
        return null;
    }

    public static function pendientes(string $fecha, ?string $jornada, int $max = 300): array
    {
        $sql = "SELECT b.id, b.jornada, b.creado_en, us.codigo, us.nombre, a.programa, a.matricula_activa, a.paz_y_salvo
                  FROM bonos b JOIN usuarios us ON us.id = b.usuario_id
                  LEFT JOIN academico_estudiantes a ON a.codigo = us.codigo
                 WHERE b.fecha = ? AND b.estado = 'solicitado'" . ($jornada ? ' AND b.jornada = ?' : '') . ' ORDER BY b.id LIMIT ' . (int)$max;
        $s = Database::pdo()->prepare($sql);
        $s->execute($jornada ? [$fecha, $jornada] : [$fecha]);
        return $s->fetchAll();
    }

    public static function contadores(string $fecha): array
    {
        $s = Database::pdo()->prepare("SELECT estado, COUNT(*) FROM bonos WHERE fecha = ? AND estado IN ('solicitado','aprobado','rechazado') GROUP BY estado");
        $s->execute([$fecha]);
        return $s->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function recientesResueltos(string $fecha, int $max = 10): array
    {
        $s = Database::pdo()->prepare("SELECT b.estado, b.motivo_rechazo, b.aprobado_en, us.nombre, ap.nombre AS por
                          FROM bonos b JOIN usuarios us ON us.id = b.usuario_id LEFT JOIN usuarios ap ON ap.id = b.aprobado_por
                         WHERE b.fecha = ? AND b.aprobado_por IS NOT NULL AND b.estado IN ('aprobado','rechazado','entregado','no_recogido')
                         ORDER BY b.aprobado_en DESC LIMIT " . (int)$max);
        $s->execute([$fecha]);
        return $s->fetchAll();
    }

    /** Lista de la jornada de hoy para la pantalla del supervisor. */
    public static function deLaJornada(string $jornada): array
    {
        $s = Database::pdo()->prepare("SELECT b.id, b.codigo AS bono, b.estado, b.categoria, us.codigo, us.nombre
                          FROM bonos b JOIN usuarios us ON us.id = b.usuario_id
                         WHERE b.fecha = CURDATE() AND b.jornada = ? AND b.estado IN ('solicitado','aprobado','entregado','no_recogido')
                         ORDER BY FIELD(b.estado, 'aprobado', 'solicitado', 'entregado', 'no_recogido'), us.nombre");
        $s->execute([$jornada]);
        return $s->fetchAll();
    }

    /** Lista del día para validar sin conexión. */
    public static function listaOffline(): array
    {
        return Database::pdo()->query("SELECT b.codigo AS b, us.codigo AS e, us.nombre AS n, b.jornada AS j, b.estado AS s
                            FROM bonos b JOIN usuarios us ON us.id = b.usuario_id
                           WHERE b.fecha = CURDATE() AND b.estado IN ('solicitado','aprobado','entregado')")->fetchAll();
    }

    /* ================================================================ Ciclo de vida */

    public static function cerrarVencidos(): void
    {
        static $hecho = false;
        if ($hecho) {
            return;
        }
        $hecho = true;
        $pdo = Database::pdo();
        $a = $pdo->exec("UPDATE bonos SET estado = 'rechazado', motivo_rechazo = 'Venció sin aprobación' WHERE fecha < CURDATE() AND estado = 'solicitado'");
        $b = $pdo->exec("UPDATE bonos SET estado = 'no_recogido' WHERE fecha < CURDATE() AND estado = 'aprobado'");
        if ($a || $b) {
            Auditoria::registrar('cierre_automatico', 'bono', null, ['rechazados' => $a, 'no_recogidos' => $b], null, true);
        }
    }

    /** El estudiante solicita su bono. Con aprobación manual queda "solicitado"; si no, se aprueba solo. */
    public static function reservar(int $uid, string $fecha, string $jornada): array
    {
        if (!isset(HORARIOS[$jornada]) || !in_array($fecha, Cupo::diasReservables(), true)) {
            return [false, 'Fecha o jornada no válida.'];
        }
        if (Cupo::limitePasado($fecha, $jornada)) {
            return [false, 'Las solicitudes de esa jornada ya cerraron.'];
        }
        [$ok, $motivo] = Usuario::elegibilidad($uid);
        if (!$ok) {
            Auditoria::registrar('solicitud_bloqueada', 'bono', null, ['fecha' => $fecha, 'jornada' => $jornada, 'motivo' => $motivo], $uid);
            return [false, $motivo];
        }
        $cat = Usuario::categoria($uid);

        $auto = !Configuracion::aprobacionManual();
        $pdo = Database::pdo();
        Cupo::asegurar($fecha, $jornada);
        $pdo->beginTransaction();
        try {
            // Serializa las solicitudes del mismo estudiante (evita pedir hoy y mañana a la vez con dos clics).
            $pdo->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE')->execute([$uid]);
            // Bloquea el día/jornada: con muchos estudiantes a la vez nunca se pasa del total de cupos.
            $e = Cupo::estado($fecha, $jornada, true);

            $s = $pdo->prepare('SELECT 1 FROM bonos WHERE usuario_id = ? AND fecha = ? AND estado IN ' . self::ACTIVOS);
            $s->execute([$uid, $fecha]);
            if ($s->fetchColumn()) {
                $pdo->rollBack();
                return [false, 'Ya tienes un bono para ese día.'];
            }
            // Un solo bono activo a la vez (solicitado/aprobado que aún puede usar).
            if ($otro = self::activoVigente($uid)) {
                $pdo->rollBack();
                return [false, 'Ya tienes un bono activo para el ' . fecha_larga($otro['fecha']) . ' (jornada ' . HORARIOS[$otro['jornada']]['nombre']
                    . '). Solo puedes tener uno a la vez: cancélalo si prefieres pedir otro, o espera a recibirlo.'];
            }
            $abiertos = Cupo::sobrantesAbiertos($fecha, $jornada);
            if (Cupo::libresPara($cat, $e, $abiertos) < 1) {
                $pdo->rollBack();
                $hora = HORARIOS[$jornada]['sobrantes'];
                if (!$abiertos && $cat === 'regular') {
                    return [false, "Los cupos sobrantes para estudiantes regulares se abren a las $hora del " . fecha_larga($fecha) . '.'];
                }
                if (!$abiertos) {
                    return [false, "Ya se usaron los cupos reservados para tu categoría en esa jornada. Si sobran cupos, se abrirán a las $hora."];
                }
                return [false, 'Ya no quedan cupos en esa jornada.'];
            }

            $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            for ($i = 0; $i < 5; $i++) {
                $codigo = '';
                for ($k = 0; $k < 6; $k++) {
                    $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                }
                try {
                    $pdo->prepare('INSERT INTO bonos (usuario_id, fecha, jornada, codigo, categoria, estado, aprobado_en) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$uid, $fecha, $jornada, $codigo, $cat, $auto ? 'aprobado' : 'solicitado', $auto ? date('Y-m-d H:i:s') : null]);
                    $bonoId = (int)$pdo->lastInsertId();
                    Auditoria::registrar($auto ? 'solicitud_aprobada_auto' : 'solicitud_creada', 'bono', $bonoId, ['fecha' => $fecha, 'jornada' => $jornada, 'categoria' => $cat], $uid);
                    $pdo->commit();
                    Notificacion::crear($uid, 'solicitud', $auto ? 'Bono aprobado' : 'Solicitud recibida',
                        $auto ? 'Tu bono del ' . fecha_larga($fecha) . ' quedó aprobado. Código: ' . $codigo
                              : 'Recibimos tu solicitud para el ' . fecha_larga($fecha) . ' (' . HORARIOS[$jornada]['nombre'] . '). Bienestar la revisará.', 's' . $bonoId);
                    return [true, $auto ? $codigo : 'Solicitud enviada. Te avisaremos cuando Bienestar la apruebe.'];
                } catch (PDOException $e2) {
                    if ($e2->getCode() !== '23000') {
                        throw $e2;
                    }
                    if (str_contains($e2->getMessage(), 'uq_usuario_dia')) {
                        $pdo->rollBack();
                        return [false, 'Ya tienes un bono para ese día.'];
                    }
                    // si no, el código del bono se repitió: reintenta con otro
                }
            }
            $pdo->rollBack();
            return [false, 'No se pudo generar el bono. Inténtalo de nuevo.'];
        } catch (Throwable $e3) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e3;
        }
    }

    public static function cancelar(int $uid, int $bonoId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT fecha, jornada FROM bonos WHERE id = ? AND usuario_id = ? AND estado IN ('solicitado','aprobado') FOR UPDATE");
        $s->execute([$bonoId, $uid]);
        $b = $s->fetch();
        if (!$b) {
            $pdo->rollBack();
            return [false, 'No se encontró el bono.'];
        }
        if ($b['fecha'] < date('Y-m-d') || Cupo::limitePasado($b['fecha'], $b['jornada'])) {
            $pdo->rollBack();
            return [false, 'Ya no puedes cancelar este bono.'];
        }
        $pdo->prepare("UPDATE bonos SET estado = 'cancelado', motivo_rechazo = 'Cancelado por el estudiante' WHERE id = ?")->execute([$bonoId]);
        Auditoria::registrar('bono_cancelado', 'bono', $bonoId, ['fecha' => $b['fecha']], $uid);
        $pdo->commit();
        return [true, 'Bono cancelado. El cupo quedó libre para otro estudiante.'];
    }

    /** Bienestar aprueba o rechaza una solicitud pendiente. */
    public static function resolver(int $bonoId, bool $aprobar, string $motivo = ''): array
    {
        $u = user();
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT b.id, b.usuario_id, b.fecha, b.jornada, b.estado FROM bonos b WHERE b.id = ? FOR UPDATE");
        $s->execute([$bonoId]);
        $b = $s->fetch();
        if (!$b || $b['estado'] !== 'solicitado') {
            $pdo->rollBack();
            return [false, 'La solicitud ya fue resuelta.'];
        }
        if ($aprobar) {
            [$ok, $m] = Usuario::elegibilidad((int)$b['usuario_id']);
            if (!$ok) {
                $pdo->rollBack();
                return [false, 'No elegible: ' . $m];
            }
            $pdo->prepare("UPDATE bonos SET estado = 'aprobado', aprobado_por = ?, aprobado_en = NOW() WHERE id = ?")->execute([$u['id'], $bonoId]);
        } else {
            $motivo = mb_substr(trim($motivo), 0, 200) ?: 'Sin motivo indicado';
            $pdo->prepare("UPDATE bonos SET estado = 'rechazado', motivo_rechazo = ?, aprobado_por = ?, aprobado_en = NOW() WHERE id = ?")
                ->execute([$motivo, $u['id'], $bonoId]);
        }
        Auditoria::registrar($aprobar ? 'solicitud_aprobada' : 'solicitud_rechazada', 'bono', $bonoId, $aprobar ? [] : ['motivo' => $motivo]);
        $pdo->commit();

        $cod = $pdo->prepare('SELECT codigo FROM bonos WHERE id = ?');
        $cod->execute([$bonoId]);
        $codigo = $cod->fetchColumn();
        if ($aprobar) {
            Notificacion::crear((int)$b['usuario_id'], 'aprobacion', 'Bono aprobado',
                'Tu bono del ' . fecha_larga($b['fecha']) . ' (' . HORARIOS[$b['jornada']]['nombre'] . ') fue aprobado. Código: ' . $codigo, 'a' . $bonoId);
        } else {
            Notificacion::crear((int)$b['usuario_id'], 'rechazo', 'Solicitud rechazada',
                'Tu solicitud del ' . fecha_larga($b['fecha']) . ' no fue aprobada. Motivo: ' . $motivo, 'r' . $bonoId);
        }
        return [true, $aprobar ? 'Solicitud aprobada.' : 'Solicitud rechazada.'];
    }

    public static function aprobarMasivo(string $fecha, ?string $jornada): array
    {
        $sql = "SELECT id FROM bonos WHERE fecha = ? AND estado = 'solicitado'" . ($jornada ? ' AND jornada = ?' : '') . ' ORDER BY id';
        $s = Database::pdo()->prepare($sql);
        $s->execute($jornada ? [$fecha, $jornada] : [$fecha]);
        $ok = $no = 0;
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) {
            [$r] = self::resolver((int)$id, true);
            $r ? $ok++ : $no++;
        }
        return [$ok, $no];
    }

    /** Cierra la jornada: lo aprobado y no recogido pasa a "no_recogido"; lo que nunca se aprobó, a "rechazado". */
    public static function cerrarJornada(string $jornada): array
    {
        $pdo = Database::pdo();
        $a = $pdo->prepare("UPDATE bonos SET estado = 'no_recogido' WHERE fecha = CURDATE() AND jornada = ? AND estado = 'aprobado'");
        $a->execute([$jornada]);
        $r = $pdo->prepare("UPDATE bonos SET estado = 'rechazado', motivo_rechazo = 'Venció sin aprobación' WHERE fecha = CURDATE() AND jornada = ? AND estado = 'solicitado'");
        $r->execute([$jornada]);
        Auditoria::registrar('jornada_cerrada', 'jornada', null, ['jornada' => $jornada, 'no_recogidos' => $a->rowCount(), 'sin_aprobar' => $r->rowCount()]);
        return [$a->rowCount(), $r->rowCount()];
    }

    /* ================================================================ Entrega (supervisor) */

    /**
     * Canjea un bono por su código (del bono o estudiantil). La unicidad está garantizada en la base de datos:
     * canjes.bono_id es UNIQUE, así que aunque dos supervisores canjeen a la vez, solo uno lo logra.
     * Para canjes hechos sin conexión se pasan $uuid (idempotencia), $fecha y $tsMs (momento real del canje).
     * @return array{ok:bool,estado:string,msg:string,nombre?:string}
     */
    public static function canjear(string $entrada, string $origen = 'online', ?string $uuid = null, ?string $fecha = null, ?int $tsMs = null): array
    {
        $u = user();
        $pdo = Database::pdo();
        $entrada = strtoupper(trim($entrada));
        if ($entrada === '' || strlen($entrada) > 20) {
            return ['ok' => false, 'estado' => 'no_encontrado', 'msg' => 'Escribe un código.'];
        }
        $hoy = date('Y-m-d');
        $fecha = valid_fecha($fecha ?? $hoy) ?: $hoy;
        if ($fecha > $hoy || $fecha < date('Y-m-d', strtotime('-2 days'))) {
            return ['ok' => false, 'estado' => 'fecha', 'msg' => 'El bono es de una fecha fuera del rango permitido.'];
        }
        $canjeadoEn = date('Y-m-d H:i:s');
        if ($origen === 'offline' && $tsMs) {
            $t = (int)($tsMs / 1000);
            if ($t <= time() && $t >= time() - 3 * 86400) {
                $canjeadoEn = date('Y-m-d H:i:s', $t);
            }
        }
        if ($uuid !== null) {
            $s = $pdo->prepare('SELECT 1 FROM canjes WHERE uuid = ?');
            $s->execute([$uuid]);
            if ($s->fetchColumn()) {
                return ['ok' => true, 'estado' => 'ya_sincronizado', 'msg' => 'Ya estaba registrado.'];
            }
        }

        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare("SELECT b.id, b.estado, b.jornada, us.id AS uid, us.nombre
                                  FROM bonos b JOIN usuarios us ON us.id = b.usuario_id
                                 WHERE b.fecha = ? AND (b.codigo = ? OR us.codigo = ?) AND b.estado IN ('solicitado','aprobado','entregado')
                                 LIMIT 1 FOR UPDATE");
            $s->execute([$fecha, $entrada, $entrada]);
            $b = $s->fetch();
            if (!$b) {
                $pdo->rollBack();
                return ['ok' => false, 'estado' => 'no_encontrado', 'msg' => "No hay un bono vigente para “{$entrada}” en esa fecha."];
            }
            if ($b['estado'] === 'solicitado') {
                $pdo->rollBack();
                return ['ok' => false, 'estado' => 'no_aprobado', 'nombre' => $b['nombre'], 'msg' => $b['nombre'] . ': la solicitud aún no ha sido aprobada.'];
            }
            if ($b['estado'] === 'entregado') {
                $pdo->rollBack();
                return ['ok' => false, 'estado' => 'ya_entregado', 'nombre' => $b['nombre'], 'msg' => $b['nombre'] . ' ya recibió este bono' . self::detalleCanje((int)$b['id']) . '.'];
            }
            $pdo->prepare('INSERT INTO canjes (bono_id, validador_id, canjeado_en, origen, uuid) VALUES (?,?,?,?,?)')
                ->execute([$b['id'], $u['id'], $canjeadoEn, $origen, $uuid]);
            $pdo->prepare("UPDATE bonos SET estado = 'entregado', entregado_en = ?, entregado_por = ? WHERE id = ? AND estado = 'aprobado'")
                ->execute([$canjeadoEn, $u['id'], $b['id']]);
            Auditoria::registrar('bono_canjeado', 'bono', (int)$b['id'], ['origen' => $origen, 'canjeado_en' => $canjeadoEn, 'uuid' => $uuid]);
            $pdo->commit();
            Notificacion::crear((int)$b['uid'], 'entrega', 'Bono entregado', 'Recibiste tu bono de la jornada ' . HORARIOS[$b['jornada']]['nombre'] . '. ¡Buen provecho!', 'e' . $b['id']);
            return ['ok' => true, 'estado' => 'ok', 'nombre' => $b['nombre'], 'msg' => 'Entregado a ' . $b['nombre'] . ' (jornada ' . HORARIOS[$b['jornada']]['nombre'] . ').'];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23000') { // carrera: otro supervisor lo canjeó en este instante
                return ['ok' => false, 'estado' => 'ya_entregado', 'msg' => 'Este bono acaba de ser canjeado por otro supervisor.'];
            }
            throw $e;
        }
    }

    private static function detalleCanje(int $bonoId): string
    {
        $s = Database::pdo()->prepare('SELECT c.canjeado_en, v.nombre FROM canjes c JOIN usuarios v ON v.id = c.validador_id WHERE c.bono_id = ?');
        $s->execute([$bonoId]);
        $r = $s->fetch();
        return $r ? ' a las ' . substr($r['canjeado_en'], 11, 5) . ' (validó ' . $r['nombre'] . ')' : '';
    }

    public static function marcarRecibido(int $bonoId): array
    {
        $s = Database::pdo()->prepare('SELECT codigo FROM bonos WHERE id = ? AND fecha = CURDATE()');
        $s->execute([$bonoId]);
        $codigo = $s->fetchColumn();
        if (!$codigo) {
            return [false, 'Ese bono no es de hoy.'];
        }
        $r = self::canjear((string)$codigo);
        return [$r['ok'], $r['msg']];
    }

    public static function marcarNoRecibido(int $bonoId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT usuario_id, estado, fecha FROM bonos WHERE id = ? FOR UPDATE');
        $s->execute([$bonoId]);
        $b = $s->fetch();
        if (!$b || $b['estado'] !== 'aprobado' || $b['fecha'] !== date('Y-m-d')) {
            $pdo->rollBack();
            return [false, 'Este bono no se puede marcar como no recibido.'];
        }
        $pdo->prepare("UPDATE bonos SET estado = 'no_recogido' WHERE id = ?")->execute([$bonoId]);
        Auditoria::registrar('bono_no_recibido', 'bono', $bonoId, []);
        $pdo->commit();
        Notificacion::crear((int)$b['usuario_id'], 'no_recibido', 'Bono marcado como no recibido',
            'Tu bono de hoy fue marcado como no recibido. Si fue un error, acércate al supervisor.', 'n' . $bonoId);
        return [true, 'Marcado como no recibido.'];
    }

    public static function deshacerNoRecibido(int $bonoId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT estado, fecha FROM bonos WHERE id = ? FOR UPDATE');
        $s->execute([$bonoId]);
        $b = $s->fetch();
        if (!$b || $b['estado'] !== 'no_recogido' || $b['fecha'] !== date('Y-m-d')) {
            $pdo->rollBack();
            return [false, 'No se puede deshacer.'];
        }
        $pdo->prepare("UPDATE bonos SET estado = 'aprobado' WHERE id = ?")->execute([$bonoId]);
        Auditoria::registrar('bono_no_recibido_revertido', 'bono', $bonoId, []);
        $pdo->commit();
        return [true, 'Bono devuelto a "por entregar".'];
    }
}
