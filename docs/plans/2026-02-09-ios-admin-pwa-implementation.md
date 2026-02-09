# iOS /admin PWA Hardening Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make the iOS-installed `/admin` PWA reliable and more “native” by fixing service worker + iOS meta correctness, and improving repeat-launch performance (assets-only caching, no stale admin data).

**Architecture:** Keep `tomatophp/filament-pwa` for routes/injection, override its views for iOS correctness, replace `public/serviceworker.js` with an assets-only caching SW, and remove large inlined CSS from the Filament HTML head.

**Tech Stack:** Laravel + Filament, Vite, vanilla Service Worker, Blade view overrides.

### Task 1: Add Filament PWA View Overrides (Meta + Offline)

**Files:**
- Create: `resources/views/vendor/filament-pwa/meta.blade.php`
- Create: `resources/views/vendor/filament-pwa/offline.blade.php`

**Step 1: Write a failing test**

Create `tests/Feature/PwaMetaTest.php`:

```php
<?php

use TomatoPHP\FilamentPWA\Services\ManifestService;

it('renders meta with a valid iOS status bar style', function () {
    $config = ManifestService::generate();

    // Force legacy/invalid style to simulate current production data shape.
    $config['status_bar'] = '#000000';

    $html = view('filament-pwa::meta', ['config' => $config])->render();

    expect($html)->toContain('name="apple-mobile-web-app-status-bar-style"');
    expect($html)->toMatch('/content="(default|black|black-translucent)"/');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PwaMetaTest.php`
Expected: FAIL (until view override is in place and normalizes value).

**Step 3: Write minimal implementation**

- Implement `resources/views/vendor/filament-pwa/meta.blade.php`:
  - Keep existing tags but compute a `$statusBarStyle` that maps any hex/unknown values to `default`.
  - Register SW with `scope: '/'` and remove/limit console logs.
- Implement `resources/views/vendor/filament-pwa/offline.blade.php`:
  - Simple, safe offline page (no auth/data).

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/PwaMetaTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Feature/PwaMetaTest.php resources/views/vendor/filament-pwa/meta.blade.php resources/views/vendor/filament-pwa/offline.blade.php
git commit -m "pwa: override filament-pwa meta and offline views for iOS"
```

### Task 2: Replace `public/serviceworker.js` With Assets-Only Caching

**Files:**
- Modify: `public/serviceworker.js`

**Step 1: Write the failing test**

Create `tests/Feature/ServiceWorkerTest.php`:

```php
<?php

it('ships a syntactically valid service worker file', function () {
    $path = public_path('serviceworker.js');
    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);

    // Basic sanity checks (guards against the current broken file).
    expect($contents)->toContain("self.addEventListener('fetch'");
    expect($contents)->toContain('/offline/');
    expect($contents)->not->toContain("\n\"\n");
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ServiceWorkerTest.php`
Expected: FAIL (until `public/serviceworker.js` is replaced).

**Step 3: Write minimal implementation**

Replace `public/serviceworker.js` with a SW that:
- Precaches `/offline/`, `/build/manifest.json`, `/images/icons/*` (default icons/splashes), and root icons (`/apple-touch-icon.png`, `/android-chrome-192x192.png`, etc. if present).
- At install, fetches `/build/manifest.json` and caches all referenced `file`, `css`, and `assets`.
- Fetch handling:
  - Ignore non-GET requests.
  - `mode: navigate` => network-first, fallback to cached `/offline/`.
  - Static assets (same-origin; destination in `script|style|image|font`) => cache-first with background refresh.
- Lifecycle:
  - `skipWaiting` on install
  - `clients.claim` on activate
  - optionally enable `navigationPreload` if supported

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/ServiceWorkerTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add public/serviceworker.js tests/Feature/ServiceWorkerTest.php
git commit -m "pwa: replace service worker with assets-only caching"
```

### Task 3: Remove Large Inline CSS From Filament Head (Cacheable CSS)

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `vite.config.js`
- (Optional) Create: `resources/css/filament/admin.css`

**Step 1: Write the failing test**

Create `tests/Feature/AdminHeadTest.php`:

```php
<?php

it('does not inline large filament css blobs in the admin head hook', function () {
    $src = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    expect($src)->not->toContain('file_get_contents(resource_path(\'css/filament/chat.css\'))');
    expect($src)->not->toContain('file_get_contents(resource_path(\'css/filament/ide.css\'))');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AdminHeadTest.php`
Expected: FAIL (until inline CSS is removed).

**Step 3: Write minimal implementation**

- Add a Vite CSS entry for Filament custom styles (either by importing both CSS files into a single `resources/css/filament/admin.css`, or by adding both files as inputs).
- Update `app/Providers/Filament/AdminPanelProvider.php` to:
  - Remove inline `<style>` concatenation.
  - Inject the CSS via `@vite(...)` from `PanelsRenderHook::HEAD_END` (so it’s cached and SW-cacheable).

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/AdminHeadTest.php`
Expected: PASS

**Step 5: Run build to verify assets exist**

Run: `npm run build`
Expected: Vite build completes successfully.

**Step 6: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php vite.config.js resources/css/filament/admin.css tests/Feature/AdminHeadTest.php
git commit -m "perf: serve filament custom css as cacheable assets"
```

### Task 4: Fix PWA Settings UI for iOS Status Bar Style

**Files:**
- Modify: `app/Filament/Pages/PWASettingsPage.php`
- Modify: `database/migrations/2025_01_23_222011_pwa_settings.php` (only if safe) OR add a new settings migration

**Step 1: Write the failing test**

Create `tests/Feature/PwaSettingsFormTest.php`:

```php
<?php

it('uses a constrained status bar style field instead of a color picker', function () {
    $src = file_get_contents(app_path('Filament/Pages/PWASettingsPage.php'));
    expect($src)->not->toContain(\"ColorPicker::make('pwa_status_bar')\");
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/PwaSettingsFormTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

- Replace the status bar ColorPicker with a Select/TextInput constrained to:
  - `default`
  - `black`
  - `black-translucent`
- Keep `theme_color` as the actual color picker.
- Keep backward compatibility by leaving the meta view normalization from Task 1.

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/PwaSettingsFormTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Filament/Pages/PWASettingsPage.php tests/Feature/PwaSettingsFormTest.php
git commit -m "pwa: constrain iOS status bar style setting"
```

### Task 5: End-to-End Verification (Local)

**Files:**
- (No code changes required)

**Step 1: Run full PHP test suite**

Run: `php artisan test`
Expected: PASS

**Step 2: Build assets**

Run: `npm run build`
Expected: PASS

**Step 3: Smoke-check PWA endpoints**

Run:

```bash
php artisan route:list | rg \"manifest\\.json|serviceworker\\.js|offline\" || true
curl -fsS http://localhost:8000/manifest.json | head
curl -fsS http://localhost:8000/serviceworker.js | head
```

Expected:
- Routes exist
- SW JS is served and contains `/offline/`

