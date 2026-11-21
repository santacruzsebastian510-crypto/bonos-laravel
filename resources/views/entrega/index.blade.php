@extends('layout.main')
@section('contenido')
<h1>Entrega del día · <?= h(fecha_larga($hoy)) ?></h1>

<div class="tabs">
  <?php foreach (HORARIOS as $j => $i): ?>
    <a class="<?= $j === $jornada ? 'on' : '' ?>" href="?jornada=<?= $j ?>"><?= $j === 'diurna' ? '☀️' : '🌙' ?> <?= h($i['nombre']) ?></a>
  <?php endforeach; ?>
</div>

<div class="kpis">
  <div class="kpi"><span class="lbl">Por entregar</span><span class="val"><?= (int)($cuenta['aprobado'] ?? 0) ?></span><span class="muted sm">aprobados</span></div>
  <div class="kpi"><span class="lbl">Entregados</span><span class="val"><?= (int)($cuenta['entregado'] ?? 0) ?></span></div>
  <div class="kpi"><span class="lbl">Sin aprobar</span><span class="val"><?= (int)($cuenta['solicitado'] ?? 0) ?></span><span class="muted sm">en revisión</span></div>
  <div class="kpi"><span class="lbl">Cupos libres</span><span class="val"><?= $d['libres'] ?></span><span class="muted sm">de <?= $d['cupos'] ?></span></div>
</div>

<p class="filters" style="margin:-4px 0 14px">
  <?php foreach (CATEGORIAS as $k => $nombre): [, $lc] = etiqueta_categoria($k); ?>
    <span class="pill <?= $lc ?>"><?= h($nombre) ?>: <?= (int)($porCat[$k]['entregado'] ?? 0) ?> entregados · <?= (int)($porCat[$k]['aprobado'] ?? 0) ?> por entregar</span>
  <?php endforeach; ?>
</p>

<section class="card">
  <div class="filters">
    <h2 style="margin:0">Registrar entrega</h2>
    <span class="spacer"></span>
    <span id="conn" class="pill ok">En línea</span>
    <span id="cola-pill" class="pill info" hidden><span id="cola-n">0</span> pendientes de sincronizar</span>
    <button id="btn-sync" class="btn ghost sm" type="button" hidden>Sincronizar ahora</button>
  </div>
  <form id="f-canje" class="filters" method="post" autocomplete="off" style="margin-top:12px">
    <?= csrf_field() ?><input type="hidden" name="accion" value="canjear"><input type="hidden" name="jornada" value="<?= h($jornada) ?>">
    <label style="flex:1;min-width:240px">Código del bono o código estudiantil
      <input id="codigo" name="codigo" required autofocus autocapitalize="characters" placeholder="K7M2QX ó 2024000123">
    </label>
    <button class="btn">Entregar bono</button>
  </form>
  <div id="res" class="res" role="status" aria-live="polite" hidden></div>
  <p class="muted sm" id="lista-info">La lista del día se guarda en este dispositivo para poder validar aunque se caiga la conexión.</p>
  <div id="conflictos" class="alert error" hidden>
    <b>Canjes con conflicto al sincronizar</b>
    <ul id="conf-lista"></ul>
    <button class="btn ghost sm" type="button" id="conf-ok">Entendido</button>
  </div>
</section>

<section class="card">
  <div class="filters">
    <h2 style="margin:0">Bonos de la jornada <?= h(HORARIOS[$jornada]['nombre']) ?></h2>
    <span class="spacer"></span>
    <input id="q" type="search" placeholder="Buscar por nombre o código…" style="max-width:260px">
    <?php if (puede('cerrar_jornada')): ?>
    <form method="post" onsubmit="return confirm('Los bonos aprobados sin recoger pasarán a NO RECOGIDOS y las solicitudes sin aprobar se rechazarán. ¿Cerrar jornada?')">
      <?= csrf_field() ?><input type="hidden" name="accion" value="cerrar"><input type="hidden" name="jornada" value="<?= h($jornada) ?>">
      <button class="btn ghost sm">Cerrar jornada</button>
    </form>
    <?php endif; ?>
  </div>
  <?php if (!$lista): ?>
    <p class="muted">Todavía no hay bonos para esta jornada.</p>
  <?php else: ?>
  <div class="scroll"><table id="tabla">
    <thead><tr><th>Estudiante</th><th>Código</th><th>Bono</th><th>Categoría</th><th>Estado</th><th>¿Recibido?</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $r): [$txt, $cls] = etiqueta_estado($r['estado'], $hoy); ?>
      <?php [$ct, $cc] = etiqueta_categoria($r['categoria']); ?>
      <tr><td><?= h($r['nombre']) ?></td><td><?= h($r['codigo']) ?></td><td><code><?= h($r['bono']) ?></code></td>
          <td><span class="pill <?= $cc ?>"><?= h($ct) ?></span></td>
          <td><span class="pill <?= $cls ?>"><?= $txt ?></span></td>
          <td class="acts">
            <?php if ($r['estado'] === 'aprobado'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="recibido"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="jornada" value="<?= h($jornada) ?>"><button class="btn sm">Recibido</button></form>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="no_recibido"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="jornada" value="<?= h($jornada) ?>"><button class="btn ghost sm">No recibido</button></form>
            <?php elseif ($r['estado'] === 'no_recogido'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="deshacer"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="jornada" value="<?= h($jornada) ?>"><button class="btn ghost sm">Deshacer</button></form>
            <?php endif; ?>
          </td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<script>window.VALIDAR = {hoy: <?= json_encode($hoy) ?>};</script>
<script src="<?= asset('assets/validar.js') ?>"></script>
<script>
document.getElementById('q')?.addEventListener('input', e => {
  const t = e.target.value.toLowerCase();
  document.querySelectorAll('#tabla tbody tr').forEach(tr => tr.style.display = tr.textContent.toLowerCase().includes(t) ? '' : 'none');
});
</script>
@endsection
