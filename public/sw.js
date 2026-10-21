// HRIS service worker: makes the app installable, loads faster, and shows a friendly page when offline.
// Pages with personal data are never cached; only the app's own static files are.
const VERSION = 'hris-v2';
const STATIC = ['/offline', '/css/app.css?v=9', '/images/app-icon-192.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Pages: always from the network (they hold personal data); offline page if there is no connection.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('/offline')));
    return;
  }

  // Static files (styles, scripts, face recognition models, icons): cache first, refresh in the background.
  if (/^\/(css|js|vendor|images)\//.test(url.pathname)) {
    event.respondWith(caches.open(VERSION).then((cache) => cache.match(req).then((hit) => {
      const fresh = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
      return hit || fresh;
    })));
  }
});
