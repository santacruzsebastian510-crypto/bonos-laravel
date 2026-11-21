<section class="card">
  <div class="filters"><h2 style="margin:0">En vivo · <?= h(fecha_larga($hoy)) ?></h2><span class="muted sm" id="vivo-hora"></span><span class="spacer"></span>
    <?php if (puede('aprobar')): ?><a class="btn ghost sm" href="<?= url('aprobaciones') ?>">Aprobaciones</a><?php endif; ?>
    <?php if (puede('canjear')): ?><a class="btn ghost sm" href="<?= url('validar') ?>">Entrega del día</a><?php endif; ?></div>
  <div class="kpis" id="vivo-kpis" style="margin-top:12px"><span class="muted">Cargando…</span></div>
</section>

<?php if (!$rows): ?>
  <div class="alert info">No hay datos cerrados en ese rango de fechas.</div>
<?php else: ?>
  <div class="kpis">
    <div class="kpi"><span class="lbl">Bonos entregados</span><span class="val"><?= number_format($tot['entregados']) ?></span><span class="muted sm"><?= $tot['tasa'] ?>% de los pedidos</span></div>
    <div class="kpi"><span class="lbl">No recogidos</span><span class="val"><?= number_format($tot['no_recogidos']) ?></span><span class="muted sm">pedidos y no recibidos</span></div>
    <div class="kpi"><span class="lbl">Sobrantes</span><span class="val"><?= number_format($tot['sobrantes']) ?></span><span class="muted sm">de <?= number_format($tot['cupos']) ?> cupos</span></div>
    <?php if ($maxEnt): ?>
    <div class="kpi"><span class="lbl">Día con más entregas</span><span class="val"><?= h($maxEnt['dia']) ?></span><span class="muted sm">prom. <?= $maxEnt['prom_entregados'] ?> por día</span></div>
    <div class="kpi"><span class="lbl">Día con menos entregas</span><span class="val"><?= h($minEnt['dia']) ?></span><span class="muted sm">prom. <?= $minEnt['prom_entregados'] ?> por día</span></div>
    <div class="kpi"><span class="lbl">Día con más sobrantes</span><span class="val"><?= h($maxSob['dia']) ?></span><span class="muted sm">prom. <?= $maxSob['prom_sobrantes'] ?> por día</span></div>
    <?php endif; ?>
  </div>
  <h2>Entregados por categoría</h2>
  <div class="kpis">
    <?php foreach (CATEGORIAS as $k => $nombre): $c = $cats[$k]; ?>
      <a class="kpi link" href="<?= url('admin') ?>?tab=categorias&<?= h($qs) ?>">
        <span class="lbl"><?= h($nombre) ?></span><span class="val"><?= number_format($c['entregados']) ?></span>
        <span class="muted sm"><?= number_format($c['reservados']) ?> pedidos · <?= $c['tasa'] ?>% recogidos</span></a>
    <?php endforeach; ?>
    <div class="kpi"><span class="lbl">Reservados que pasaron a sobrantes</span><span class="val"><?= number_format($rc['liberados']) ?></span><span class="muted sm">cupos de becados/convocatoria sin pedir</span></div>
  </div>
<?php endif; ?>
