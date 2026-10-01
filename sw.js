// ════════════════════════════════════════════════════════════════════════
// FlotaMancha · Service worker
// · La app (index.html) se carga SIEMPRE de internet cuando hay conexión,
//   así cada versión nueva que se sube a GitHub llega sola a todos los aparatos.
//   Solo si no hay cobertura se usa la copia guardada.
// · Iconos, manifest y tabla ADR: se sirven de la copia y se renuevan por detrás.
// · Firebase, librerías externas, etc.: no se tocan (van directas a internet).
// © FlotaMancha · Desarrollada y diseñada por Rubén Díaz. Todos los derechos reservados.
// ════════════════════════════════════════════════════════════════════════
const CACHE = 'flotamancha-app-2';
const BASICOS = ['./', './index.html', './manifest.json', './icon.png', './icon-192.png', './icon-512.png',
                 './icon-maskable-512.png', './apple-touch-icon.png', './favicon-32.png', './adr.json'];

self.addEventListener('install', ev => {
  ev.waitUntil(caches.open(CACHE).then(c => Promise.all(BASICOS.map(u => c.add(new Request(u, {cache: 'reload'})).catch(() => {})))));
});

self.addEventListener('activate', ev => {
  ev.waitUntil((async () => {
    const nombres = await caches.keys();
    await Promise.all(nombres.filter(n => n !== CACHE).map(n => caches.delete(n)));   // fuera las copias de versiones antiguas
    await self.clients.claim();
  })());
});

self.addEventListener('message', ev => {
  if (ev.data && ev.data.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', ev => {
  const req = ev.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;                 // Firebase, CDN…: directo a internet

  const esApp = req.mode === 'navigate' || url.pathname.endsWith('/') || url.pathname.endsWith('.html');
  if (esApp) {
    // Primero internet (versión más nueva); si no hay cobertura, la copia guardada
    ev.respondWith((async () => {
      try {
        const r = await fetch(req, {cache: 'no-store'});
        if (r && r.ok) { const c = await caches.open(CACHE); c.put('./index.html', r.clone()); }
        return r;
      } catch (e) {
        return (await caches.match('./index.html')) || (await caches.match('./')) || Response.error();
      }
    })());
    return;
  }
  // Resto de archivos propios: la copia al momento y se renueva por detrás
  ev.respondWith((async () => {
    const c = await caches.open(CACHE);
    const guardada = await c.match(req, {ignoreSearch: true});
    const nueva = fetch(req, {cache: 'no-store'}).then(r => { if (r && r.ok) c.put(req, r.clone()); return r; }).catch(() => null);
    return guardada || (await nueva) || Response.error();
  })());
});
