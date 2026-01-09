# Code Server IDE View Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a new IDE view page that embeds code-server (VS Code) alongside the chat panel for a full development environment experience.

**Architecture:** code-server runs as a systemd service on localhost:8443 with no auth. Nginx proxies `/ide-proxy/*` to code-server, gated by a Laravel auth check endpoint. A new Filament page at `/tasks/{id}/ide` shows a split layout with the embedded editor and existing chat component.

**Tech Stack:** code-server, Nginx proxy with auth_request, Filament 4 page, Livewire 3, CSS Grid for split layout.

---

## Phase 1: Server Infrastructure

### Task 1: Create Laravel Auth Check Endpoint

**Files:**
- Modify: `routes/web.php`

**Step 1: Add the auth check route**

Edit `routes/web.php` to add:

```php
Route::get('/ide-auth-check', function () {
    return auth()->check() ? response('OK') : response('Unauthorized', 401);
})->middleware('web')->name('ide.auth-check');
```

**Step 2: Verify route is registered**

Run: `php artisan route:list --name=ide`

Expected: Shows the `ide.auth-check` route with `web` middleware.

**Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat(ide): add auth check endpoint for code-server proxy"
```

---

### Task 2: Document Server Setup Instructions

**Files:**
- Create: `docs/server-setup/code-server.md`

**Step 1: Create the setup documentation**

```markdown
# Code Server Setup

## Installation

Install code-server on the server:

```bash
curl -fsSL https://code-server.dev/install.sh | sh
```

## Systemd Service

Create `/etc/systemd/system/code-server.service`:

```ini
[Unit]
Description=code-server
After=network.target

[Service]
Type=exec
User=ploi
WorkingDirectory=/home/ploi
ExecStart=/usr/bin/code-server --bind-addr 127.0.0.1:8443 --auth none
Restart=always
Environment=HOME=/home/ploi

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl enable code-server
sudo systemctl start code-server
```

## Nginx Configuration

Add this to your site's Nginx config in Ploi (before the main location block):

```nginx
# IDE Auth Check
location = /ide-auth-check {
    internal;
    proxy_pass http://127.0.0.1:8000/ide-auth-check;
    proxy_pass_request_body off;
    proxy_set_header Content-Length "";
    proxy_set_header X-Original-URI $request_uri;
    proxy_set_header Cookie $http_cookie;
}

# Code Server Proxy
location /ide-proxy/ {
    auth_request /ide-auth-check;

    proxy_pass http://127.0.0.1:8443/;
    proxy_http_version 1.1;

    # WebSocket support
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # Longer timeouts for IDE
    proxy_read_timeout 86400;
    proxy_send_timeout 86400;
}
```

## Verification

1. Start code-server: `sudo systemctl start code-server`
2. Check status: `sudo systemctl status code-server`
3. Test locally: `curl http://127.0.0.1:8443` (should return HTML)
4. Test via proxy (when logged in): Navigate to `/ide-proxy/` in browser
```

**Step 2: Commit**

```bash
git add docs/server-setup/code-server.md
git commit -m "docs(ide): add code-server setup instructions"
```

---

## Phase 2: Filament Page

### Task 3: Create TaskIde Filament Page

**Files:**
- Create: `app/Filament/Resources/Tasks/Pages/TaskIde.php`

**Step 1: Create the page class**

Create `app/Filament/Resources/Tasks/Pages/TaskIde.php`:

```php
<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;

class TaskIde extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TaskResource::class;

    protected static ?string $slug = 'ide';

    protected string $view = 'filament.resources.tasks.task-resource.pages.task-ide';

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'IDE - ' . ($this->record->title ?? 'Task #' . $this->record->id);
    }

    public function getIdeUrl(): string
    {
        $folder = $this->record->workspace_path
            ?? $this->record->site?->path
            ?? '/home/ploi';

        return '/ide-proxy/?' . http_build_query([
            'folder' => $folder,
        ]);
    }
}
```

**Step 2: Verify file syntax**

Run: `php -l app/Filament/Resources/Tasks/Pages/TaskIde.php`

Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Filament/Resources/Tasks/Pages/TaskIde.php
git commit -m "feat(ide): create TaskIde Filament page"
```

---

### Task 4: Register TaskIde in TaskResource

**Files:**
- Modify: `app/Filament/Resources/Tasks/TaskResource.php`

**Step 1: Add import for TaskIde**

Add to the imports section at the top:

```php
use App\Filament\Resources\Tasks\Pages\TaskIde;
```

**Step 2: Register the page route**

In the `getPages()` method, add the IDE page:

```php
public static function getPages(): array
{
    return [
        'index' => ListTasks::route('/'),
        'create' => CreateTask::route('/create'),
        'chat' => TaskChat::route('/{record}/chat'),
        'ide' => TaskIde::route('/{record}/ide'),
    ];
}
```

**Step 3: Verify routes are registered**

Run: `php artisan route:list | grep ide`

Expected: Shows the `/admin/tasks/{record}/ide` route.

