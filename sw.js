// Service worker de GAFA.
// Guarda solo archivos estaticos (estilos, scripts, imagenes) para que abra rapido.
// Las paginas PHP NUNCA se guardan: tienen datos de la empresa y dependen de la sesion.
const VERSION = 'gafa-v2';
const ESTATICOS = [
  'sin-conexion.html',
  'pwa/icon-192.png',
  'pwa/icon-512.png',
  'Css/tema.css',
  'Css/movil.css',
  'estilos.css',
  'pwa/movil.js'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(ESTATICOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((ks) => Promise.all(ks.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Paginas: siempre de la red; si no hay conexion, la pantalla "sin conexion"
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('sin-conexion.html')));
    return;
  }

  // CSS y JS: primero la red (asi las actualizaciones llegan enseguida); sin conexion, la copia guardada
  if (/\.(css|js)$/i.test(url.pathname)) {
    e.respondWith(
      fetch(req).then((r) => {
        if (r && r.ok) { const copia = r.clone(); caches.open(VERSION).then((c) => c.put(req, copia)); }
        return r;
      }).catch(() => caches.match(req))
    );
    return;
  }

  // Imagenes y fuentes: de la copia guardada, y se refrescan en segundo plano
  if (/\.(png|jpe?g|svg|ico|webp|woff2?)$/i.test(url.pathname)) {
    e.respondWith(
      caches.open(VERSION).then((c) =>
        c.match(req).then((hit) => {
          const red = fetch(req).then((r) => {
            if (r && r.ok) c.put(req, r.clone());
            return r;
          }).catch(() => hit);
          return hit || red;
        })
      )
    );
  }
});
