// VERSION: 2
self.addEventListener('install', e => {
  // No activar automáticamente: esperar a que el usuario confirme la actualización
});
self.addEventListener('message', e => {
  if (e.data && e.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
self.addEventListener('activate', e => e.waitUntil(clients.claim()));
self.addEventListener('fetch', e => {
  // No interceptar peticiones a otros dominios (Firestore, Google APIs, CDNs, etc.)
  // Interceptarlas rompe las conexiones en tiempo real (streaming) de Firestore.
  const url = new URL(e.request.url);
  if (url.origin !== self.location.origin) return;
  e.respondWith(fetch(e.request));
});
