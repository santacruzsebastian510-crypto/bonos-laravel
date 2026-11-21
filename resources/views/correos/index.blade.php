@extends('layout.main')
@section('contenido')
<h1>Cola de correos</h1>
<p class="muted">Cada notificación a un usuario con correo registrado genera un mensaje aquí. Modo actual: <b><?= h(MAIL_MODO) ?></b>
  <?= MAIL_MODO === 'cola' ? '(solo se guardan; para enviarlos de verdad cambia MAIL_MODO a “mail” en app/Support/constantes.php y configura el correo de PHP)' : '' ?></p>
<section class="card">
  <div class="filters">
    <span class="pill info"><?= (int)($cont['pendiente'] ?? 0) ?> pendientes</span>
    <span class="pill ok"><?= (int)($cont['enviado'] ?? 0) ?> enviados</span>
    <span class="pill err"><?= (int)($cont['error'] ?? 0) ?> con error</span>
    <span class="spacer"></span>
    <form method="post"><?= csrf_field() ?><button class="btn" <?= MAIL_MODO === 'mail' ? '' : 'disabled' ?>>Enviar pendientes</button></form>
  </div>
</section>
<section class="card">
  <?php if (!$filas): ?><p class="muted">Aún no hay correos.</p><?php else: ?>
  <div class="scroll"><table>
    <thead><tr><th>Fecha</th><th>Para</th><th>Asunto</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $r): ?>
      <tr><td style="white-space:nowrap"><?= h(substr($r['creado_en'], 0, 16)) ?></td><td><?= h($r['para']) ?></td><td><?= h($r['asunto']) ?></td>
          <td><span class="pill <?= $r['estado'] === 'enviado' ? 'ok' : ($r['estado'] === 'error' ? 'err' : 'info') ?>"><?= h($r['estado']) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
@endsection
