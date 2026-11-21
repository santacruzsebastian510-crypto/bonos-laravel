@extends('layout.main')
@section('contenido')
<div class="card"><h2>Sin permiso</h2><p class="muted">Tu rol (<?= h($rol) ?>) no tiene acceso a esta sección.</p><a class="btn" href="<?= h($home) ?>">Volver</a></div>
@endsection
