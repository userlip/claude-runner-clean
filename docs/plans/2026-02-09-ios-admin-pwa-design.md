# iOS /admin PWA Design (Claude Runner)

**Scope:** iOS (Safari “Add to Home Screen”) installed PWA for the Filament `/admin` experience.

## Goals

- Faster repeat launches and better perceived performance on iOS.
- “Native app” presentation (standalone, correct status bar behavior, proper splash/icons).
- Conservative offline behavior: cache **static assets only**; never cache admin HTML/data.

## Current Findings

- Filament PWA meta is injected via `tomatophp/filament-pwa` on the Filament panel.
- The deployed service worker file `public/serviceworker.js` is **malformed** and caches paths that do not exist (`/css/app.css`, `/js/app.js`). This makes PWA behavior unreliable.
- The package’s iOS meta uses `apple-mobile-web-app-status-bar-style`, but the app settings currently store this as a hex color; iOS only accepts `default`, `black`, or `black-translucent`.
- `/admin` HTML currently inlines ~82KB of CSS via `file_get_contents(...)`, which hurts TTFB/parse time and prevents caching.

## Proposed Architecture

### Manifest + Meta (iOS correctness)

- Keep `tomatophp/filament-pwa` for manifest route (`/manifest.json`) and meta injection, but override the package views in `resources/views/vendor/filament-pwa/` to:
  - Map any legacy `status_bar` values to a valid iOS status-bar style.
  - Register the service worker with a safe scope (`/`) and avoid noisy console logging in production.
  - Provide a clean offline page view.

### Service Worker (assets-only caching)

- Replace `public/serviceworker.js` with an iOS-friendly SW that:
  - Precaches:
    - `/offline/`
    - Vite build entries by reading `/build/manifest.json` at install time (JS/CSS/assets referenced by manifest)
    - App icons and default splash assets under `/images/icons/*`
  - Uses `network-first` for navigations, falling back to cached `/offline/` when offline.
  - Uses `cache-first` (or stale-while-revalidate) for same-origin static assets (scripts/styles/images/fonts), including Filament assets under `/css/filament/` and `/js/filament/`.
  - Implements sane lifecycle controls: `skipWaiting`, `clients.claim`, optional `navigationPreload` (feature-gated).

### HTML/CSS Performance

- Stop inlining large CSS blobs into Filament HTML.
- Serve those styles as external, cacheable CSS via Vite (or a static file), and have the service worker cache them.

## Non-Goals (for this pass)

- Offline-capable admin flows (data caching, write-queueing, conflict resolution).
- Workbox integration (can be a follow-up if we want automatic precache manifests).

