<section class="card">
  <h2>Cupos por jornada</h2>
  <p class="muted sm">Define cuántos bonos hay en cada jornada y cuántos quedan <b>reservados</b> para becados y para quienes quedaron en la convocatoria.
    Lo que no esté reservado, más lo reservado que nadie pida, pasa a los estudiantes regulares como sobrantes.</p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="accion" value="cupos">
    <div class="grid2" style="margin-bottom:12px">
      <?php foreach ($cfg as $j => $x):
          $info = $x['info']; $t = $x['total']; $b = $x['becado']; $c = $x['convocatoria']; ?>
        <div class="jornada">
          <b><?= $j === 'diurna' ? '☀️' : '🌙' ?> Jornada <?= h($info['nombre']) ?></b>
          <div class="filters" style="margin-top:8px">
            <label>Total de cupos <input type="number" name="cupos_<?= $j ?>" min="1" max="5000" value="<?= $t ?>" required></label>
            <label>Reservados becados <input type="number" name="reserva_<?= $j ?>_becado" min="0" value="<?= $b ?>" required></label>
            <label>Reservados convocatoria <input type="number" name="reserva_<?= $j ?>_convocatoria" min="0" value="<?= $c ?>" required></label>
          </div>
          <span class="muted sm">Sobrantes de base para regulares: <b><?= max(0, $t - $b - $c) ?></b> · se abren a las <?= h($info['sobrantes']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="filters">
      <button class="btn">Guardar</button>
      <span class="muted sm">Cupos por día: <b><?= $cuposDia ?></b> · Estudiantes activos: <b><?= $totEst ?></b>
        (<?= (int)($estudiantes['becado'] ?? 0) ?> becados, <?= (int)($estudiantes['convocatoria'] ?? 0) ?> convocatoria, <?= (int)($estudiantes['regular'] ?? 0) ?> regulares)</span>
    </div>
  </form>
</section>
