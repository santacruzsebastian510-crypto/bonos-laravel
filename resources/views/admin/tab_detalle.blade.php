  <?php if (!$rows): ?>
    <div class="alert info">No hay datos cerrados en ese rango de fechas.</div>
  <?php else: ?>
  <section class="card">
    <h2>Por día de la semana</h2>
    <div class="scroll"><table>
      <thead><tr><th>Día</th><th>Días</th><th>Entregados</th><th>Prom.</th><th>No recog.</th><th>Prom.</th><th>Sobrantes</th><th>Prom.</th></tr></thead>
      <tbody>
      <?php foreach ($sem as $w): ?>
        <tr><td><?= h($w['dia']) ?></td><td><?= $w['dias'] ?></td><td><?= number_format($w['entregados']) ?></td><td><?= $w['prom_entregados'] ?></td>
            <td><?= number_format($w['no_recogidos']) ?></td><td><?= $w['prom_no_recogidos'] ?></td><td><?= number_format($w['sobrantes']) ?></td><td><?= $w['prom_sobrantes'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <section class="card">
    <h2>Detalle diario</h2>
    <div class="scroll"><table>
      <thead><tr><th>Fecha</th><th>Jornada</th><th>Cupos</th><th>Pedidos</th><th>Entregados</th><th>No recog.</th><th>Sobrantes</th><th>Becados</th><th>Convoc.</th><th>Regulares</th></tr></thead>
      <tbody>
      <?php foreach (array_reverse($rows) as $r): ?>
        <tr><td><?= h(substr(dia_semana($r['fecha']), 0, 3) . ' ' . substr($r['fecha'], 5)) ?></td><td><?= h(HORARIOS[$r['jornada']]['nombre']) ?></td>
            <td><?= $r['cupos'] ?></td><td><?= $r['reservados'] ?></td><td><?= $r['entregados'] ?></td><td><?= $r['no_recogidos'] ?></td><td><?= $r['sobrantes'] ?></td>
            <td><?= $r['ent_becado'] ?></td><td><?= $r['ent_convocatoria'] ?></td><td><?= $r['ent_regular'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="muted sm" style="margin-bottom:0">Las tres últimas columnas son bonos entregados por categoría.</p>
  </section>
  <?php endif; ?>
