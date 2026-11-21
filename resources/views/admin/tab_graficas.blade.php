  <?php if (!$rows): ?>
    <div class="alert info">No hay datos cerrados en ese rango de fechas.</div>
  <?php else: ?>
  <div class="grid2">
    <section class="card"><h2>Promedio por día de la semana</h2><div class="chart"><canvas id="cSemana"></canvas></div></section>
    <section class="card"><h2>Evolución diaria</h2><div class="chart"><canvas id="cDiario"></canvas></div></section>
  </div>
  <div class="grid2">
    <section class="card"><h2>Entregados por jornada</h2><div class="chart sm"><canvas id="cJornada"></canvas></div></section>
    <section class="card"><h2>Pedidos y entregas por categoría</h2><div class="chart sm"><canvas id="cCategoria"></canvas></div></section>
  </div>
  <script src="<?= asset('assets/chart.umd.js') ?>"></script>
  <script>
  const C = {ent:'#d6001c', nor:'#4b5563', sob:'#d1d5db', dia:'#d6001c', noc:'#374151', bec:'#d6001c', con:'#374151', reg:'#9ca3af'};
  Chart.defaults.color = getComputedStyle(document.body).color; Chart.defaults.font.family = 'system-ui, sans-serif';
  Chart.defaults.borderColor = 'rgba(128,128,128,.2)';
  const opt = {maintainAspectRatio:false, plugins:{legend:{position:'bottom'}}, scales:{y:{beginAtZero:true}}};

  const sem = <?= json_encode(array_values(array_map(fn($w) => [$w['dia'], $w['prom_entregados'], $w['prom_no_recogidos'], $w['prom_sobrantes']], $sem)), JSON_UNESCAPED_UNICODE) ?>;
  new Chart(document.getElementById('cSemana'), {type:'bar', options:opt, data:{labels:sem.map(x=>x[0]), datasets:[
    {label:'Entregados', data:sem.map(x=>x[1]), backgroundColor:C.ent},
    {label:'No recogidos', data:sem.map(x=>x[2]), backgroundColor:C.nor},
    {label:'Sobrantes', data:sem.map(x=>x[3]), backgroundColor:C.sob}]}});

  const dia = <?= json_encode(array_map(fn($f, $v) => [substr($f, 5), $v['entregados'], $v['sobrantes']], array_keys($porFecha), array_values($porFecha))) ?>;
  new Chart(document.getElementById('cDiario'), {type:'line', options:opt, data:{labels:dia.map(x=>x[0]), datasets:[
    {label:'Entregados', data:dia.map(x=>x[1]), borderColor:C.ent, backgroundColor:C.ent, tension:.25, pointRadius:2},
    {label:'Sobrantes', data:dia.map(x=>x[2]), borderColor:C.sob, backgroundColor:C.sob, tension:.25, pointRadius:2}]}});

  new Chart(document.getElementById('cJornada'), {type:'doughnut', options:{maintainAspectRatio:false, plugins:{legend:{position:'bottom'}}},
    data:{labels:['Diurna','Nocturna'], datasets:[{data:<?= json_encode(array_values($porJornada)) ?>, backgroundColor:[C.dia, C.noc], borderWidth:0}]}});

  const cat = <?= json_encode([
      'labels' => array_values(CATEGORIAS),
      'pedidos' => array_values(array_map(fn($c) => $c['reservados'], $cats)),
      'entregados' => array_values(array_map(fn($c) => $c['entregados'], $cats)),
      'no' => array_values(array_map(fn($c) => $c['no_recogidos'], $cats)),
  ], JSON_UNESCAPED_UNICODE) ?>;
  new Chart(document.getElementById('cCategoria'), {type:'bar', options:opt, data:{labels:cat.labels, datasets:[
    {label:'Pedidos', data:cat.pedidos, backgroundColor:C.sob},
    {label:'Entregados', data:cat.entregados, backgroundColor:C.ent},
    {label:'No recogidos', data:cat.no, backgroundColor:C.nor}]}});
  </script>
  <?php endif; ?>
