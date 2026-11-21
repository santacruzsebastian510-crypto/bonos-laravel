<html><head><meta charset="utf-8"></head><body>
<h3>Resumen por día de la semana</h3><table border="1"><tr>
<?php foreach ($cabSem as $c) echo '<th>' . h($c) . '</th>'; ?></tr>
<?php foreach ($resSem as $r) { echo '<tr>'; foreach ($r as $v) echo '<td>' . h($v) . '</td>'; echo '</tr>'; } ?></table><br>
<h3>Por categoría</h3><table border="1"><tr>
<?php foreach ($cabCat as $c) echo '<th>' . h($c) . '</th>'; ?></tr>
<?php foreach ($resCat as $r) { echo '<tr>'; foreach ($r as $v) echo '<td>' . h($v) . '</td>'; echo '</tr>'; } ?></table>
<p>Cupos reservados que nadie pidió y pasaron a sobrantes: <?= h($cats['liberados']) ?></p><br>
<h3>Detalle diario</h3><table border="1"><tr>
<?php foreach ($cabDetalle as $c) echo '<th>' . h($c) . '</th>'; ?></tr>
<?php foreach ($detalle as $r) { echo '<tr>'; foreach ($r as $v) echo '<td>' . h($v) . '</td>'; echo '</tr>'; } ?></table>
</body></html>
