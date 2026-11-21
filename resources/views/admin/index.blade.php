@extends('layout.main')
@section('contenido')
<h1>Panel de administración</h1>
<nav class="subtabs" aria-label="Secciones del panel">
  <?php foreach ($tabs as $k => $txt): ?>
    <a class="<?= $k === $tab ? 'on' : '' ?>" href="<?= url('admin') ?>?tab=<?= $k ?>&<?= h($qs) ?>"><?= h($txt) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab !== 'cupos'): ?>
<section class="card">
  <form class="filters" method="get">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <label>Desde <input type="date" name="desde" value="<?= h($desde) ?>"></label>
    <label>Hasta <input type="date" name="hasta" value="<?= h($hasta) ?>" max="<?= h($ayer) ?>"></label>
    <label>Jornada
      <select name="jornada">
        <option value="">Ambas</option>
        <?php foreach (HORARIOS as $j => $i): ?><option value="<?= $j ?>" <?= $jor === $j ? 'selected' : '' ?>><?= h($i['nombre']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <button class="btn">Aplicar</button>
    <span class="spacer"></span>
    <span class="muted sm">Exportar:</span>
    <a class="btn ghost sm" href="<?= url('exportar') ?>?formato=csv&<?= h($qs) ?>">CSV</a>
    <a class="btn ghost sm" href="<?= url('exportar') ?>?formato=xls&<?= h($qs) ?>">Excel</a>
    <a class="btn ghost sm" href="<?= url('exportar') ?>?formato=json&<?= h($qs) ?>">JSON</a>
    <a class="btn ghost sm" target="_blank" href="<?= url('exportar') ?>?formato=pdf&<?= h($qs) ?>">PDF</a>
  </form>
  <p class="muted sm" style="margin-bottom:0">Las cifras del periodo cuentan solo días cerrados (hasta ayer). <b>No recogidos</b> = pedidos que no se recibieron · <b>Sobrantes</b> = cupos − entregados.</p>
</section>
<?php endif; ?>

@include('admin.tab_' . $tab)
@endsection
