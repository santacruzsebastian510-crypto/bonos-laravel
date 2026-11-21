<?php
/* Diseño común: cabecera con menú + contenido + pie. Variables: $titulo, $u, $noLeidas, $ultimoId, $actual, $flash, $contenido */
$menu = [
    ['estudiante', 'Mi bono', 'solicitar'], ['aprobaciones', 'Aprobaciones', 'aprobar'],
    ['validar', 'Entrega', 'canjear'], ['admin', 'Estadísticas', 'ver_stats'],
    ['auditoria', 'Auditoría', 'ver_auditoria'], ['usuarios', 'Usuarios', 'gestionar_usuarios'],
    ['correos', 'Correos', 'gestionar_usuarios'],
];
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#d6001c">
<meta name="csrf" content="<?= h(csrf_token()) ?>">
<title><?= h($titulo ?? '') ?> · Bonos de alimentación</title>
<link rel="stylesheet" href="<?= asset('assets/style.css') ?>">
</head>
<body<?= $u ? ' data-poll="1" data-last="' . (int)$ultimoId . '"' : '' ?>>
<header class="top">
  <div class="wrap top-in">
    <a class="brand" href="<?= $u ? h(url(\App\Support\Auth::homePara($u['rol']))) : h(url('/')) ?>"><img src="<?= asset('assets/img/logo.png') ?>" alt="Universidad del Valle"> <span>Bonos de alimentación</span></a>
    <?php if ($u): ?>
      <nav>
        <?php foreach ($menu as [$href, $txt, $perm]): if (!puede($perm)) continue; ?>
          <a href="<?= h(url($href)) ?>" class="<?= $actual === $href ? 'on' : '' ?>"><?= h($txt) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="who">
        <a class="bell" href="<?= url('notificaciones') ?>" title="Notificaciones" aria-label="Notificaciones">🔔<span id="bell-n" class="badge" <?= $noLeidas ? '' : 'hidden' ?>><?= (int)$noLeidas ?></span></a>
        <span class="who-name"><?= h($u['nombre']) ?> <small class="muted">· <?= h(ROLES[$u['rol']] ?? $u['rol']) ?></small></span>
        <a class="btn ghost sm" href="<?= url('logout') ?>">Salir</a>
      </div>
    <?php endif; ?>
  </div>
</header>
<main class="wrap">
<?php if ($flash): ?><div class="alert <?= h($flash[0]) ?>"><?= h($flash[1]) ?></div><?php endif; ?>
@yield('contenido')
</main>
<footer class="wrap foot">Sistema de bonos de alimentación · Universidad del Valle, Sede Norte del Cauca</footer>
<script src="<?= asset('assets/app.js') ?>"></script>
</body>
</html>
