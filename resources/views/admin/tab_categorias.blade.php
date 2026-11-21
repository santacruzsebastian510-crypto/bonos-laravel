<section class="card">
  <h2>Cómo se reparten los bonos</h2>
  <div class="rule">
    <div><b>Becados</b>Tienen cupos reservados en cada jornada.</div>
    <div><b>Convocatoria</b>Quienes quedaron seleccionados tras la convocatoria; también tienen cupos reservados.</div>
    <div><b>Regulares</b>Reciben los sobrantes: los cupos sin reservar y los reservados que nadie pidió. Se abren a las
      <?= h(HORARIOS['diurna']['sobrantes']) ?> (diurna) y <?= h(HORARIOS['nocturna']['sobrantes']) ?> (nocturna).</div>
  </div>
</section>

<?php if (!$rows): ?>
  <div class="alert info">No hay datos cerrados en ese rango de fechas.</div>
<?php else: ?>
<section class="card">
  <h2>Resumen del periodo por categoría</h2>
  <div class="scroll"><table>
    <thead><tr><th>Categoría</th><th>Estudiantes</th><th>Cupos reservados</th><th>Pedidos</th><th>Entregados</th><th>No recogidos</th><th>% recogidos</th></tr></thead>
    <tbody>
    <?php foreach (CATEGORIAS as $k => $nombre): $c = $cats[$k]; [, $cc] = etiqueta_categoria($k); ?>
      <tr>
        <td><span class="pill <?= $cc ?>"><?= h($nombre) ?></span></td>
        <td><?= (int)($estudiantes[$k] ?? 0) ?></td>
        <td><?= number_format($c['cupos_reservados']) ?><?= $k === 'regular' ? ' <span class="muted sm">(sobrantes base)</span>' : '' ?></td>
        <td><?= number_format($c['reservados']) ?></td><td><?= number_format($c['entregados']) ?></td>
        <td><?= number_format($c['no_recogidos']) ?></td><td><?= $c['tasa'] ?>%</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted sm" style="margin-bottom:0">Reservados sin pedir que se liberaron como sobrantes: <b><?= number_format($rc['liberados']) ?></b> ·
    Sobrantes que recibieron los regulares: <b><?= number_format($cats['regular']['entregados']) ?></b>.</p>
</section>
<?php endif; ?>

<section class="card">
  <h2>Hoy por categoría · <?= h(fecha_larga($hoy)) ?></h2>
  <div class="scroll"><table>
    <thead><tr><th>Jornada</th><th>Becados (pedidos / reserva)</th><th>Convocatoria (pedidos / reserva)</th><th>Regulares (pedidos)</th><th>Total</th><th>Sobrantes</th></tr></thead>
    <tbody>
    <?php foreach ($hoyCat as $j => $x): $info = $x['info']; $e = $x['estado']; $ab = $x['abiertos']; ?>
      <tr><td><?= h($info['nombre']) ?></td>
        <td><?= $e['usados']['becado'] ?> / <?= $e['reserva']['becado'] ?></td>
        <td><?= $e['usados']['convocatoria'] ?> / <?= $e['reserva']['convocatoria'] ?></td>
        <td><?= $e['usados']['regular'] ?></td>
        <td><?= $e['total'] ?> de <?= $e['cupos'] ?></td>
        <td><?= $ab ? 'Abiertos (' . max(0, $e['cupos'] - $e['total']) . ' libres)' : 'Desde las ' . h($info['sobrantes']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
