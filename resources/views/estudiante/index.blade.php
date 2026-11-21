@extends('layout.main')
@section('contenido')
<h1>Hola, <?= h(explode(' ', $u['nombre'])[0]) ?> 👋</h1>
<p class="muted">Solicita tu bono para hoy o para el siguiente día hábil. Solo puedes tener <b>un bono activo a la vez</b>.</p>
<?php [$catTxt, $catCls] = etiqueta_categoria($categoria); ?>
<p><span class="pill <?= $catCls ?>">Categoría: <?= h($catTxt) ?></span>
  <span class="muted sm"><?= $categoria === 'regular'
      ? 'Recibes los bonos sobrantes: se abren cuando becados y convocatoria no usan sus cupos reservados.'
      : 'Tienes cupos reservados en cada jornada.' ?></span></p>

<?php if (!$elegible): ?>
  <div class="alert error"><b>No puedes solicitar bonos por ahora.</b><br><?= h($motivoNoElegible) ?></div>
<?php endif; ?>

<?php if ($activo): ?>
  <div class="alert info">Ya tienes un bono activo para el <b><?= h(fecha_larga($activo['fecha'])) ?></b> (jornada <?= h(HORARIOS[$activo['jornada']]['nombre']) ?>).
    Para pedir otro día, primero cancélalo o recíbelo.</div>
<?php endif; ?>

<div class="grid2">
<?php foreach ($dias as $dia):
    $fecha = $dia['fecha']; $mio = $dia['mio']; ?>
  <section class="card">
    <h2><?= h(fecha_larga($fecha)) ?><?= $fecha === date('Y-m-d') ? ' <span class="pill info">Hoy</span>' : '' ?></h2>

    <?php if ($mio): [$txt, $cls] = etiqueta_estado($mio['estado'], $mio['fecha']); ?>
      <div class="ticket">
        <div class="ticket-code"><?= h($mio['codigo']) ?></div>
        <div>
          <b>Jornada <?= h(HORARIOS[$mio['jornada']]['nombre']) ?></b><br>
          <span class="muted">Entrega <?= h(HORARIOS[$mio['jornada']]['entrega']) ?></span><br>
          <span class="pill <?= $cls ?>"><?= $txt ?></span>
        </div>
      </div>
      <?= linea_tiempo($mio['estado']) ?>
      <p class="muted sm">
        <?= $mio['estado'] === 'solicitado' ? 'Bienestar está revisando tu solicitud. Te avisaremos en cuanto se apruebe.'
            : ($mio['estado'] === 'aprobado' ? 'Muestra este código (o tu código estudiantil) al recibir tu bono.' : '') ?>
      </p>
      <?php if ($dia['puedeCancelar']): ?>
        <form method="post" onsubmit="return confirm('¿Cancelar tu bono? El cupo quedará libre para otro estudiante.')">
          <?= csrf_field() ?><input type="hidden" name="accion" value="cancelar"><input type="hidden" name="id" value="<?= (int)$mio['id'] ?>">
          <button class="btn ghost sm">Cancelar bono</button>
        </form>
      <?php endif; ?>

    <?php else: ?>
      <?php if ($f = $dia['fallido']): ?>
        <div class="alert <?= $f['estado'] === 'rechazado' ? 'error' : 'info' ?>">
          Tu solicitud anterior fue <b><?= $f['estado'] === 'rechazado' ? 'rechazada' : 'cancelada' ?></b>.
          <?= $f['motivo_rechazo'] ? h($f['motivo_rechazo']) . '.' : '' ?> Puedes solicitar de nuevo.
        </div>
      <?php endif; ?>
      <?php foreach ($dia['jornadas'] as $j => $d):
          $info = $d['info']; $mios = $d['mios']; $abiertos = $d['abiertos']; $cerrada = $d['cerrada'];
          $pct = $d['cupos'] ? round(100 * $d['usados'] / $d['cupos']) : 100; ?>
        <div class="jornada">
          <div class="jhead">
            <b><?= $j === 'diurna' ? '☀️' : '🌙' ?> Jornada <?= h($info['nombre']) ?></b>
            <span class="muted sm">Entrega <?= h($info['entrega']) ?></span>
          </div>
          <div class="bar"><span style="width:<?= $pct ?>%"></span></div>
          <div class="jfoot">
            <span class="sm"><b><?= $mios ?></b> cupos para ti · <?= $d['libres'] ?> libres de <?= $d['cupos'] ?>
              <?php if (!$abiertos && $categoria === 'regular'): ?><br><span class="muted">Sobrantes desde las <?= h($info['sobrantes']) ?></span><?php endif; ?></span>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="accion" value="reservar">
              <input type="hidden" name="fecha" value="<?= h($fecha) ?>"><input type="hidden" name="jornada" value="<?= h($j) ?>">
              <button class="btn sm" <?= (!$elegible || $activo || $cerrada || $mios === 0) ? 'disabled' : '' ?>>
                <?= $cerrada ? 'Cerrado desde ' . h($info['limite'])
                    : ($activo ? 'Ya tienes un bono activo'
                    : ($mios === 0 ? (!$abiertos ? ($categoria === 'regular' ? 'Sobrantes desde ' . h($info['sobrantes']) : 'Reserva agotada') : 'Sin cupos') : 'Solicitar bono')) ?>
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>

<section class="card">
  <h2>Mi historial</h2>
  <?php if (!$historial): ?>
    <p class="muted">Aún no tienes bonos anteriores.</p>
  <?php else: ?>
    <div class="scroll"><table>
      <thead><tr><th>Fecha</th><th>Jornada</th><th>Código</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($historial as $b): [$txt, $cls] = etiqueta_estado($b['estado'], $b['fecha']); ?>
        <tr><td><?= h(fecha_larga($b['fecha'])) ?></td><td><?= h(HORARIOS[$b['jornada']]['nombre']) ?></td>
            <td><code><?= h($b['codigo']) ?></code></td><td><span class="pill <?= $cls ?>"><?= $txt ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
@endsection
