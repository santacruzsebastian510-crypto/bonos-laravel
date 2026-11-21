@extends('layout.main')
@section('contenido')
<h1>Aprobación de bonos</h1>

<section class="card">
  <form class="filters" method="get">
    <label>Fecha
      <select name="fecha" onchange="this.form.submit()">
        <?php foreach ($fechas as $f): ?><option value="<?= h($f) ?>" <?= $f === $fecha ? 'selected' : '' ?>><?= h(fecha_larga($f)) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Jornada
      <select name="jornada" onchange="this.form.submit()">
        <option value="">Ambas</option>
        <?php foreach (HORARIOS as $j => $i): ?><option value="<?= $j ?>" <?= $jor === $j ? 'selected' : '' ?>><?= h($i['nombre']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <span class="spacer"></span>
    <span class="pill info"><?= (int)($cont['solicitado'] ?? 0) ?> pendientes</span>
    <span class="pill ok"><?= (int)($cont['aprobado'] ?? 0) ?> aprobadas</span>
    <span class="pill err"><?= (int)($cont['rechazado'] ?? 0) ?> rechazadas</span>
  </form>
</section>

<section class="card">
  <div class="filters">
    <form method="post" onsubmit="return confirm('Se aprobarán todas las solicitudes pendientes de estudiantes elegibles. ¿Continuar?')">
      <?= csrf_field() ?><input type="hidden" name="accion" value="masivo"><input type="hidden" name="fecha" value="<?= h($fecha) ?>"><input type="hidden" name="jornada" value="<?= h($jor) ?>">
      <button class="btn" <?= $pend ? '' : 'disabled' ?>>Aprobar todos los elegibles (<?= $elegibles ?>)</button>
    </form>
    <span class="spacer"></span>
    <form method="post" class="filters">
      <?= csrf_field() ?><input type="hidden" name="accion" value="modo"><input type="hidden" name="fecha" value="<?= h($fecha) ?>"><input type="hidden" name="jornada" value="<?= h($jor) ?>">
      <label>Modo de aprobación
        <select name="manual" onchange="this.form.submit()">
          <option value="1" <?= $manual ? 'selected' : '' ?>>Manual (Bienestar revisa)</option>
          <option value="0" <?= !$manual ? 'selected' : '' ?>>Automática si es elegible</option>
        </select>
      </label>
    </form>
    <?php if (MODO_DEMO && $u['rol'] === 'admin'): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="demo"><input type="hidden" name="fecha" value="<?= h($fecha) ?>"><input type="hidden" name="jornada" value="<?= h($jor) ?>">
        <button class="btn ghost sm">Generar solicitudes de prueba</button></form>
    <?php endif; ?>
  </div>
</section>

<section class="card">
  <h2>Solicitudes pendientes · <?= h(fecha_larga($fecha)) ?></h2>
  <?php if (!$pend): ?>
    <p class="muted">No hay solicitudes pendientes.</p>
  <?php else: ?>
    <div class="scroll"><table>
      <thead><tr><th>Estudiante</th><th>Código</th><th>Programa</th><th>Jornada</th><th>Elegibilidad</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($pend as $r):
          $ok = $r['matricula_activa'] && $r['paz_y_salvo'];
          $why = $r['matricula_activa'] === null ? 'No está en el sistema académico' : (!$r['matricula_activa'] ? 'Matrícula inactiva' : 'Novedad financiera'); ?>
        <tr>
          <td><?= h($r['nombre']) ?></td><td><?= h($r['codigo']) ?></td><td class="muted"><?= h($r['programa'] ?? '—') ?></td>
          <td><?= h(HORARIOS[$r['jornada']]['nombre']) ?></td>
          <td><span class="pill <?= $ok ? 'ok' : 'err' ?>"><?= $ok ? 'Elegible' : h($why) ?></span></td>
          <td class="acts">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="aprobar"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="fecha" value="<?= h($fecha) ?>"><input type="hidden" name="jornada" value="<?= h($jor) ?>">
              <button class="btn sm" <?= $ok ? '' : 'disabled' ?>>Aprobar</button></form>
            <form method="post" class="rej"><?= csrf_field() ?><input type="hidden" name="accion" value="rechazar"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="fecha" value="<?= h($fecha) ?>"><input type="hidden" name="jornada" value="<?= h($jor) ?>">
              <input name="motivo" required maxlength="200" placeholder="Motivo del rechazo" aria-label="Motivo del rechazo">
              <button class="btn ghost sm">Rechazar</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if (count($pend) === 300): ?><p class="muted sm">Se muestran las primeras 300. Resuelve estas y recarga para ver el resto.</p><?php endif; ?>
  <?php endif; ?>
</section>

<?php if ($recientes): ?>
<section class="card">
  <h2>Últimas resoluciones</h2>
  <div class="scroll"><table>
    <thead><tr><th>Estudiante</th><th>Resultado</th><th>Por</th><th>Hora</th></tr></thead>
    <tbody>
    <?php foreach ($recientes as $r): ?>
      <tr><td><?= h($r['nombre']) ?></td>
          <td><span class="pill <?= $r['estado'] === 'rechazado' ? 'err' : 'ok' ?>"><?= $r['estado'] === 'rechazado' ? 'Rechazado' : 'Aprobado' ?></span>
              <?= $r['motivo_rechazo'] ? '<span class="muted sm">' . h($r['motivo_rechazo']) . '</span>' : '' ?></td>
          <td><?= h($r['por'] ?? 'Sistema') ?></td><td><?= h(substr((string)$r['aprobado_en'], 11, 5)) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
@endsection
