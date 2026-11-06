const cacheName = 'vpr-v4'; // Increment cache name to trigger update
const assetsToCache = [
  '/',
  './manifest.json',
  // Add critical CSS files
  './assets/css/dashboard.css',
  './assets/css/meeting-room.css',
  './assets/css/style.css',
  // Add critical JS files (if any, will need to identify)
  // './assets/js/some_script.js',
  // Add common image assets
  './assets/img/globe.png',
  './assets/img/icon-192x192.png',
  './assets/img/icon-512x512.png'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(cacheName)
      .then(cache => {
        console.log('[Service Worker] Caching all assets');
        return cache.addAll(assetsToCache);
      })
      .catch(error => console.error('[Service Worker] Failed to cache assets:', error))
  );
  self.skipWaiting(); // Force the new service worker to activate immediately
});

self.addEventListener('activate', event => {
  console.log('[Service Worker] Activating new service worker.');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(name => {
          if (name !== cacheName) {
            console.log('[Service Worker] Deleting old cache:', name);
            return caches.delete(name);
          }
        })
      );
    })
  );
  event.waitUntil(clients.claim()); // Take control of un-controlled clients
});

self.addEventListener('fetch', event => {
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => {
        return caches.match(event.request);
      })
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then(response => {
      if (response) {
        return response;
      }

      return fetch(event.request)
        .then(networkResponse => {
          if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
            const responseToCache = networkResponse.clone();
            caches.open(cacheName).then(cache => {
              cache.put(event.request, responseToCache);
            });
          }
          return networkResponse;
        })
        .catch(error => {
          console.error('[Service Worker] Fetch failed:', event.request.url, error);
          return new Response('<h1>Offline</h1><p>Please check your internet connection.</p>', {
            headers: { 'Content-Type': 'text/html' }
          });
        });
    })
  );
});
