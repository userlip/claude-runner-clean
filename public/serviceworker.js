/* Service Worker (assets-only caching)
 *
 * Principles:
 * - Never cache HTML/data for /admin (avoid stale/sensitive content).
 * - Cache static assets (Vite build assets, Filament assets, icons, splash).
 * - Navigations are network-first; offline falls back to /offline/.
 */

const CACHE_PREFIX = 'claude-runner-pwa';
const PRECACHE_NAME = `${CACHE_PREFIX}-precache-v1`;
const RUNTIME_NAME = `${CACHE_PREFIX}-runtime-v1`;
const OFFLINE_URL = '/offline/';

const STATIC_URLS = [
  OFFLINE_URL,
  '/build/manifest.json',
  '/apple-touch-icon.png',
  '/android-chrome-192x192.png',
  '/android-chrome-512x512.png',
  '/favicon.ico',
  '/favicon-16x16.png',
  '/favicon-32x32.png',
  '/images/icons/icon-72x72.png',
  '/images/icons/icon-96x96.png',
  '/images/icons/icon-128x128.png',
  '/images/icons/icon-144x144.png',
  '/images/icons/icon-152x152.png',
  '/images/icons/icon-192x192.png',
  '/images/icons/icon-384x384.png',
  '/images/icons/icon-512x512.png',
  '/images/icons/splash-640x1136.png',
  '/images/icons/splash-750x1334.png',
  '/images/icons/splash-828x1792.png',
  '/images/icons/splash-1125x2436.png',
  '/images/icons/splash-1242x2208.png',
  '/images/icons/splash-1242x2688.png',
  '/images/icons/splash-1536x2048.png',
  '/images/icons/splash-1668x2224.png',
  '/images/icons/splash-1668x2388.png',
  '/images/icons/splash-2048x2732.png',
];

function isSameOrigin(url) {
  return url.origin === self.location.origin;
}

function isStaticAssetRequest(requestUrl, destination) {
  // Vite build assets are immutable (hashed).
  if (requestUrl.pathname.startsWith('/build/assets/')) return true;

  // Filament static assets are safe to cache and significantly improve iOS repeat launches.
  if (requestUrl.pathname.startsWith('/css/filament/')) return true;
  if (requestUrl.pathname.startsWith('/js/filament/')) return true;

  // Default asset destinations.
  return destination === 'style' || destination === 'script' || destination === 'image' || destination === 'font';
}

async function cachePutIfOk(cache, request, response) {
  if (!response || !response.ok) return;
  // Avoid caching opaque responses (e.g. cross-origin).
  if (response.type === 'opaque') return;
  await cache.put(request, response.clone());
}

async function addUrlBestEffort(cache, url) {
  try {
    await cache.add(new Request(url, { cache: 'reload' }));
  } catch {
    // Best effort only (install should not fail if one asset is missing).
  }
}

async function precacheViteBuildAssets(cache) {
  let manifest;
  try {
    const res = await fetch('/build/manifest.json', { cache: 'no-store' });
    if (!res.ok) return;
    manifest = await res.json();
  } catch {
    return;
  }

  const urls = new Set();
  for (const entry of Object.values(manifest)) {
    if (entry?.file) urls.add(`/build/${entry.file}`);
    if (Array.isArray(entry?.css)) entry.css.forEach((p) => urls.add(`/build/${p}`));
    if (Array.isArray(entry?.assets)) entry.assets.forEach((p) => urls.add(`/build/${p}`));
  }

  await Promise.all([...urls].map((u) => addUrlBestEffort(cache, u)));
}

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    (async () => {
      const cache = await caches.open(PRECACHE_NAME);
      await Promise.all(STATIC_URLS.map((u) => addUrlBestEffort(cache, u)));
      await precacheViteBuildAssets(cache);
    })(),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(
        keys
          .filter((k) => k.startsWith(CACHE_PREFIX))
          .filter((k) => k !== PRECACHE_NAME && k !== RUNTIME_NAME)
          .map((k) => caches.delete(k)),
      );

      if (self.registration.navigationPreload) {
        try {
          await self.registration.navigationPreload.enable();
        } catch {
          // Ignore unsupported/failed preload enablement.
        }
      }

      await self.clients.claim();
    })(),
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (!isSameOrigin(url)) return;

  // Never cache /admin HTML. Offline falls back to the offline page.
  const isNavigate = request.mode === 'navigate' || request.destination === 'document';
  if (isNavigate) {
    event.respondWith(
      (async () => {
        try {
          const preload = await event.preloadResponse;
          if (preload) return preload;
          return await fetch(request);
        } catch {
          const cache = await caches.open(PRECACHE_NAME);
          return (await cache.match(OFFLINE_URL)) || Response.error();
        }
      })(),
    );
    return;
  }

  // Cache static assets to improve iOS repeat-load performance.
  if (isStaticAssetRequest(url, request.destination)) {
    event.respondWith(
      (async () => {
        const cache = await caches.open(RUNTIME_NAME);
        const cached = await cache.match(request);
        const fetchPromise = fetch(request)
          .then((response) => cachePutIfOk(cache, request, response).then(() => response))
          .catch(() => undefined);

        return cached || (await fetchPromise) || Response.error();
      })(),
    );
    return;
  }
});

self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

