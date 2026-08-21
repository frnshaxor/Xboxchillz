// Arsip Layar — Service Worker
// Cache-first for static assets, network-first for dynamic content
const CACHE_NAME = 'arsip-v3';
const STATIC_ASSETS = [
  '/',
  '/assets/css/style.css',
  '/assets/js/vue_enhance.js',
  '/assets/plyr.svg',
  'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght,SOFT,WONK@9..144,300..900,0..100,0..1&family=Geist:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap',
  'https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.css',
  'https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js',
  'https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.min.js',
  'https://unpkg.com/vue@3.4.38/dist/vue.global.prod.js',
];

// Install: pre-cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch(() => {
        // Silently fail if some assets aren't available
      });
    })
  );
  self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      );
    })
  );
  self.clients.claim();
});

// Fetch: stale-while-revalidate for static, network-first for API/dynamic
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Skip non-GET requests
  if (event.request.method !== 'GET') return;

  // Skip API calls and PHP pages (network-first)
  if (url.pathname.includes('api.php') ||
      url.search.includes('page=') ||
      url.pathname.endsWith('.php') ||
      url.pathname.includes('/protected-media/')) {
    event.respondWith(
      fetch(event.request).catch(() => {
        return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
      })
    );
    return;
  }

  // Static assets: cache-first with network fallback
  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) return cached;
      return fetch(event.request).then((response) => {
        // Only cache successful responses from same origin or known CDNs
        if (!response.ok) return response;
        const respUrl = new URL(response.url);
        const isSameOrigin = respUrl.origin === url.origin;
        const isCDN = respUrl.hostname.includes('cdn.jsdelivr.net') ||
                      respUrl.hostname.includes('unpkg.com') ||
                      respUrl.hostname.includes('fonts.googleapis.com') ||
                      respUrl.hostname.includes('fonts.gstatic.com');
        if (isSameOrigin || isCDN) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        }
        return response;
      }).catch(() => {
        // Offline fallback for navigation
        if (event.request.mode === 'navigate') {
          return caches.match('/');
        }
        return new Response('', { status: 504 });
      });
    })
  );
});
