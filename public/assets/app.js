/* Notificaciones (campana + avisos del navegador) y panel en vivo. */
(() => {
  const body = document.body;
  const txt = (el, t) => { if (el) el.textContent = t; };

  /* ---- Campana: consulta cada 30 s; si hay notificaciones nuevas y el usuario activó los avisos, las muestra ---- */
  if (body.dataset.poll) {
    let ultimo = parseInt(body.dataset.last || '0', 10);
    const bell = document.getElementById('bell-n');
    const consultar = async () => {
      if (document.hidden) return;
      try {
        const r = await fetch('api?accion=notif&desde=' + ultimo, { credentials: 'same-origin' });
        if (!r.ok) return;
        const d = await r.json();
        if (bell) { bell.hidden = !d.no_leidas; txt(bell, d.no_leidas); }
        for (const n of d.nuevas) {
          ultimo = Math.max(ultimo, n.id);
          if ('Notification' in window && Notification.permission === 'granted') new Notification(n.titulo, { body: n.mensaje });
        }
      } catch { /* sin red */ }
    };
    setInterval(consultar, 30000);
    document.addEventListener('visibilitychange', consultar);
  }

  /* ---- Botón "Activar avisos del navegador" (página de notificaciones) ---- */
  const btn = document.getElementById('btn-avisos');
  if (btn) {
    const estado = () => {
      if (!('Notification' in window)) { btn.hidden = true; return; }
      btn.hidden = Notification.permission === 'granted';
      if (Notification.permission === 'denied') { btn.disabled = true; btn.textContent = 'Avisos bloqueados en este navegador'; }
    };
    btn.addEventListener('click', () => Notification.requestPermission().then(estado));
    estado();
  }

  /* ---- Panel en vivo (estadísticas) ---- */
  const vivo = document.getElementById('vivo-kpis');
  if (vivo) {
    const celda = (lbl, val, sub) => {
      const d = document.createElement('div'); d.className = 'kpi';
      const a = document.createElement('span'); a.className = 'lbl'; a.textContent = lbl;
      const b = document.createElement('span'); b.className = 'val'; b.textContent = val;
      d.append(a, b);
      for (const linea of [].concat(sub)) { const c = document.createElement('span'); c.className = 'muted sm'; c.textContent = linea; d.append(c); }
      return d;
    };
    const pintar = async () => {
      try {
        const r = await fetch('api?accion=en_vivo', { credentials: 'same-origin' });
        if (!r.ok) return;
        const d = await r.json();
        vivo.textContent = '';
        for (const j of Object.values(d.jornadas)) {
          vivo.append(celda((j.nombre === 'Diurna' ? '☀️ ' : '🌙 ') + 'Jornada ' + j.nombre, j.entregados + ' entregados',
            [`${j.aprobados} por entregar · ${j.solicitados} en revisión · ${j.libres} cupos libres de ${j.cupos}`,
             `Becados ${j.categorias.becado.pedidos}/${j.categorias.becado.reserva} · Convocatoria ${j.categorias.convocatoria.pedidos}/${j.categorias.convocatoria.reserva} · Regulares ${j.categorias.regular.pedidos}`,
             j.sobrantes_abiertos ? 'Sobrantes abiertos' : `Sobrantes desde las ${j.sobrantes_desde}`]));
        }
        txt(document.getElementById('vivo-hora'), 'actualizado ' + d.hora);
      } catch { /* reintenta en el siguiente ciclo */ }
    };
    pintar(); setInterval(pintar, 10000);
  }
})();
