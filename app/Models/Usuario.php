<?php
namespace App\Models;

use App\Support\Database;
use PDO;

/** Usuarios, categorías y elegibilidad académica. */
class Usuario
{
    public static function activoPorCodigo(string $codigo): ?array
    {
        $s = Database::pdo()->prepare('SELECT id, codigo, nombre, rol, password_hash FROM usuarios WHERE codigo = ? AND activo = 1');
        $s->execute([$codigo]);
        return $s->fetch() ?: null;
    }

    public static function categoria(int $uid): string
    {
        $c = Database::pdo()->prepare('SELECT categoria FROM usuarios WHERE id = ?');
        $c->execute([$uid]);
        return (string)($c->fetchColumn() ?: 'regular');
    }

    /**
     * Elegibilidad del estudiante: matrícula activa y paz y salvo financiero.
     * Hoy lee la tabla academico_estudiantes (se carga desde Registro Académico). Para conectarlo con el
     * sistema real basta reemplazar el cuerpo de esta función por la consulta a su API o vista.
     * @return array{0:bool,1:string} [elegible, motivo si no lo es]
     */
    public static function elegibilidad(int $uid): array
    {
        $s = Database::pdo()->prepare('SELECT a.matricula_activa, a.paz_y_salvo
                              FROM usuarios u LEFT JOIN academico_estudiantes a ON a.codigo = u.codigo WHERE u.id = ?');
        $s->execute([$uid]);
        $r = $s->fetch();
        if (!$r || $r['matricula_activa'] === null) {
            return [false, 'No apareces en el sistema académico. Comunícate con Registro Académico.'];
        }
        if (!(int)$r['matricula_activa']) {
            return [false, 'Tu matrícula no está activa en este periodo.'];
        }
        if (!(int)$r['paz_y_salvo']) {
            return [false, 'Tienes una novedad financiera pendiente. Comunícate con Tesorería.'];
        }
        return [true, ''];
    }

    public static function crear(string $codigo, string $nombre, string $email, string $pass, string $rol, string $cat): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO usuarios (codigo, nombre, email, password_hash, rol, categoria) VALUES (?,?,?,?,?,?)')
            ->execute([$codigo, $nombre, $email ?: null, password_hash($pass, PASSWORD_BCRYPT), $rol, $cat]);
        return (int)$pdo->lastInsertId();
    }

    public static function buscar(int $id): ?array
    {
        $s = Database::pdo()->prepare('SELECT codigo, rol, activo, categoria FROM usuarios WHERE id = ?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public static function actualizar(int $id, string $rol, int $activo, string $cat): void
    {
        Database::pdo()->prepare('UPDATE usuarios SET rol = ?, activo = ?, categoria = ? WHERE id = ?')->execute([$rol, $activo, $cat, $id]);
    }

    public static function cambiarPassword(int $id, string $pass): void
    {
        Database::pdo()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_BCRYPT), $id]);
    }

    /** Pasa a los estudiantes con esos códigos a la categoría dada. Devuelve cuántos cambiaron. */
    public static function cargarLista(string $cat, array $codigos): int
    {
        $n = 0;
        foreach (array_chunk($codigos, 500) as $parte) {
            $in = implode(',', array_fill(0, count($parte), '?'));
            $st = Database::pdo()->prepare("UPDATE usuarios SET categoria = ? WHERE rol = 'estudiante' AND codigo IN ($in)");
            $st->execute(array_merge([$cat], $parte));
            $n += $st->rowCount();
        }
        return $n;
    }

    public static function listar(string $q, string $rol, int $max = 100): array
    {
        $where = [];
        $par = [];
        if ($q !== '') { $where[] = '(codigo LIKE ? OR nombre LIKE ?)'; $par[] = "%$q%"; $par[] = "%$q%"; }
        if ($rol)      { $where[] = 'rol = ?'; $par[] = $rol; }
        $s = Database::pdo()->prepare('SELECT id, codigo, nombre, email, rol, categoria, activo FROM usuarios ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY FIELD(rol,'admin','bienestar','supervisor','auditor','estudiante'), codigo LIMIT " . (int)$max);
        $s->execute($par);
        return $s->fetchAll();
    }

    public static function conteoPorRol(): array
    {
        return Database::pdo()->query('SELECT rol, COUNT(*) FROM usuarios GROUP BY rol')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function conteoPorCategoria(): array
    {
        return Database::pdo()->query("SELECT categoria, COUNT(*) FROM usuarios WHERE rol = 'estudiante' GROUP BY categoria")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function conteoActivosPorCategoria(): array
    {
        return Database::pdo()->query("SELECT categoria, COUNT(*) FROM usuarios WHERE rol = 'estudiante' AND activo = 1 GROUP BY categoria")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function estudiantesAleatorios(int $n): array
    {
        return Database::pdo()->query("SELECT id FROM usuarios WHERE rol = 'estudiante' AND activo = 1 ORDER BY RAND() LIMIT " . (int)$n)->fetchAll(PDO::FETCH_COLUMN);
    }
}