**Step 4: Commit**

```bash
git add app/Filament/Resources/Tasks/TaskResource.php
git commit -m "feat(ide): register TaskIde page in TaskResource"
```

---

## Phase 3: Views & Styling

### Task 5: Create IDE Page Blade View

**Files:**
- Create: `resources/views/filament/resources/tasks/task-resource/pages/task-ide.blade.php`

**Step 1: Create the view**

```blade
<x-filament-panels::page>
    <div
        class="ide-page-layout"
        x-data="{
            editorWidth: 70,
            isDragging: false,
            startX: 0,
            startWidth: 0,
            startDrag(e) {
                this.isDragging = true;
                this.startX = e.clientX;
                this.startWidth = this.editorWidth;
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';
            },
            onDrag(e) {
                if (!this.isDragging) return;
                const container = this.$el;
                const containerWidth = container.offsetWidth;
                const delta = e.clientX - this.startX;
                const deltaPercent = (delta / containerWidth) * 100;
                this.editorWidth = Math.min(85, Math.max(30, this.startWidth + deltaPercent));
            },
            stopDrag() {
                this.isDragging = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        }"
        x-init="document.body.classList.add('ide-immersive-mode')"
        x-on:mousemove.window="onDrag($event)"
        x-on:mouseup.window="stopDrag()"
    >
        {{-- Editor Panel --}}
        <div class="ide-editor-panel" :style="'width: ' + editorWidth + '%'">
            <div class="ide-editor-header">
                <a href="{{ \App\Filament\Resources\Tasks\Pages\TaskChat::getUrl(['record' => $this->getRecord()]) }}" class="ide-back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="ide-back-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Back to Chat
                </a>
                <span class="ide-header-title">{{ $this->getRecord()->title ?? 'Task #' . $this->getRecord()->id }}</span>
            </div>
            <iframe
                src="{{ $this->getIdeUrl() }}"
                class="ide-frame"
                allow="clipboard-read; clipboard-write"
            ></iframe>
        </div>

        {{-- Resize Handle --}}
        <div
            class="ide-resize-handle"
            x-on:mousedown.prevent="startDrag($event)"
            :class="{ 'ide-resize-handle-active': isDragging }"
        ></div>

        {{-- Chat Panel --}}
        <div class="ide-chat-panel" :style="'width: ' + (100 - editorWidth) + '%'">
            @livewire('task-chat', ['task' => $this->getRecord()])
        </div>
    </div>
</x-filament-panels::page>
```

**Step 2: Commit**

```bash
git add resources/views/filament/resources/tasks/task-resource/pages/task-ide.blade.php
git commit -m "feat(ide): create IDE page blade view with split layout"
```

---

### Task 6: Create IDE Page CSS

**Files:**
- Create: `resources/css/filament/ide.css`

**Step 1: Create the CSS file**

```css
/* IDE page layout - Split view with code-server and chat */

/* Immersive mode hides Filament sidebar */
body.ide-immersive-mode .fi-main-sidebar {
    display: none;
}

body.ide-immersive-mode .fi-layout {
    --sidebar-width: 0px !important;
}

body.ide-immersive-mode .fi-main-ctn {
    margin-inline-start: 0 !important;
}

/* Main layout */
.ide-page-layout {
    display: flex;
    height: calc(100vh - 4rem);
    width: 100%;
    overflow: hidden;
    background-color: rgb(17 24 39);
}

/* Editor panel */
.ide-editor-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 300px;
    overflow: hidden;
}

.ide-editor-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 1rem;
    background-color: rgb(31 41 55);
    border-bottom: 1px solid rgb(55 65 81);
    flex-shrink: 0;
}

.ide-back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    color: rgb(156 163 175);
    font-size: 0.875rem;
    text-decoration: none;
    transition: color 0.15s;
}

.ide-back-link:hover {
    color: white;
}

.ide-back-icon {
    width: 1rem;
    height: 1rem;
}

.ide-header-title {
    color: rgb(209 213 219);
    font-size: 0.875rem;
    font-weight: 500;
}

.ide-frame {
    flex: 1;
    width: 100%;
    border: none;
    background-color: rgb(30 30 30);
}

/* Resize handle */
.ide-resize-handle {
    width: 6px;
    background-color: rgb(55 65 81);
    cursor: col-resize;
    flex-shrink: 0;
    transition: background-color 0.15s;
}

.ide-resize-handle:hover,
.ide-resize-handle-active {
    background-color: rgb(59 130 246);
}

/* Chat panel */
.ide-chat-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 280px;
    overflow: hidden;
    background-color: white;
}

.dark .ide-chat-panel {
    background-color: rgb(17 24 39);
}

/* Override chat container height in IDE mode */
.ide-chat-panel .chat-container {
    height: 100%;
}
```

**Step 2: Commit**

```bash
git add resources/css/filament/ide.css
git commit -m "feat(ide): add IDE page CSS styles"
```

---

