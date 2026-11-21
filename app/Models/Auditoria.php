<?php
namespace App\Models;

use App\Support\Database;
use PDO;
use Throwable;

/** Registro de auditoría con cadena de hashes (cada fila incluye el hash de la anterior). */
class Auditoria
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    private static function payload($prev, $ts, $actorId, $rol, $accion, $entidad, $entidadId, $detalle): string
    {
        return implode('|', [$prev, $ts, $actorId ?? '', $rol, $accion, $entidad, $entidadId ?? '', $detalle]);
    }

    /**
     * Registra una acción. Si ya hay una transacción abierta se une a ella
     * (así la acción y su registro quedan guardados juntos o ninguno).
     */
    public static function registrar(string $accion, string $entidad = '', ?int $entidadId = null, array $detalle = [], ?int $actorId = null, bool $sistema = false): void
    {
        $pdo = Database::pdo();
        $u = user();
        $rol = $sistema ? 'sistema' : ($u['rol'] ?? 'sistema');
        if ($actorId === null && !$sistema) {
            $actorId = $u['id'] ?? null;
        }
        $propia = !$pdo->inTransaction();
        if ($propia) {
            $pdo->beginTransaction();
        }
        try {
            $prev = (string)$pdo->query('SELECT ultimo_hash FROM auditoria_cadena WHERE id = 1 FOR UPDATE')->fetchColumn();
            $ts = date('Y-m-d H:i:s');
            $det = json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $hash = hash('sha256', self::payload($prev, $ts, $actorId, $rol, $accion, $entidad, $entidadId, $det));
            $pdo->prepare('INSERT INTO auditoria (fecha_hora, usuario_id, rol, accion, entidad, entidad_id, detalle, ip, hash_prev, hash)
                           VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$ts, $actorId, $rol, $accion, $entidad, $entidadId, $det, $_SERVER['REMOTE_ADDR'] ?? null, $prev, $hash]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE auditoria_cadena SET ultimo_hash = ?, ultimo_id = ? WHERE id = 1')->execute([$hash, $id]);
            if ($propia) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($propia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array{0:bool,1:int,2:?int} [íntegra, filas verificadas, id de la primera fila alterada] */
    public static function verificarCadena(): array
    {
        $pdo = Database::pdo();
        $prev = self::GENESIS;
        $n = 0;
        foreach ($pdo->query('SELECT * FROM auditoria ORDER BY id') as $r) {
            $esp = hash('sha256', self::payload($prev, $r['fecha_hora'], $r['usuario_id'], $r['rol'], $r['accion'], $r['entidad'], $r['entidad_id'], $r['detalle']));
            if ($r['hash_prev'] !== $prev || $r['hash'] !== $esp) {
                return [false, $n, (int)$r['id']];
            }
            $prev = $r['hash'];
            $n++;
        }
        $ult = (string)$pdo->query('SELECT ultimo_hash FROM auditoria_cadena WHERE id = 1')->fetchColumn();
        return [$ult === $prev, $n, $ult === $prev ? null : (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM auditoria')->fetchColumn()];
    }

    /* ---- Consultas para la pantalla de auditoría ---- */
    private static function condiciones(array $f): array
    {
        $where = [];
        $par = [];
        if (($f['accion'] ?? '') !== '') { $where[] = 'a.accion = ?'; $par[] = $f['accion']; }
        if (($f['actor'] ?? '') !== '')  { $where[] = '(us.codigo = ? OR us.nombre LIKE ?)'; $par[] = $f['actor']; $par[] = '%' . $f['actor'] . '%'; }
        if (!empty($f['desde']))         { $where[] = 'a.fecha_hora >= ?'; $par[] = $f['desde'] . ' 00:00:00'; }
        if (!empty($f['hasta']))         { $where[] = 'a.fecha_hora <= ?'; $par[] = $f['hasta'] . ' 23:59:59'; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return ["FROM auditoria a LEFT JOIN usuarios us ON us.id = a.usuario_id $w", $par];
    }

    public static function contar(array $f): int
    {
        [$base, $par] = self::condiciones($f);
        $s = Database::pdo()->prepare("SELECT COUNT(*) $base");
        $s->execute($par);
        return (int)$s->fetchColumn();
    }

    public static function pagina(array $f, int $pag, int $por): array
    {
        [$base, $par] = self::condiciones($f);
        $s = Database::pdo()->prepare("SELECT a.*, us.codigo AS actor_codigo, us.nombre AS actor_nombre $base ORDER BY a.id DESC LIMIT $por OFFSET " . (($pag - 1) * $por));
        $s->execute($par);
        return $s->fetchAll();
    }

    /** Para exportar a CSV (iterable de filas). */
    public static function paraCsv(array $f): \PDOStatement
    {
        [$base, $par] = self::condiciones($f);
        $s = Database::pdo()->prepare("SELECT a.id, a.fecha_hora, COALESCE(us.codigo,'sistema') AS actor, a.rol, a.accion, a.entidad, a.entidad_id, a.detalle, a.ip, a.hash $base ORDER BY a.id DESC LIMIT 50000");
        $s->execute($par);
        return $s;
    }

    public static function acciones(): array
    {
        return Database::pdo()->query('SELECT DISTINCT accion FROM auditoria ORDER BY accion')->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function ultimo(): array
    {
        return Database::pdo()->query('SELECT ultimo_hash, ultimo_id FROM auditoria_cadena WHERE id = 1')->fetch();
    }
}
