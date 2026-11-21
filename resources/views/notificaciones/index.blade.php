@extends('layout.main')
@section('contenido')
<div class="filters"><h1 style="margin:0">Notificaciones</h1><span class="spacer"></span>
  <button id="btn-avisos" class="btn ghost sm" type="button" hidden>Activar avisos del navegador</button></div>
<p class="muted sm">Los avisos del navegador aparecen mientras tengas la aplicación abierta. También se guarda una copia por correo institucional (ver “Correos” del administrador).</p>
<section class="card">
  <?php if (!$lista): ?>
    <p class="muted">Aún no tienes notificaciones.</p>
  <?php else: foreach ($lista as $n): ?>
    <div class="notif <?= $n['leida'] ? '' : 'nueva' ?>">
      <b><?= h($n['titulo']) ?></b> <span class="muted sm"><?= h(substr($n['creado_en'], 0, 16)) ?></span>
      <div><?= h($n['mensaje']) ?></div>
    </div>
  <?php endforeach; endif; ?>
</section>
@endsection