### Task 7: Import IDE CSS in App CSS

**Files:**
- Modify: `resources/css/filament/app.css` (or wherever chat.css is imported)

**Step 1: Find where CSS is imported**

Run: `grep -r "chat.css" resources/`

**Step 2: Add the IDE CSS import**

Add this import alongside the chat.css import:

```css
@import 'ide.css';
```

**Step 3: Rebuild assets**

Run: `npm run build`

**Step 4: Commit**

```bash
git add resources/css/
git commit -m "feat(ide): import IDE CSS in app styles"
```

---

## Phase 4: Navigation

### Task 8: Add IDE Link to Chat Page Header

**Files:**
- Modify: `app/Livewire/TaskChat.php` (add method to get IDE URL)
- Modify: `resources/views/livewire/task-chat.blade.php` (add button)

**Step 1: Check if TaskChat needs a method for IDE URL**

The chat page view needs access to the IDE page URL. Since we're using Filament pages, we can use the static method directly in Blade.

**Step 2: Add the IDE button to chat header**

Find the chat header section in the task-chat blade file and add:

```blade
<a
    href="{{ \App\Filament\Resources\Tasks\Pages\TaskIde::getUrl(['record' => $task]) }}"
    class="chat-header-action"
    title="Open in IDE"
>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
    </svg>
</a>
```

**Step 3: Add CSS for the action button**

Add to `chat.css`:

```css
.chat-header-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    color: rgb(107 114 128);
    border-radius: 0.375rem;
    transition: all 0.15s;
}

.chat-header-action:hover {
    color: rgb(59 130 246);
    background-color: rgb(243 244 246);
}

.dark .chat-header-action:hover {
    background-color: rgb(31 41 55);
}
```

**Step 4: Commit**

```bash
git add resources/views/livewire/task-chat.blade.php resources/css/filament/chat.css
git commit -m "feat(ide): add IDE button to chat header"
```

---

## Phase 5: Testing

### Task 9: Create Feature Test for TaskIde Page

**Files:**
- Create: `tests/Feature/Filament/TaskIdeTest.php`

**Step 1: Create the test file**

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Tasks\Pages\TaskIde;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskIdeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ide_page_requires_authentication(): void
    {
        $task = Task::factory()->create();

        $this->get(TaskIde::getUrl(['record' => $task]))
            ->assertRedirect();
    }

    public function test_ide_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(TaskIde::getUrl(['record' => $task]))
            ->assertOk()
            ->assertSee('ide-proxy');
    }

    public function test_ide_url_includes_workspace_folder(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'workspace_path' => '/home/ploi/test-project',
        ]);

        $page = new TaskIde();
        $page->mount($task->id);

        $this->assertStringContainsString(
            'folder=' . urlencode('/home/ploi/test-project'),
            $page->getIdeUrl()
        );
    }
}
```

**Step 2: Run the tests**

Run: `php artisan test --filter=TaskIdeTest`

Expected: All tests pass.

**Step 3: Commit**

```bash
git add tests/Feature/Filament/TaskIdeTest.php
git commit -m "test(ide): add feature tests for TaskIde page"
```

---

### Task 10: Create Test for Auth Check Endpoint

**Files:**
- Create: `tests/Feature/IdeAuthCheckTest.php`

**Step 1: Create the test file**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdeAuthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_check_returns_401_for_guests(): void
    {
        $this->get('/ide-auth-check')
            ->assertUnauthorized();
    }

    public function test_auth_check_returns_200_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/ide-auth-check')
            ->assertOk();
    }
}
```

**Step 2: Run the tests**

Run: `php artisan test --filter=IdeAuthCheckTest`

Expected: All tests pass.

**Step 3: Commit**

```bash
git add tests/Feature/IdeAuthCheckTest.php
git commit -m "test(ide): add tests for auth check endpoint"
```

---

## Phase 6: Final Steps

### Task 11: Run Full Test Suite

**Step 1: Run all tests**

Run: `php artisan test`

Expected: All tests pass.

**Step 2: Run Pint**

Run: `vendor/bin/pint --dirty`

**Step 3: Commit any formatting fixes**

```bash
git add -A
git commit -m "style: apply pint formatting"
```

---

### Task 12: Build Assets and Verify

**Step 1: Build frontend assets**

Run: `npm run build`

**Step 2: Clear caches**

Run: `php artisan optimize:clear`

**Step 3: Verify the IDE page loads**

Navigate to `/admin/tasks/{id}/ide` in the browser (while logged in) and verify:
- The page loads without errors
- The chat panel appears on the right
- The iframe attempts to load code-server (may show error if code-server not installed yet)

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat(ide): complete IDE view implementation"
```

---

## Post-Implementation: Server Setup

After the code is deployed, follow the instructions in `docs/server-setup/code-server.md` to:

1. Install code-server on the server
2. Create and enable the systemd service
3. Add the Nginx proxy configuration via Ploi
4. Test the full integration

The code-server iframe will show an error until the server is configured, which is expected.
