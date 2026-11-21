// Permite abrir la pantalla de entrega sin conexión (solo funciona en https:// o http://localhost).
const CACHE = 'bonos-validar-v3';
const SHELL = ['validar', 'assets/style.css', 'assets/app.js', 'assets/validar.js'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).catch(() => {}));
  self.skipWaiting();
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))));
  self.clients.claim();
});
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.pathname.endsWith('/api')) return;
  const esShell = SHELL.some(p => url.pathname.endsWith('/' + p));
  if (!esShell) return;
  // red primero; si falla, lo guardado
  e.respondWith(fetch(e.request).then(r => { const c = r.clone(); caches.open(CACHE).then(x => x.put(e.request, c)); return r; })
    .catch(() => caches.match(e.request, { ignoreSearch: true })));
});
