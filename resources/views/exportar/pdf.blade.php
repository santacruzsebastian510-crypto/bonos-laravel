<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title><?= h($archivo) ?></title>
<style>
  body{font:13px system-ui,sans-serif;margin:24px;color:#111}
  h1{font-size:20px;margin:0 0 4px} h2{font-size:15px;margin:22px 0 6px}
  table{border-collapse:collapse;width:100%} th,td{border:1px solid #bbb;padding:4px 8px;text-align:left} th{background:#eee}
  .p{color:#555;margin:0 0 8px} @@media print{button{display:none}}
</style></head><body>
<button onclick="print()">Imprimir / Guardar como PDF</button>
<h1>Reporte de entrega de bonos de alimentación</h1>
<p class="p">Periodo: <?= h($desde) ?> a <?= h($hasta) ?> · Jornada: <?= $jor ? h(HORARIOS[$jor]['nombre']) : 'ambas' ?></p>
<p>Entregados: <b><?= $tot['entregados'] ?></b> · No recogidos: <b><?= $tot['no_recogidos'] ?></b> · Sobrantes: <b><?= $tot['sobrantes'] ?></b> · Cupos: <b><?= $tot['cupos'] ?></b></p>
<h2>Por día de la semana</h2>
<table><tr><?php foreach ($cabSem as $c) echo '<th>' . h($c) . '</th>'; ?></tr>
<?php foreach ($resSem as $r) { echo '<tr>'; foreach ($r as $v) echo '<td>' . h($v) . '</td>'; echo '</tr>'; } ?></table>
<h2>Por categoría</h2>
<table><tr><?php foreach ($cabCat as $c) echo '<th>' . h($c) . '</th>'; ?></tr>
<?php foreach ($resCat as $r) { echo '<tr>'; foreach ($r as $v) echo '<td>' . h($v) . '</td>'; echo '</tr>'; } ?></table>
<p>Cupos reservados que nadie pidió y pasaron a sobrantes: <b><?= h($cats['liberados']) ?></b></p>
<h2>Detalle diario</h2>
<table><tr><?php foreach ($cabDetalle as $c) echo '<th>' . h($c) . '</th>'; ?></tr>
<?php foreach ($detalle as $r) { echo '<tr>'; foreach ($r as $v) echo '<td>' . h($v) . '</td>'; echo '</tr>'; } ?></table>
<script>window.onload = () => setTimeout(print, 300)</script>
</body></html>
