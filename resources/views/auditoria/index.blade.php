@extends('layout.main')
@section('contenido')
<h1>Auditoría</h1>
<p class="muted">Registro de quién hizo qué y cuándo. Cada fila guarda el hash de la anterior: si alguien modifica o borra un registro, la verificación lo detecta.</p>

<section class="card">
  <div class="filters">
    <a class="btn" href="?verificar=1&<?= h($qs) ?>">Verificar integridad</a>
    <span class="muted sm">Último registro: #<?= (int)$ultimo['ultimo_id'] ?> · <code title="<?= h($ultimo['ultimo_hash']) ?>"><?= h(substr($ultimo['ultimo_hash'], 0, 16)) ?>…</code>
      Guarda este valor aparte de vez en cuando (p. ej. en un acta): es lo que permite probar que nadie reescribió toda la cadena.</span>
  </div>
  <?php if ($verif): [$okc, $n, $malo] = $verif; ?>
    <div class="alert <?= $okc ? 'ok' : 'error' ?>" style="margin:12px 0 0">
      <?= $okc ? "✔ Cadena íntegra: $n registros verificados." : '✖ Se detectó una alteración' . ($malo ? " en el registro #$malo" : '') . " (se verificaron $n registros correctos antes del problema)." ?>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <form class="filters" method="get">
    <label>Acción
      <select name="accion"><option value="">Todas</option>
        <?php foreach ($acciones as $a): ?><option <?= $a === $f['accion'] ? 'selected' : '' ?>><?= h($a) ?></option><?php endforeach; ?>
      </select></label>
    <label>Usuario (código o nombre) <input name="actor" value="<?= h($f['actor']) ?>"></label>
    <label>Desde <input type="date" name="desde" value="<?= h($f['desde']) ?>"></label>
    <label>Hasta <input type="date" name="hasta" value="<?= h($f['hasta']) ?>"></label>
    <button class="btn">Filtrar</button>
    <span class="spacer"></span>
    <a class="btn ghost sm" href="?csv=1&<?= h($qs) ?>">Exportar CSV</a>
  </form>
</section>

<section class="card">
  <div class="muted sm" style="margin-bottom:8px"><?= number_format($total) ?> registros</div>
  <div class="scroll"><table>
    <thead><tr><th>#</th><th>Fecha</th><th>Quién</th><th>Acción</th><th>Detalle</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $r): ?>
      <tr>
        <td class="muted"><?= (int)$r['id'] ?></td>
        <td style="white-space:nowrap"><?= h($r['fecha_hora']) ?></td>
        <td><?= h($r['actor_nombre'] ?? 'Sistema') ?> <span class="muted sm">· <?= h($r['rol']) ?></span></td>
        <td><code><?= h($r['accion']) ?></code><?= $r['entidad'] ? ' <span class="muted sm">' . h($r['entidad']) . ($r['entidad_id'] ? ' #' . (int)$r['entidad_id'] : '') . '</span>' : '' ?></td>
        <td class="muted sm"><?= h($r['detalle'] === '[]' ? '' : $r['detalle']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="filters" style="margin-top:12px">
    <?php if ($pag > 1): ?><a class="btn ghost sm" href="?<?= h($qs) ?>&p=<?= $pag - 1 ?>">← Más recientes</a><?php endif; ?>
    <?php if ($pag * $por < $total): ?><a class="btn ghost sm" href="?<?= h($qs) ?>&p=<?= $pag + 1 ?>">Más antiguos →</a><?php endif; ?>
  </div>
</section>
@endsection
