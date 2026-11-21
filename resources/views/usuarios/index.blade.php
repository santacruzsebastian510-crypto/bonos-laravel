@extends('layout.main')
@section('contenido')
<h1>Usuarios y roles</h1>
<div class="kpis">
  <?php foreach (ROLES as $k => $nombre): ?>
    <div class="kpi"><span class="lbl"><?= h($nombre) ?></span><span class="val"><?= (int)($conteo[$k] ?? 0) ?></span></div>
  <?php endforeach; ?>
</div>

<p class="filters"><?php foreach (CATEGORIAS as $k => $n): [, $cc] = etiqueta_categoria($k); ?><span class="pill <?= $cc ?>"><?= h($n) ?>: <?= (int)($conteoCat[$k] ?? 0) ?> estudiantes</span><?php endforeach; ?></p>

<section class="card">
  <h2>Cargar lista por categoría</h2>
  <p class="muted sm">Pega los códigos estudiantiles (uno por línea, o separados por comas) de los becados o de quienes quedaron en la convocatoria.</p>
  <form class="filters" method="post">
    <?= csrf_field() ?><input type="hidden" name="accion" value="cargar_lista">
    <label>Categoría <select name="categoria"><?php foreach (CATEGORIAS as $k => $n): ?><option value="<?= $k ?>"><?= h($n) ?></option><?php endforeach; ?></select></label>
    <label style="flex:1;min-width:260px">Códigos <textarea name="codigos" rows="3" required placeholder="2024000001&#10;2024000002"></textarea></label>
    <button class="btn">Aplicar</button>
  </form>
</section>

<section class="card">
  <h2>Crear usuario</h2>
  <form class="filters" method="post">
    <?= csrf_field() ?><input type="hidden" name="accion" value="crear">
    <label>Código <input name="codigo" required maxlength="20"></label>
    <label>Nombre <input name="nombre" required maxlength="100"></label>
    <label>Correo institucional <input name="email" type="email" maxlength="120"></label>
    <label>Rol <select name="rol"><?php foreach (ROLES as $k => $n): ?><option value="<?= $k ?>"><?= h($n) ?></option><?php endforeach; ?></select></label>
    <label>Categoría (estudiantes) <select name="categoria"><?php foreach (CATEGORIAS as $k => $n): ?><option value="<?= $k ?>" <?= $k === 'regular' ? 'selected' : '' ?>><?= h(CATEGORIA_UNO[$k]) ?></option><?php endforeach; ?></select></label>
    <label>Contraseña <input name="password" type="password" minlength="8" required></label>
    <button class="btn">Crear</button>
  </form>
</section>

<section class="card">
  <form class="filters" method="get">
    <label>Buscar <input name="q" value="<?= h($q) ?>" placeholder="código o nombre"></label>
    <label>Rol <select name="rol"><option value="">Todos</option><?php foreach (ROLES as $k => $n): ?><option value="<?= $k ?>" <?= $k === $fr ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></label>
    <button class="btn">Filtrar</button>
  </form>
  <div class="scroll" style="margin-top:12px"><table>
    <thead><tr><th>Código</th><th>Nombre</th><th>Rol</th><th>Categoría</th><th>Activo</th><th>Nueva contraseña</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $r): $f = 'f' . (int)$r['id']; ?>
      <tr>
        <td><?= h($r['codigo']) ?></td><td><?= h($r['nombre']) ?><br><span class="muted sm"><?= h($r['email'] ?? '') ?></span></td>
        <td><select name="rol" form="<?= $f ?>"><?php foreach (ROLES as $k => $n): ?><option value="<?= $k ?>" <?= $k === $r['rol'] ? 'selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></td>
        <td><?php if ($r['rol'] === 'estudiante'): ?><select name="categoria" form="<?= $f ?>"><?php foreach (CATEGORIAS as $k => $n): ?><option value="<?= $k ?>" <?= $k === $r['categoria'] ? 'selected' : '' ?>><?= h(CATEGORIA_UNO[$k]) ?></option><?php endforeach; ?></select>
            <?php else: ?><input type="hidden" name="categoria" form="<?= $f ?>" value="<?= h($r['categoria']) ?>"><span class="muted">—</span><?php endif; ?></td>
        <td><input type="checkbox" name="activo" form="<?= $f ?>" <?= $r['activo'] ? 'checked' : '' ?>></td>
        <td><input type="password" name="password" form="<?= $f ?>" placeholder="(sin cambio)" autocomplete="new-password" style="width:150px"></td>
        <td><form id="<?= $f ?>" method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="q" value="<?= h($q) ?>"><input type="hidden" name="filtro_rol" value="<?= h($fr) ?>"><button class="btn sm">Guardar</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (count($usuarios) === 100): ?><p class="muted sm">Se muestran los primeros 100; usa la búsqueda para encontrar a alguien en particular.</p><?php endif; ?>
</section>
@endsection
