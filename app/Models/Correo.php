<?php
namespace App\Models;

use App\Support\Database;
use PDO;

/** Cola de correos (tabla cola_correos). */
class Correo
{
    /** @return array{0:int,1:int} [enviados, con error] — solo envía si MAIL_MODO = 'mail'. */
    public static function procesarCola(int $max = 50): array
    {
        if (MAIL_MODO !== 'mail') {
            return [0, 0];
        }
        $pdo = Database::pdo();
        $ok = $err = 0;
        $s = $pdo->query("SELECT id, para, asunto, cuerpo FROM cola_correos WHERE estado = 'pendiente' ORDER BY id LIMIT " . (int)$max);
        foreach ($s->fetchAll() as $c) {
            $enviado = @mail($c['para'], '=?UTF-8?B?' . base64_encode($c['asunto']) . '?=', $c['cuerpo'],
                "From: " . MAIL_REMITENTE . "\r\nContent-Type: text/plain; charset=UTF-8");
            $pdo->prepare('UPDATE cola_correos SET estado = ?, enviado_en = ?, error = ? WHERE id = ?')
                ->execute([$enviado ? 'enviado' : 'error', $enviado ? date('Y-m-d H:i:s') : null, $enviado ? null : 'mail() devolvió false', $c['id']]);
            $enviado ? $ok++ : $err++;
        }
        return [$ok, $err];
    }

    public static function conteos(): array
    {
        return Database::pdo()->query('SELECT estado, COUNT(*) FROM cola_correos GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function recientes(int $max = 100): array
    {
        return Database::pdo()->query('SELECT para, asunto, estado, creado_en, error FROM cola_correos ORDER BY id DESC LIMIT ' . (int)$max)->fetchAll();
    }
}
