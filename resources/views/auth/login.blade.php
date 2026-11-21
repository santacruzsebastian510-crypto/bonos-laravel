@extends('layout.main')
@section('contenido')
<section class="login">
  <div class="login-bg"></div>
  <div class="card">
    <img class="login-logo" src="assets/img/logo.png" alt="Universidad del Valle">
    <h1>Recibe tu bono</h1>
    <p class="muted">Inicia sesión con tu código estudiantil para separar tu bono de alimentación.</p>
    <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label>Código de usuario
        <input name="codigo" required autofocus placeholder="2024000001 / admin" autocapitalize="off">
      </label>
      <label>Contraseña
        <input name="password" type="password" required>
      </label>
      <button class="btn" type="submit">Iniciar sesión</button>
    </form>
    <p class="hint">Demo — estudiante <b>2024000001</b> / <b>estudiante123</b><br>admin <b>admin</b> / <b>admin123</b> · bienestar <b>bienestar</b> / <b>bienestar123</b><br>supervisor <b>supervisor</b> / <b>supervisor123</b> · auditor <b>auditor</b> / <b>auditor123</b></p>
  </div>
</section>
@endsection
