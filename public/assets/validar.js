/* Canje de bonos con soporte sin conexión.
   - Con conexión: cada canje se valida en el servidor (un bono solo se puede canjear una vez, garantizado en la base de datos).
   - Sin conexión: se valida contra la lista del día descargada en este dispositivo, se guarda en una cola y se sincroniza
     al volver la red. Cada canje lleva un uuid, así reenviarlo nunca lo duplica. */
(() => {
  const $ = s => document.querySelector(s);
  const KEY_LISTA = 'bonos_lista_v2', KEY_COLA = 'bonos_cola_v2', KEY_USADOS = 'bonos_usados_v2', KEY_CONF = 'bonos_conf_v2';
  const csrf = document.querySelector('meta[name=csrf]').content;
  const load = (k, d) => { try { const v = JSON.parse(localStorage.getItem(k)); return v ?? d; } catch { return d; } };
  const save = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* almacenamiento lleno o bloqueado */ } };
  const uuid = () => (crypto.randomUUID ? crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); }));

  let lista = load(KEY_LISTA, null);          // {fecha, items:[{b,e,n,j,s}]}
  let cola = load(KEY_COLA, []);              // canjes sin conexión pendientes
  let usados = new Set(load(KEY_USADOS, [])); // códigos de bono canjeados localmente
  let conflictos = load(KEY_CONF, []);
  let sincronizando = false;

  async function api(path, opts = {}) {
    const ctl = new AbortController();
    const t = setTimeout(() => ctl.abort(), 6000);
    try {
      const r = await fetch(path, { credentials: 'same-origin', signal: ctl.signal,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, ...opts });
      let data = null; try { data = await r.json(); } catch { /* sin cuerpo */ }
      return { status: r.status, data };
    } finally { clearTimeout(t); }
  }

  function mostrar(msg, tipo) {
    const el = $('#res'); el.hidden = false; el.className = 'res ' + tipo; el.textContent = msg;
  }

  function pintarEstado() {
    const online = navigator.onLine && !window.__sinRed;
    const c = $('#conn'); c.textContent = online ? 'En línea' : 'Sin conexión'; c.className = 'pill ' + (online ? 'ok' : 'warn');
    $('#cola-n').textContent = cola.length; $('#cola-pill').hidden = cola.length === 0; $('#btn-sync').hidden = cola.length === 0;
    const cf = $('#conflictos'); cf.hidden = conflictos.length === 0;
    const ul = $('#conf-lista'); ul.textContent = '';
    conflictos.forEach(x => { const li = document.createElement('li'); li.textContent = `${x.codigo}${x.nombre ? ' (' + x.nombre + ')' : ''}: ${x.msg}`; ul.appendChild(li); });
    const info = $('#lista-info');
    if (lista && lista.fecha === window.VALIDAR.hoy) info.textContent = `Lista del día guardada en este dispositivo (${lista.items.length} bonos, actualizada ${new Date(lista.ts).toLocaleTimeString('es-CO', {hour:'2-digit', minute:'2-digit'})}).`;
    else info.textContent = 'Aún no hay lista del día en este dispositivo: conéctate una vez para descargarla.';
  }

  async function bajarLista() {
    try {
      const { status, data } = await api('api?accion=lista_dia');
      if (status === 200 && data && data.items) {
        lista = { fecha: data.fecha, items: data.items, ts: Date.now() };
        save(KEY_LISTA, lista);
        if (usados.size) { // los que el servidor ya marca como entregados dejan de ser "locales"
          const entregados = new Set(data.items.filter(i => i.s === 'entregado').map(i => i.b));
          usados = new Set([...usados].filter(c => !entregados.has(c))); save(KEY_USADOS, [...usados]);
        }
      }
    } catch { /* sin red */ }
    pintarEstado();
  }

  async function sincronizar() {
    if (sincronizando || !cola.length) return;
    sincronizando = true;
    try {
      const { status, data } = await api('api?accion=sync', { method: 'POST', body: JSON.stringify({ items: cola }) });
      if (status === 401 || status === 403 || status === 419) {
        mostrar('Tu sesión venció. Inicia sesión de nuevo: los canjes pendientes se conservan en este dispositivo.', 'warn');
      } else if (status === 200 && data && data.resultados) {
        const porUuid = Object.fromEntries(data.resultados.map(r => [r.uuid, r]));
        const quedan = [];
        for (const it of cola) {
          const r = porUuid[it.uuid];
          if (!r) { quedan.push(it); continue; }
          if (r.estado !== 'ok') { conflictos.push({ codigo: it.codigo, nombre: it.nombre, msg: r.msg }); }
          usados.delete(it.codigo);
        }
        cola = quedan; save(KEY_COLA, cola); save(KEY_CONF, conflictos); save(KEY_USADOS, [...usados]);
        await bajarLista();
      }
    } catch { /* sigue sin red: se reintenta */ }
    sincronizando = false; pintarEstado();
  }

  function canjearLocal(norm) {
    if (!lista || lista.fecha !== window.VALIDAR.hoy) return mostrar('Sin conexión y sin lista del día en este dispositivo. No se puede validar.', 'err');
    const it = lista.items.find(i => i.b === norm || i.e === norm);
    if (!it) return mostrar(`“${norm}” no está en la lista descargada. Sin conexión no se puede confirmar.`, 'err');
    if (it.s === 'solicitado') return mostrar(`${it.n}: la solicitud aún no ha sido aprobada.`, 'err');
    if (it.s === 'entregado' || usados.has(it.b)) return mostrar(`${it.n} ya recibió este bono. NO entregar.`, 'err');
    usados.add(it.b); save(KEY_USADOS, [...usados]);
    cola.push({ uuid: uuid(), codigo: it.b, nombre: it.n, fecha: lista.fecha, ts: Date.now() }); save(KEY_COLA, cola);
    mostrar(`✔ ${it.n} — entrega registrada SIN CONEXIÓN. Se sincronizará al volver la red.`, 'warn');
  }

  async function canjear(entrada) {
    const norm = entrada.trim().toUpperCase();
    if (!norm) return;
    if (!navigator.onLine || window.__sinRed) { canjearLocal(norm); pintarEstado(); return; }
    try {
      const { status, data } = await api('api?accion=canjear', { method: 'POST', body: JSON.stringify({ codigo: norm, uuid: uuid() }) });
      if (status === 401 || status === 403 || status === 419) return mostrar('Tu sesión venció. Inicia sesión de nuevo.', 'err');
      if (data) {
        mostrar((data.ok ? '✔ ' : '✖ ') + data.msg, data.ok ? 'ok' : 'err');
        if (lista) { const it = lista.items.find(i => i.b === norm || i.e === norm); if (it && (data.ok || data.estado === 'ya_entregado')) { it.s = 'entregado'; save(KEY_LISTA, lista); } }
      }
    } catch { canjearLocal(norm); } // la red falló a mitad: se trata como sin conexión
    pintarEstado();
  }

  $('#f-canje').addEventListener('submit', e => {
    e.preventDefault(); const i = $('#codigo'); canjear(i.value); i.value = ''; i.focus();
  });
  $('#btn-sync').addEventListener('click', sincronizar);
  $('#conf-ok').addEventListener('click', () => { conflictos = []; save(KEY_CONF, conflictos); pintarEstado(); });
  window.addEventListener('online', () => { pintarEstado(); sincronizar(); bajarLista(); });
  window.addEventListener('offline', pintarEstado);
  setInterval(() => { if (navigator.onLine && !window.__sinRed) { sincronizar(); if (!cola.length) bajarLista(); } }, 20000);

  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
  pintarEstado(); bajarLista(); sincronizar();
})();
