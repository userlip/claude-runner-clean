# Claude Runner Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a dashboard to sync GitHub repos, provision Ploi sites, and run Claude Code tasks in a chat interface.

**Architecture:** Laravel queue jobs execute Claude Code CLI with streaming JSON output. Filament provides the admin UI with Livewire components for real-time chat. GitHub OAuth for repo sync, Ploi CLI for site provisioning.

**Tech Stack:** Laravel 12, Filament 4, Livewire 3, Pest, Laravel Socialite, proc_open for process management.

---

## Phase 1: Core Data Models & Migrations

### Task 1: Create GitHubConnection Model

**Files:**
- Create: `app/Models/GitHubConnection.php`
- Create: `database/migrations/2025_12_25_000001_create_github_connections_table.php`
- Create: `database/factories/GitHubConnectionFactory.php`
- Test: `tests/Feature/Models/GitHubConnectionTest.php`

**Step 1: Create migration**

Run: `php artisan make:migration create_github_connections_table --no-interaction`

Then edit the migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('access_token');
            $table->string('github_user_id');
            $table->string('github_username');
            $table->json('scopes')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_connections');
    }
};
```

**Step 2: Create model**

Run: `php artisan make:model GitHubConnection --no-interaction`

Then edit `app/Models/GitHubConnection.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GitHubConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'access_token',
        'github_user_id',
        'github_username',
        'scopes',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'scopes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }
}
```

**Step 3: Create factory**

Run: `php artisan make:factory GitHubConnectionFactory --no-interaction`

Then edit `database/factories/GitHubConnectionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GitHubConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'access_token' => fake()->sha256(),
            'github_user_id' => (string) fake()->randomNumber(8),
            'github_username' => fake()->userName(),
            'scopes' => ['repo'],
        ];
    }
}
```

**Step 4: Add relationship to User model**

Edit `app/Models/User.php`, add method:

```php
public function githubConnection(): HasOne
{
    return $this->hasOne(GitHubConnection::class);
}
```

Add import at top: `use Illuminate\Database\Eloquent\Relations\HasOne;`

**Step 5: Write tests**

Create `tests/Feature/Models/GitHubConnectionTest.php`:

```php
<?php

use App\Models\GitHubConnection;
use App\Models\User;

test('github connection belongs to user', function () {
    $connection = GitHubConnection::factory()->create();

    expect($connection->user)->toBeInstanceOf(User::class);
});

test('user has one github connection', function () {
    $user = User::factory()->create();
    $connection = GitHubConnection::factory()->create(['user_id' => $user->id]);

    expect($user->githubConnection->id)->toBe($connection->id);
});

test('access token is encrypted', function () {
    $connection = GitHubConnection::factory()->create([
        'access_token' => 'secret_token_123',
    ]);

    $raw = DB::table('github_connections')
        ->where('id', $connection->id)
        ->value('access_token');

    expect($raw)->not->toBe('secret_token_123');
    expect($connection->access_token)->toBe('secret_token_123');
});
```

**Step 6: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Models/GitHubConnectionTest.php`

Expected: 3 tests pass

**Step 7: Commit**

```bash
git add -A
git commit -m "feat: add GitHubConnection model with encryption"
```

---

### Task 2: Create Repository Model

**Files:**
- Create: `app/Models/Repository.php`
- Create: `database/migrations/2025_12_25_000002_create_repositories_table.php`
- Create: `database/factories/RepositoryFactory.php`
- Test: `tests/Feature/Models/RepositoryTest.php`

**Step 1: Create migration**

Run: `php artisan make:migration create_repositories_table --no-interaction`

Edit migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_id');
            $table->string('name');
            $table->string('full_name');
            $table->string('clone_url');
            $table->string('ssh_url');
            $table->string('default_branch')->default('main');
            $table->boolean('private')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'github_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
```

**Step 2: Create model**

Run: `php artisan make:model Repository --no-interaction`

Edit `app/Models/Repository.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repository extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'github_id',
        'name',
        'full_name',
        'clone_url',
        'ssh_url',
        'default_branch',
        'private',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'github_id' => 'integer',
            'private' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
```

**Step 3: Create factory**

Run: `php artisan make:factory RepositoryFactory --no-interaction`

Edit `database/factories/RepositoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepositoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->slug(2);
        $owner = fake()->userName();

        return [
            'user_id' => User::factory(),
            'github_id' => fake()->unique()->randomNumber(8),
            'name' => $name,
            'full_name' => "{$owner}/{$name}",
            'clone_url' => "https://github.com/{$owner}/{$name}.git",
            'ssh_url' => "git@github.com:{$owner}/{$name}.git",
            'default_branch' => 'main',
            'private' => fake()->boolean(30),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function private(): static
    {
        return $this->state(['private' => true]);
    }

    public function public(): static
    {
        return $this->state(['private' => false]);
    }
}
```

**Step 4: Add relationship to User**

Edit `app/Models/User.php`, add method:

```php
public function repositories(): HasMany
{
    return $this->hasMany(Repository::class);
}
```

Add import: `use Illuminate\Database\Eloquent\Relations\HasMany;`

**Step 5: Write tests**

Create `tests/Feature/Models/RepositoryTest.php`:

```php
<?php

use App\Models\Repository;
use App\Models\User;

test('repository belongs to user', function () {
    $repository = Repository::factory()->create();

    expect($repository->user)->toBeInstanceOf(User::class);
});

test('user has many repositories', function () {
    $user = User::factory()->create();
    Repository::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->repositories)->toHaveCount(3);
});

test('repository has unique github_id per user', function () {
    $user = User::factory()->create();
    Repository::factory()->create(['user_id' => $user->id, 'github_id' => 123]);

    expect(fn () => Repository::factory()->create(['user_id' => $user->id, 'github_id' => 123]))
        ->toThrow(Exception::class);
});
```

**Step 6: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Models/RepositoryTest.php`

Expected: 3 tests pass

**Step 7: Commit**

```bash
git add -A
git commit -m "feat: add Repository model"
```

---

### Task 3: Create Site Model with Status Enum

**Files:**
- Create: `app/Enums/SiteStatus.php`
- Create: `app/Models/Site.php`
- Create: `database/migrations/2025_12_25_000003_create_sites_table.php`
- Create: `database/factories/SiteFactory.php`
- Test: `tests/Feature/Models/SiteTest.php`

**Step 1: Create enum**

Run: `php artisan make:enum SiteStatus --no-interaction`

If command doesn't exist, manually create `app/Enums/SiteStatus.php`:

```php
<?php

namespace App\Enums;

enum SiteStatus: string
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Provisioning => 'Provisioning',
            self::Active => 'Active',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Provisioning => 'warning',
            self::Active => 'success',
            self::Failed => 'danger',
        };
    }
}
```

**Step 2: Create migration**

Run: `php artisan make:migration create_sites_table --no-interaction`

Edit migration:

```php
<?php

use App\Enums\SiteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('path')->nullable();
            $table->string('ploi_site_id')->nullable();
            $table->string('php_version')->default('8.4');
            $table->string('web_directory')->default('/public');
            $table->boolean('isolated_user')->default(false);
            $table->string('database_name')->nullable();
            $table->text('deploy_script')->nullable();
            $table->string('status')->default(SiteStatus::Pending->value);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
```

**Step 3: Create model**

Run: `php artisan make:model Site --no-interaction`

Edit `app/Models/Site.php`:

```php
<?php

namespace App\Models;

use App\Enums\SiteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'domain',
        'path',
        'ploi_site_id',
        'php_version',
        'web_directory',
        'isolated_user',
        'database_name',
        'deploy_script',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'isolated_user' => 'boolean',
            'status' => SiteStatus::class,
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function isActive(): bool
    {
        return $this->status === SiteStatus::Active;
    }

    public function markAsProvisioning(): void
    {
        $this->update(['status' => SiteStatus::Provisioning]);
    }

    public function markAsActive(string $path, ?string $ploiSiteId = null): void
    {
        $this->update([
            'status' => SiteStatus::Active,
            'path' => $path,
            'ploi_site_id' => $ploiSiteId,
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => SiteStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }
}
```

**Step 4: Create factory**

Run: `php artisan make:factory SiteFactory --no-interaction`

Edit `database/factories/SiteFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\SiteStatus;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        $subdomain = fake()->unique()->slug(1);

        return [
            'repository_id' => Repository::factory(),
            'domain' => "{$subdomain}.marin.sh",
            'path' => null,
            'ploi_site_id' => null,
            'php_version' => '8.4',
            'web_directory' => '/public',
            'isolated_user' => false,
            'database_name' => null,
            'deploy_script' => null,
            'status' => SiteStatus::Pending,
            'error_message' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SiteStatus::Active,
            'path' => '/home/ploi/' . fake()->slug(1) . '.marin.sh',
            'ploi_site_id' => (string) fake()->randomNumber(6),
        ]);
    }

    public function provisioning(): static
    {
        return $this->state(['status' => SiteStatus::Provisioning]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => SiteStatus::Failed,
            'error_message' => 'Provisioning failed: timeout',
        ]);
    }

    public function withDatabase(): static
    {
        return $this->state(fn () => [
            'database_name' => 'db_' . fake()->slug(1),
        ]);
    }
}
```

**Step 5: Add relationship to Repository**

Already added in Repository model (sites() method).

**Step 6: Write tests**

Create `tests/Feature/Models/SiteTest.php`:

```php
<?php

use App\Enums\SiteStatus;
use App\Models\Repository;
use App\Models\Site;

test('site belongs to repository', function () {
    $site = Site::factory()->create();

    expect($site->repository)->toBeInstanceOf(Repository::class);
});

test('repository has many sites', function () {
    $repository = Repository::factory()->create();
    Site::factory()->count(2)->create(['repository_id' => $repository->id]);

    expect($repository->sites)->toHaveCount(2);
});

test('site status is cast to enum', function () {
    $site = Site::factory()->create();

    expect($site->status)->toBeInstanceOf(SiteStatus::class);
});

test('can mark site as active', function () {
    $site = Site::factory()->create();

    $site->markAsActive('/home/ploi/test.marin.sh', '123456');

    expect($site->status)->toBe(SiteStatus::Active);
    expect($site->path)->toBe('/home/ploi/test.marin.sh');
    expect($site->ploi_site_id)->toBe('123456');
});

test('can mark site as failed', function () {
    $site = Site::factory()->create();

    $site->markAsFailed('Connection timeout');

    expect($site->status)->toBe(SiteStatus::Failed);
    expect($site->error_message)->toBe('Connection timeout');
});

test('isActive returns true only for active sites', function () {
    $active = Site::factory()->active()->create();
    $pending = Site::factory()->create();

    expect($active->isActive())->toBeTrue();
    expect($pending->isActive())->toBeFalse();
});
```

**Step 7: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Models/SiteTest.php`

Expected: 6 tests pass

**Step 8: Commit**

```bash
git add -A
git commit -m "feat: add Site model with status enum"
```

---

### Task 4: Create Task Model with Status Enum

**Files:**
- Create: `app/Enums/TaskStatus.php`
- Create: `app/Models/Task.php`
- Create: `database/migrations/2025_12_25_000004_create_tasks_table.php`
- Create: `database/factories/TaskFactory.php`
- Test: `tests/Feature/Models/TaskTest.php`

**Step 1: Create enum**

Create `app/Enums/TaskStatus.php`:

```php
<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
```

**Step 2: Create migration**

Run: `php artisan make:migration create_tasks_table --no-interaction`

Edit migration:

```php
<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->uuid('session_id');
            $table->string('status')->default(TaskStatus::Pending->value);
            $table->unsignedInteger('max_turns')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
```

**Step 3: Create model**

Run: `php artisan make:model Task --no-interaction`

Edit `app/Models/Task.php`:

```php
<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'site_id',
        'session_id',
        'status',
        'max_turns',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'max_turns' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            $task->uuid ??= Str::uuid();
            $task->session_id ??= Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isRunning(): bool
    {
        return $this->status === TaskStatus::Running;
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => TaskStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => TaskStatus::Failed,
            'completed_at' => now(),
        ]);
    }
}
```

**Step 4: Create factory**

Run: `php artisan make:factory TaskFactory --no-interaction`

Edit `database/factories/TaskFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'site_id' => Site::factory()->active(),
            'session_id' => Str::uuid(),
            'status' => TaskStatus::Pending,
            'max_turns' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state([
            'status' => TaskStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => TaskStatus::Completed,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => TaskStatus::Failed,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }

    public function withMaxTurns(int $turns): static
    {
        return $this->state(['max_turns' => $turns]);
    }
}
```

**Step 5: Write tests**

Create `tests/Feature/Models/TaskTest.php`:

```php
<?php

use App\Enums\TaskStatus;
use App\Models\Site;
use App\Models\Task;

test('task belongs to site', function () {
    $task = Task::factory()->create();

    expect($task->site)->toBeInstanceOf(Site::class);
});

test('site has many tasks', function () {
    $site = Site::factory()->active()->create();
    Task::factory()->count(3)->create(['site_id' => $site->id]);

    expect($site->tasks)->toHaveCount(3);
});

test('task auto-generates uuid and session_id on create', function () {
    $site = Site::factory()->active()->create();
    $task = Task::create(['site_id' => $site->id]);

    expect($task->uuid)->not->toBeNull();
    expect($task->session_id)->not->toBeNull();
});

test('task uses uuid as route key', function () {
    $task = Task::factory()->create();

    expect($task->getRouteKeyName())->toBe('uuid');
});

test('can mark task as running', function () {
    $task = Task::factory()->create();

    $task->markAsRunning();

    expect($task->status)->toBe(TaskStatus::Running);
    expect($task->started_at)->not->toBeNull();
});

test('can mark task as completed', function () {
    $task = Task::factory()->running()->create();

    $task->markAsCompleted();

    expect($task->status)->toBe(TaskStatus::Completed);
    expect($task->completed_at)->not->toBeNull();
});
```

**Step 6: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Models/TaskTest.php`

Expected: 6 tests pass

**Step 7: Commit**

```bash
git add -A
git commit -m "feat: add Task model with status enum"
```

---

### Task 5: Create Message Model

**Files:**
- Create: `app/Enums/MessageRole.php`
- Create: `app/Models/Message.php`
- Create: `database/migrations/2025_12_25_000005_create_messages_table.php`
- Create: `database/factories/MessageFactory.php`
- Test: `tests/Feature/Models/MessageTest.php`

**Step 1: Create enum**

Create `app/Enums/MessageRole.php`:

```php
<?php

namespace App\Enums;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
```

**Step 2: Create migration**

Run: `php artisan make:migration create_messages_table --no-interaction`

Edit migration:

```php
<?php

use App\Enums\MessageRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default(MessageRole::User->value);
            $table->longText('content')->nullable();
            $table->longText('raw_output')->nullable();
            $table->json('tool_calls')->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
```

**Step 3: Create model**

Run: `php artisan make:model Message --no-interaction`

Edit `app/Models/Message.php`:

```php
<?php

namespace App\Models;

use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'role',
        'content',
        'raw_output',
        'tool_calls',
        'tokens_in',
        'tokens_out',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'tool_calls' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost_usd' => 'decimal:6',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isFromUser(): bool
    {
        return $this->role === MessageRole::User;
    }

    public function isFromAssistant(): bool
    {
        return $this->role === MessageRole::Assistant;
    }

    public function appendRawOutput(string $chunk): void
    {
        $this->update([
            'raw_output' => ($this->raw_output ?? '') . $chunk,
        ]);
    }

    public function addToolCall(array $toolCall): void
    {
        $calls = $this->tool_calls ?? [];
        $calls[] = $toolCall;
        $this->update(['tool_calls' => $calls]);
    }
}
```

**Step 4: Create factory**

Run: `php artisan make:factory MessageFactory --no-interaction`

Edit `database/factories/MessageFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\MessageRole;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'role' => MessageRole::User,
            'content' => fake()->paragraph(),
            'raw_output' => null,
            'tool_calls' => null,
            'tokens_in' => null,
            'tokens_out' => null,
            'cost_usd' => null,
        ];
    }

    public function user(): static
    {
        return $this->state(['role' => MessageRole::User]);
    }

    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => MessageRole::Assistant,
            'tokens_in' => fake()->numberBetween(100, 1000),
            'tokens_out' => fake()->numberBetween(500, 5000),
            'cost_usd' => fake()->randomFloat(6, 0.001, 0.1),
        ]);
    }

    public function withToolCalls(): static
    {
        return $this->state([
            'tool_calls' => [
                ['name' => 'Read', 'params' => ['file_path' => '/app/Models/User.php']],
                ['name' => 'Edit', 'params' => ['file_path' => '/app/Models/User.php', 'old_string' => 'foo', 'new_string' => 'bar']],
            ],
        ]);
    }
}
```

**Step 5: Write tests**

Create `tests/Feature/Models/MessageTest.php`:

```php
<?php

use App\Enums\MessageRole;
use App\Models\Message;
use App\Models\Task;

test('message belongs to task', function () {
    $message = Message::factory()->create();

    expect($message->task)->toBeInstanceOf(Task::class);
});

test('task has many messages', function () {
    $task = Task::factory()->create();
    Message::factory()->count(5)->create(['task_id' => $task->id]);

    expect($task->messages)->toHaveCount(5);
});

test('message role is cast to enum', function () {
    $message = Message::factory()->create();

    expect($message->role)->toBeInstanceOf(MessageRole::class);
});

test('isFromUser returns true for user messages', function () {
    $userMessage = Message::factory()->user()->create();
    $assistantMessage = Message::factory()->assistant()->create();

    expect($userMessage->isFromUser())->toBeTrue();
    expect($assistantMessage->isFromUser())->toBeFalse();
});

test('can append raw output', function () {
    $message = Message::factory()->assistant()->create(['raw_output' => null]);

    $message->appendRawOutput('{"type":"text"}');
    $message->appendRawOutput("\n");
    $message->appendRawOutput('{"type":"result"}');

    expect($message->raw_output)->toBe("{\"type\":\"text\"}\n{\"type\":\"result\"}");
});

test('can add tool calls', function () {
    $message = Message::factory()->assistant()->create(['tool_calls' => null]);

    $message->addToolCall(['name' => 'Read', 'params' => ['file' => 'test.php']]);
    $message->addToolCall(['name' => 'Write', 'params' => ['file' => 'out.php']]);

    expect($message->tool_calls)->toHaveCount(2);
    expect($message->tool_calls[0]['name'])->toBe('Read');
});
```

**Step 6: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Models/MessageTest.php`

Expected: 6 tests pass

**Step 7: Commit**

```bash
git add -A
git commit -m "feat: add Message model"
```

---

## Phase 2: GitHub OAuth Integration

### Task 6: Install Laravel Socialite

**Step 1: Install package**

Run: `composer require laravel/socialite --no-interaction`

**Step 2: Configure GitHub OAuth in services.php**

Edit `config/services.php`, add:

```php
'github' => [
    'client_id' => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect' => env('GITHUB_REDIRECT_URI', '/admin/github/callback'),
],
```

**Step 3: Add env variables to .env.example**

Edit `.env.example`, add:

```
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=/admin/github/callback
```

**Step 4: Commit**

```bash
git add -A
git commit -m "chore: install Laravel Socialite for GitHub OAuth"
```

---

### Task 7: Create GitHub OAuth Controller

**Files:**
- Create: `app/Http/Controllers/GitHubAuthController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/GitHubAuthTest.php`

**Step 1: Create controller**

Run: `php artisan make:controller GitHubAuthController --no-interaction`

Edit `app/Http/Controllers/GitHubAuthController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\GitHubConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GitHubAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['repo'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        GitHubConnection::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'access_token' => $githubUser->token,
                'github_user_id' => $githubUser->getId(),
                'github_username' => $githubUser->getNickname(),
                'scopes' => ['repo'],
            ]
        );

        return redirect('/admin')
            ->with('success', 'GitHub connected successfully!');
    }

    public function disconnect(): RedirectResponse
    {
        GitHubConnection::where('user_id', Auth::id())->delete();

        return redirect('/admin')
            ->with('success', 'GitHub disconnected.');
    }
}
```

**Step 2: Add routes**

Edit `routes/web.php`:

```php
<?php

use App\Http\Controllers\GitHubAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth'])->prefix('admin/github')->group(function () {
    Route::get('/redirect', [GitHubAuthController::class, 'redirect'])->name('github.redirect');
    Route::get('/callback', [GitHubAuthController::class, 'callback'])->name('github.callback');
    Route::delete('/disconnect', [GitHubAuthController::class, 'disconnect'])->name('github.disconnect');
});
```

**Step 3: Write tests**

Create `tests/Feature/GitHubAuthTest.php`:

```php
<?php

use App\Models\GitHubConnection;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('github redirect requires authentication', function () {
    $this->get(route('github.redirect'))
        ->assertRedirect('/admin/login');
});

test('github redirect redirects to github', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('github.redirect'));

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('github.com');
});

test('github callback creates connection', function () {
    $user = User::factory()->create();

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->token = 'test_token_123';
    $socialiteUser->shouldReceive('getId')->andReturn('12345');
    $socialiteUser->shouldReceive('getNickname')->andReturn('testuser');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->actingAs($user)
        ->get(route('github.callback'))
        ->assertRedirect('/admin');

    $this->assertDatabaseHas('github_connections', [
        'user_id' => $user->id,
        'github_user_id' => '12345',
        'github_username' => 'testuser',
    ]);
});

test('github disconnect removes connection', function () {
    $user = User::factory()->create();
    GitHubConnection::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('github.disconnect'))
        ->assertRedirect('/admin');

    $this->assertDatabaseMissing('github_connections', ['user_id' => $user->id]);
});
```

**Step 4: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/GitHubAuthTest.php`

Expected: 4 tests pass

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add GitHub OAuth controller and routes"
```

---

### Task 8: Create GitHub Settings Filament Page

**Files:**
- Create: `app/Filament/Pages/GitHubSettings.php`
- Create: `resources/views/filament/pages/github-settings.blade.php`
- Test: `tests/Feature/Filament/GitHubSettingsTest.php`

**Step 1: Create Filament page**

Run: `php artisan make:filament-page GitHubSettings --no-interaction`

Edit `app/Filament/Pages/GitHubSettings.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Models\GitHubConnection;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class GitHubSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'GitHub Connection';

    protected static string $view = 'filament.pages.github-settings';

    public function getGitHubConnection(): ?GitHubConnection
    {
        return Auth::user()->githubConnection;
    }

    public function isConnected(): bool
    {
        return $this->getGitHubConnection() !== null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connect')
                ->label('Connect GitHub')
                ->icon('heroicon-o-link')
                ->url(route('github.redirect'))
                ->visible(fn () => ! $this->isConnected()),

            Action::make('disconnect')
                ->label('Disconnect')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will disconnect your GitHub account. Your synced repositories will remain.')
                ->action(function () {
                    Auth::user()->githubConnection()->delete();
                    Notification::make()
                        ->title('GitHub disconnected')
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->isConnected()),
        ];
    }
}
```

**Step 2: Create view**

Create `resources/views/filament/pages/github-settings.blade.php`:

```blade
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            GitHub Connection
        </x-slot>

        @if($this->isConnected())
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-success-100 dark:bg-success-900">
                    <x-heroicon-o-check class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-lg font-medium">Connected as {{ $this->getGitHubConnection()->github_username }}</p>
                    <p class="text-sm text-gray-500">Your GitHub account is connected and ready to sync repositories.</p>
                </div>
            </div>
        @else
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-link-slash class="h-6 w-6 text-gray-400" />
                </div>
                <div>
                    <p class="text-lg font-medium">Not Connected</p>
                    <p class="text-sm text-gray-500">Connect your GitHub account to sync and manage your repositories.</p>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
```

**Step 3: Write tests**

Create `tests/Feature/Filament/GitHubSettingsTest.php`:

```php
<?php

use App\Filament\Pages\GitHubSettings;
use App\Models\GitHubConnection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('can view github settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(GitHubSettings::class)
        ->assertSuccessful()
        ->assertSee('Not Connected');
});

test('shows connected status when github is connected', function () {
    $user = User::factory()->create();
    GitHubConnection::factory()->create([
        'user_id' => $user->id,
        'github_username' => 'testuser123',
    ]);

    $this->actingAs($user);

    Livewire::test(GitHubSettings::class)
        ->assertSuccessful()
        ->assertSee('Connected as testuser123');
});

test('can disconnect github', function () {
    $user = User::factory()->create();
    GitHubConnection::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(GitHubSettings::class)
        ->callAction('disconnect');

    expect($user->fresh()->githubConnection)->toBeNull();
});
```

**Step 4: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Filament/GitHubSettingsTest.php`

Expected: 3 tests pass

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add GitHub Settings Filament page"
```

---

## Phase 3: Repository Sync

### Task 9: Create GitHub API Service

**Files:**
- Create: `app/Services/GitHubService.php`
- Test: `tests/Unit/Services/GitHubServiceTest.php`

**Step 1: Create service**

Create `app/Services/GitHubService.php`:

```php
<?php

namespace App\Services;

use App\Models\GitHubConnection;
use App\Models\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    private const API_BASE = 'https://api.github.com';

    public function __construct(
        private GitHubConnection $connection
    ) {}

    public function fetchRepositories(): Collection
    {
        $repos = collect();
        $page = 1;
        $perPage = 100;

        do {
            $response = Http::withToken($this->connection->access_token)
                ->accept('application/vnd.github+json')
                ->get(self::API_BASE . '/user/repos', [
                    'per_page' => $perPage,
                    'page' => $page,
                    'sort' => 'updated',
                    'affiliation' => 'owner,collaborator,organization_member',
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to fetch repositories: ' . $response->body());
            }

            $pageRepos = collect($response->json());
            $repos = $repos->concat($pageRepos);
            $page++;
        } while ($pageRepos->count() === $perPage);

        return $repos;
    }

    public function syncRepositories(): int
    {
        $repos = $this->fetchRepositories();
        $synced = 0;

        foreach ($repos as $repo) {
            Repository::updateOrCreate(
                [
                    'user_id' => $this->connection->user_id,
                    'github_id' => $repo['id'],
                ],
                [
                    'name' => $repo['name'],
                    'full_name' => $repo['full_name'],
                    'clone_url' => $repo['clone_url'],
                    'ssh_url' => $repo['ssh_url'],
                    'default_branch' => $repo['default_branch'],
                    'private' => $repo['private'],
                    'description' => $repo['description'],
                ]
            );
            $synced++;
        }

        return $synced;
    }
}
```

**Step 2: Write tests**

Create `tests/Unit/Services/GitHubServiceTest.php`:

```php
<?php

use App\Models\GitHubConnection;
use App\Models\Repository;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Http;

test('sync repositories creates new repos', function () {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            [
                'id' => 123,
                'name' => 'test-repo',
                'full_name' => 'testuser/test-repo',
                'clone_url' => 'https://github.com/testuser/test-repo.git',
                'ssh_url' => 'git@github.com:testuser/test-repo.git',
                'default_branch' => 'main',
                'private' => false,
                'description' => 'A test repo',
            ],
        ]),
    ]);

    $connection = GitHubConnection::factory()->create();
    $service = new GitHubService($connection);

    $synced = $service->syncRepositories();

    expect($synced)->toBe(1);
    expect(Repository::where('github_id', 123)->exists())->toBeTrue();
});

test('sync repositories updates existing repos', function () {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            [
                'id' => 123,
                'name' => 'updated-repo',
                'full_name' => 'testuser/updated-repo',
                'clone_url' => 'https://github.com/testuser/updated-repo.git',
                'ssh_url' => 'git@github.com:testuser/updated-repo.git',
                'default_branch' => 'main',
                'private' => true,
                'description' => 'Updated description',
            ],
        ]),
    ]);

    $connection = GitHubConnection::factory()->create();
    Repository::factory()->create([
        'user_id' => $connection->user_id,
        'github_id' => 123,
        'name' => 'old-name',
    ]);

    $service = new GitHubService($connection);
    $service->syncRepositories();

    $repo = Repository::where('github_id', 123)->first();
    expect($repo->name)->toBe('updated-repo');
    expect($repo->private)->toBeTrue();
});

test('throws exception on api failure', function () {
    Http::fake([
        'api.github.com/user/repos*' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $connection = GitHubConnection::factory()->create();
    $service = new GitHubService($connection);

    expect(fn () => $service->syncRepositories())->toThrow(RuntimeException::class);
});
```

**Step 3: Run tests**

Run: `php artisan test tests/Unit/Services/GitHubServiceTest.php`

Expected: 3 tests pass

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add GitHubService for repository sync"
```

---

### Task 10: Create Repository Resource

**Files:**
- Create: `app/Filament/Resources/RepositoryResource.php`
- Create: `app/Filament/Resources/RepositoryResource/Pages/ListRepositories.php`
- Test: `tests/Feature/Filament/RepositoryResourceTest.php`

**Step 1: Create resource**

Run: `php artisan make:filament-resource Repository --view --no-interaction`

Edit `app/Filament/Resources/RepositoryResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RepositoryResource\Pages;
use App\Models\Repository;
use App\Services\GitHubService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RepositoryResource extends Resource
{
    protected static ?string $model = Repository::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Repository')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('private')
                    ->label('Visibility')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('default_branch')
                    ->label('Branch')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('sites_count')
                    ->label('Sites')
                    ->counts('sites'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Synced')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Actions\Action::make('createSite')
                    ->label('Create Site')
                    ->icon('heroicon-o-server')
                    ->color(Color::Blue)
                    ->url(fn (Repository $record) => route('filament.admin.resources.sites.create', ['repository' => $record->id])),

                Actions\Action::make('github')
                    ->label('GitHub')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Repository $record) => "https://github.com/{$record->full_name}")
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                Actions\Action::make('sync')
                    ->label('Sync Repositories')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () {
                        $connection = Auth::user()->githubConnection;

                        if (! $connection) {
                            Notification::make()
                                ->title('GitHub not connected')
                                ->body('Please connect your GitHub account first.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $service = new GitHubService($connection);
                        $count = $service->syncRepositories();

                        Notification::make()
                            ->title('Repositories synced')
                            ->body("Synced {$count} repositories from GitHub.")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No repositories')
            ->emptyStateDescription('Sync your GitHub repositories to get started.')
            ->emptyStateActions([
                Actions\Action::make('sync')
                    ->label('Sync from GitHub')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () {
                        $connection = Auth::user()->githubConnection;

                        if (! $connection) {
                            Notification::make()
                                ->title('GitHub not connected')
                                ->danger()
                                ->send();
                            return;
                        }

                        $service = new GitHubService($connection);
                        $count = $service->syncRepositories();

                        Notification::make()
                            ->title("Synced {$count} repositories")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepositories::route('/'),
        ];
    }
}
```

**Step 2: Create list page**

Create `app/Filament/Resources/RepositoryResource/Pages/ListRepositories.php`:

```php
<?php

namespace App\Filament\Resources\RepositoryResource\Pages;

use App\Filament\Resources\RepositoryResource;
use Filament\Resources\Pages\ListRecords;

class ListRepositories extends ListRecords
{
    protected static string $resource = RepositoryResource::class;
}
```

**Step 3: Write tests**

Create `tests/Feature/Filament/RepositoryResourceTest.php`:

```php
<?php

use App\Filament\Resources\RepositoryResource\Pages\ListRepositories;
use App\Models\GitHubConnection;
use App\Models\Repository;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can view repositories list', function () {
    Livewire::test(ListRepositories::class)
        ->assertSuccessful();
});

test('only shows own repositories', function () {
    $ownRepo = Repository::factory()->create(['user_id' => $this->user->id]);
    $otherRepo = Repository::factory()->create();

    Livewire::test(ListRepositories::class)
        ->assertCanSeeTableRecords([$ownRepo])
        ->assertCanNotSeeTableRecords([$otherRepo]);
});

test('can sync repositories from github', function () {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            [
                'id' => 999,
                'name' => 'synced-repo',
                'full_name' => 'testuser/synced-repo',
                'clone_url' => 'https://github.com/testuser/synced-repo.git',
                'ssh_url' => 'git@github.com:testuser/synced-repo.git',
                'default_branch' => 'main',
                'private' => false,
                'description' => null,
            ],
        ]),
    ]);

    GitHubConnection::factory()->create(['user_id' => $this->user->id]);

    Livewire::test(ListRepositories::class)
        ->callTableAction('sync')
        ->assertNotified('Repositories synced');

    expect(Repository::where('github_id', 999)->exists())->toBeTrue();
});

test('shows error when github not connected', function () {
    Livewire::test(ListRepositories::class)
        ->callTableAction('sync')
        ->assertNotified('GitHub not connected');
});
```

**Step 4: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Filament/RepositoryResourceTest.php`

Expected: 4 tests pass

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add RepositoryResource for Filament"
```

---

## Phase 4: Site Provisioning

### Task 11: Create SiteResource with Create Form

**Files:**
- Create: `app/Filament/Resources/SiteResource.php`
- Create: `app/Filament/Resources/SiteResource/Pages/ListSites.php`
- Create: `app/Filament/Resources/SiteResource/Pages/CreateSite.php`
- Create: `app/Filament/Resources/SiteResource/Pages/ViewSite.php`
- Test: `tests/Feature/Filament/SiteResourceTest.php`

**Step 1: Create resource**

Run: `php artisan make:filament-resource Site --view --no-interaction`

Edit `app/Filament/Resources/SiteResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Enums\SiteStatus;
use App\Filament\Resources\SiteResource\Pages;
use App\Models\Repository;
use App\Models\Site;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('repository', fn (Builder $query) => $query->where('user_id', Auth::id()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site Configuration')
                    ->schema([
                        Forms\Components\Select::make('repository_id')
                            ->label('Repository')
                            ->options(fn () => Repository::where('user_id', Auth::id())->pluck('full_name', 'id'))
                            ->required()
                            ->searchable()
                            ->default(request()->query('repository'))
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $repo = Repository::find($state);
                                    if ($repo) {
                                        $slug = Str::slug($repo->name);
                                        $set('domain', "{$slug}.marin.sh");
                                        $set('database_name', "db_{$slug}");
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('domain')
                            ->label('Domain')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('my-project.marin.sh')
                            ->helperText('Single subdomain only (e.g., feature-auth.marin.sh)'),
                    ]),

                Section::make('PHP & Server')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('php_version')
                                    ->label('PHP Version')
                                    ->options([
                                        '8.1' => 'PHP 8.1',
                                        '8.2' => 'PHP 8.2',
                                        '8.3' => 'PHP 8.3',
                                        '8.4' => 'PHP 8.4',
                                    ])
                                    ->default('8.4')
                                    ->required(),

                                Forms\Components\TextInput::make('web_directory')
                                    ->label('Web Directory')
                                    ->default('/public')
                                    ->required(),
                            ]),

                        Forms\Components\Toggle::make('isolated_user')
                            ->label('Isolated User')
                            ->helperText('Create an isolated system user for this site'),
                    ]),

                Section::make('Database')
                    ->schema([
                        Forms\Components\Toggle::make('create_database')
                            ->label('Create Database')
                            ->default(false)
                            ->live(),

                        Forms\Components\TextInput::make('database_name')
                            ->label('Database Name')
                            ->visible(fn (Forms\Get $get) => $get('create_database')),
                    ]),

                Section::make('Deployment')
                    ->schema([
                        Forms\Components\Toggle::make('run_composer')
                            ->label('Run Composer Install')
                            ->default(true),

                        Forms\Components\Textarea::make('deploy_script')
                            ->label('Deploy Script')
                            ->rows(5)
                            ->placeholder('cd {SITE_PATH}
php artisan migrate --force
php artisan config:cache'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('repository.full_name')
                    ->label('Repository')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (SiteStatus $state) => $state->color()),

                Tables\Columns\TextColumn::make('php_version')
                    ->label('PHP')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('tasks_count')
                    ->label('Tasks')
                    ->counts('tasks'),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Actions\Action::make('chat')
                    ->label('Open Chat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (Site $record) => Pages\SiteChat::getUrl(['record' => $record]))
                    ->visible(fn (Site $record) => $record->isActive()),

                Actions\ViewAction::make(),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'view' => Pages\ViewSite::route('/{record}'),
            'chat' => Pages\SiteChat::route('/{record}/chat'),
        ];
    }
}
```

**Step 2: Create page files**

Create `app/Filament/Resources/SiteResource/Pages/ListSites.php`:

```php
<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSites extends ListRecords
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

Create `app/Filament/Resources/SiteResource/Pages/CreateSite.php`:

```php
<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Jobs\ProvisionSiteJob;
use Filament\Resources\Pages\CreateRecord;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['create_database'], $data['run_composer']);

        return $data;
    }

    protected function afterCreate(): void
    {
        ProvisionSiteJob::dispatch($this->record);
    }
}
```

Create `app/Filament/Resources/SiteResource/Pages/ViewSite.php`:

```php
<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSite extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('chat')
                ->label('Open Chat')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->url(fn () => SiteChat::getUrl(['record' => $this->record]))
                ->visible(fn () => $this->record->isActive()),
        ];
    }
}
```

**Step 3: Create placeholder SiteChat page**

Create `app/Filament/Resources/SiteResource/Pages/SiteChat.php`:

```php
<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use Filament\Resources\Pages\Page;

class SiteChat extends Page
{
    protected static string $resource = SiteResource::class;

    protected static string $view = 'filament.resources.site-resource.pages.site-chat';

    public $record;

    public function mount($record): void
    {
        $this->record = $this->resolveRecord($record);
    }
}
```

Create `resources/views/filament/resources/site-resource/pages/site-chat.blade.php`:

```blade
<x-filament-panels::page>
    <div class="text-center py-12">
        <p class="text-gray-500">Chat interface coming soon...</p>
    </div>
</x-filament-panels::page>
```

**Step 4: Write tests**

Create `tests/Feature/Filament/SiteResourceTest.php`:

```php
<?php

use App\Enums\SiteStatus;
use App\Filament\Resources\SiteResource\Pages\CreateSite;
use App\Filament\Resources\SiteResource\Pages\ListSites;
use App\Models\Repository;
use App\Models\Site;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can view sites list', function () {
    Livewire::test(ListSites::class)
        ->assertSuccessful();
});

test('only shows sites from own repositories', function () {
    $ownRepo = Repository::factory()->create(['user_id' => $this->user->id]);
    $ownSite = Site::factory()->create(['repository_id' => $ownRepo->id]);

    $otherRepo = Repository::factory()->create();
    $otherSite = Site::factory()->create(['repository_id' => $otherRepo->id]);

    Livewire::test(ListSites::class)
        ->assertCanSeeTableRecords([$ownSite])
        ->assertCanNotSeeTableRecords([$otherSite]);
});

test('can create site', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);

    Livewire::test(CreateSite::class)
        ->fillForm([
            'repository_id' => $repository->id,
            'domain' => 'test-site.marin.sh',
            'php_version' => '8.4',
            'web_directory' => '/public',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Site::where('domain', 'test-site.marin.sh')->exists())->toBeTrue();
});

test('domain must be unique', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    Site::factory()->create(['domain' => 'existing.marin.sh']);

    Livewire::test(CreateSite::class)
        ->fillForm([
            'repository_id' => $repository->id,
            'domain' => 'existing.marin.sh',
        ])
        ->call('create')
        ->assertHasFormErrors(['domain' => 'unique']);
});
```

**Step 5: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Filament/SiteResourceTest.php`

Expected: 4 tests pass

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add SiteResource with create form"
```

---

### Task 12: Create ProvisionSiteJob (Placeholder)

**Files:**
- Create: `app/Jobs/ProvisionSiteJob.php`
- Test: `tests/Feature/Jobs/ProvisionSiteJobTest.php`

**Step 1: Create job**

Run: `php artisan make:job ProvisionSiteJob --no-interaction`

Edit `app/Jobs/ProvisionSiteJob.php`:

```php
<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ProvisionSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public Site $site
    ) {}

    public function handle(): void
    {
        $this->site->markAsProvisioning();

        try {
            // TODO: Implement Ploi CLI integration
            // For now, simulate provisioning
            $path = "/home/ploi/{$this->site->domain}";

            Log::info("Provisioning site: {$this->site->domain}", [
                'site_id' => $this->site->id,
                'repository' => $this->site->repository->full_name,
            ]);

            // Placeholder: mark as active with simulated path
            $this->site->markAsActive($path, 'ploi_' . $this->site->id);

        } catch (\Throwable $e) {
            Log::error("Site provisioning failed: {$e->getMessage()}", [
                'site_id' => $this->site->id,
            ]);

            $this->site->markAsFailed($e->getMessage());

            throw $e;
        }
    }
}
```

**Step 2: Write tests**

Create `tests/Feature/Jobs/ProvisionSiteJobTest.php`:

```php
<?php

use App\Enums\SiteStatus;
use App\Jobs\ProvisionSiteJob;
use App\Models\Site;

test('job marks site as provisioning then active', function () {
    $site = Site::factory()->create();

    expect($site->status)->toBe(SiteStatus::Pending);

    ProvisionSiteJob::dispatchSync($site);

    $site->refresh();

    expect($site->status)->toBe(SiteStatus::Active);
    expect($site->path)->toBe("/home/ploi/{$site->domain}");
    expect($site->ploi_site_id)->not->toBeNull();
});

test('job sets path based on domain', function () {
    $site = Site::factory()->create(['domain' => 'my-project.marin.sh']);

    ProvisionSiteJob::dispatchSync($site);

    expect($site->fresh()->path)->toBe('/home/ploi/my-project.marin.sh');
});
```

**Step 3: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Jobs/ProvisionSiteJobTest.php`

Expected: 2 tests pass

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add ProvisionSiteJob placeholder"
```

---

## Phase 5: Claude Code Execution

### Task 13: Create RunClaudeMessageJob

**Files:**
- Create: `app/Jobs/RunClaudeMessageJob.php`
- Test: `tests/Feature/Jobs/RunClaudeMessageJobTest.php`

**Step 1: Create job**

Run: `php artisan make:job RunClaudeMessageJob --no-interaction`

Edit `app/Jobs/RunClaudeMessageJob.php`:

```php
<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunClaudeMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public Task $task,
        public Message $userMessage,
        public bool $continue = false
    ) {}

    public function handle(): void
    {
        $this->task->markAsRunning();

        $assistantMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'content' => '',
        ]);

        try {
            $command = $this->buildCommand();
            $workingDir = $this->task->site->path;

            Log::info("Running Claude Code", [
                'task_id' => $this->task->id,
                'command' => $command,
                'working_dir' => $workingDir,
            ]);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes, $workingDir);

            if (! is_resource($process)) {
                throw new \RuntimeException('Failed to start Claude process');
            }

            fclose($pipes[0]);

            $output = '';
            $toolCalls = [];

            while (! feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line === false) {
                    continue;
                }

                $output .= $line;
                $assistantMessage->appendRawOutput($line);

                $parsed = $this->parseLine($line);
                if ($parsed) {
                    if (isset($parsed['tool_call'])) {
                        $toolCalls[] = $parsed['tool_call'];
                        $assistantMessage->update(['tool_calls' => $toolCalls]);
                    }
                    if (isset($parsed['content'])) {
                        $assistantMessage->update([
                            'content' => ($assistantMessage->content ?? '') . $parsed['content'],
                        ]);
                    }
                    if (isset($parsed['usage'])) {
                        $assistantMessage->update([
                            'tokens_in' => $parsed['usage']['input_tokens'] ?? null,
                            'tokens_out' => $parsed['usage']['output_tokens'] ?? null,
                            'cost_usd' => $parsed['usage']['cost_usd'] ?? null,
                        ]);
                    }
                }
            }

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                Log::warning("Claude exited with code {$exitCode}", ['stderr' => $stderr]);
            }

            $this->task->markAsCompleted();

        } catch (\Throwable $e) {
            Log::error("Claude execution failed: {$e->getMessage()}");

            $assistantMessage->update([
                'content' => "Error: {$e->getMessage()}",
            ]);

            $this->task->markAsFailed();

            throw $e;
        }
    }

    private function buildCommand(): string
    {
        $prompt = escapeshellarg($this->userMessage->content);
        $sessionId = escapeshellarg($this->task->session_id);

        $cmd = "claude -p {$prompt} --output-format stream-json --session-id {$sessionId}";

        if ($this->continue) {
            $cmd .= ' --continue';
        }

        if ($this->task->max_turns) {
            $cmd .= " --max-turns {$this->task->max_turns}";
        }

        return $cmd;
    }

    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if (empty($line)) {
            return null;
        }

        $data = json_decode($line, true);
        if (! $data) {
            return null;
        }

        $result = [];

        if (($data['type'] ?? '') === 'assistant' && isset($data['message']['content'])) {
            foreach ($data['message']['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $result['content'] = $block['text'] ?? '';
                }
                if (($block['type'] ?? '') === 'tool_use') {
                    $result['tool_call'] = [
                        'id' => $block['id'] ?? null,
                        'name' => $block['name'] ?? '',
                        'input' => $block['input'] ?? [],
                    ];
                }
            }
        }

        if (($data['type'] ?? '') === 'result') {
            $result['usage'] = [
                'input_tokens' => $data['total_input_tokens'] ?? null,
                'output_tokens' => $data['total_output_tokens'] ?? null,
                'cost_usd' => $data['total_cost_usd'] ?? null,
            ];
        }

        return $result ?: null;
    }
}
```

**Step 2: Write tests**

Create `tests/Feature/Jobs/RunClaudeMessageJobTest.php`:

```php
<?php

use App\Enums\MessageRole;
use App\Enums\TaskStatus;
use App\Jobs\RunClaudeMessageJob;
use App\Models\Message;
use App\Models\Site;
use App\Models\Task;

test('job marks task as running then completed', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create([
        'task_id' => $task->id,
        'content' => 'echo "Hello"',
    ]);

    // Skip actual execution for unit test
    $job = new RunClaudeMessageJob($task, $message);

    expect($job->task->id)->toBe($task->id);
    expect($job->userMessage->content)->toBe('echo "Hello"');
});

test('buildCommand includes session id', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create([
        'task_id' => $task->id,
        'content' => 'test prompt',
    ]);

    $job = new RunClaudeMessageJob($task, $message);

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('buildCommand');
    $method->setAccessible(true);

    $command = $method->invoke($job);

    expect($command)->toContain('--session-id');
    expect($command)->toContain('--output-format stream-json');
    expect($command)->toContain('-p');
});

test('buildCommand includes max turns when set', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->withMaxTurns(5)->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('buildCommand');
    $method->setAccessible(true);

    $command = $method->invoke($job);

    expect($command)->toContain('--max-turns 5');
});

test('buildCommand includes continue flag', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message, continue: true);

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('buildCommand');
    $method->setAccessible(true);

    $command = $method->invoke($job);

    expect($command)->toContain('--continue');
});
```

**Step 3: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Jobs/RunClaudeMessageJobTest.php`

Expected: 4 tests pass

**Step 4: Commit**

```bash
git add -A
git commit -m "feat: add RunClaudeMessageJob for Claude Code execution"
```

---

## Phase 6: Chat UI

### Task 14: Create Chat Livewire Component

**Files:**
- Create: `app/Livewire/SiteChat.php`
- Create: `resources/views/livewire/site-chat.blade.php`
- Modify: `app/Filament/Resources/SiteResource/Pages/SiteChat.php`
- Test: `tests/Feature/Livewire/SiteChatTest.php`

**Step 1: Create Livewire component**

Run: `php artisan make:livewire SiteChat --no-interaction`

Edit `app/Livewire/SiteChat.php`:

```php
<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Enums\TaskStatus;
use App\Jobs\RunClaudeMessageJob;
use App\Models\Message;
use App\Models\Site;
use App\Models\Task;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SiteChat extends Component
{
    public Site $site;

    public ?Task $activeTask = null;

    public string $prompt = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->activeTask = $site->tasks()->latest()->first();
    }

    #[Computed]
    public function tasks()
    {
        return $this->site->tasks()->latest()->get();
    }

    #[Computed]
    public function messages()
    {
        if (! $this->activeTask) {
            return collect();
        }

        return $this->activeTask->messages()->oldest()->get();
    }

    #[Computed]
    public function isRunning(): bool
    {
        return $this->activeTask?->isRunning() ?? false;
    }

    public function selectTask(int $taskId): void
    {
        $this->activeTask = Task::find($taskId);
    }

    public function newChat(): void
    {
        $this->activeTask = null;
        $this->prompt = '';
    }

    public function sendMessage(): void
    {
        $this->validate([
            'prompt' => 'required|string|min:1|max:10000',
        ]);

        if (! $this->activeTask) {
            $this->activeTask = Task::create([
                'site_id' => $this->site->id,
            ]);
        }

        $isFirstMessage = $this->activeTask->messages()->count() === 0;

        $userMessage = Message::create([
            'task_id' => $this->activeTask->id,
            'role' => MessageRole::User,
            'content' => $this->prompt,
        ]);

        RunClaudeMessageJob::dispatch(
            $this->activeTask,
            $userMessage,
            continue: ! $isFirstMessage
        );

        $this->prompt = '';
    }

    public function render()
    {
        return view('livewire.site-chat');
    }
}
```

**Step 2: Create view**

Edit `resources/views/livewire/site-chat.blade.php`:

```blade
<div class="flex h-[calc(100vh-12rem)] gap-4">
    {{-- Task Sidebar --}}
    <div class="w-64 shrink-0 overflow-y-auto rounded-lg bg-white p-4 shadow dark:bg-gray-900">
        <button
            wire:click="newChat"
            class="mb-4 w-full rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700"
        >
            New Chat
        </button>

        <div class="space-y-2">
            @foreach($this->tasks as $task)
                <button
                    wire:click="selectTask({{ $task->id }})"
                    @class([
                        'w-full rounded-lg px-3 py-2 text-left text-sm',
                        'bg-primary-100 dark:bg-primary-900' => $activeTask?->id === $task->id,
                        'hover:bg-gray-100 dark:hover:bg-gray-800' => $activeTask?->id !== $task->id,
                    ])
                >
                    <div class="flex items-center justify-between">
                        <span class="truncate">{{ $task->messages()->first()?->content ?? 'New chat' }}</span>
                        <span @class([
                            'h-2 w-2 rounded-full',
                            'bg-green-500' => $task->status === \App\Enums\TaskStatus::Completed,
                            'bg-blue-500 animate-pulse' => $task->status === \App\Enums\TaskStatus::Running,
                            'bg-gray-400' => $task->status === \App\Enums\TaskStatus::Pending,
                            'bg-red-500' => $task->status === \App\Enums\TaskStatus::Failed,
                        ])></span>
                    </div>
                    <div class="text-xs text-gray-500">{{ $task->created_at->diffForHumans() }}</div>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex flex-1 flex-col rounded-lg bg-white shadow dark:bg-gray-900">
        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4" wire:poll.2s="$refresh">
            @forelse($this->messages as $message)
                <div @class([
                    'flex',
                    'justify-end' => $message->isFromUser(),
                    'justify-start' => $message->isFromAssistant(),
                ])>
                    <div @class([
                        'max-w-[80%] rounded-lg px-4 py-2',
                        'bg-primary-600 text-white' => $message->isFromUser(),
                        'bg-gray-100 dark:bg-gray-800' => $message->isFromAssistant(),
                    ])>
                        @if($message->isFromUser())
                            <p class="whitespace-pre-wrap">{{ $message->content }}</p>
                        @else
                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                {!! \Illuminate\Support\Str::markdown($message->content ?? '') !!}
                            </div>

                            @if($message->tool_calls)
                                <div class="mt-2 space-y-2">
                                    @foreach($message->tool_calls as $tool)
                                        <details class="rounded bg-gray-200 dark:bg-gray-700 p-2 text-xs">
                                            <summary class="cursor-pointer font-mono">{{ $tool['name'] ?? 'Tool' }}</summary>
                                            <pre class="mt-1 overflow-x-auto">{{ json_encode($tool['input'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endforeach
                                </div>
                            @endif

                            @if($message->tokens_in || $message->tokens_out)
                                <div class="mt-2 text-xs text-gray-500">
                                    {{ number_format($message->tokens_in ?? 0) }} in /
                                    {{ number_format($message->tokens_out ?? 0) }} out
                                    @if($message->cost_usd)
                                        (${{ number_format($message->cost_usd, 4) }})
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="flex h-full items-center justify-center text-gray-500">
                    <p>Start a conversation with Claude Code</p>
                </div>
            @endforelse

            @if($this->isRunning)
                <div class="flex justify-start">
                    <div class="rounded-lg bg-gray-100 px-4 py-2 dark:bg-gray-800">
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></div>
                            <span class="text-sm text-gray-500">Claude is thinking...</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Input --}}
        <div class="border-t p-4 dark:border-gray-700">
            <form wire:submit="sendMessage" class="flex gap-2">
                <textarea
                    wire:model="prompt"
                    placeholder="Type your message..."
                    rows="2"
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                    @disabled($this->isRunning)
                ></textarea>
                <button
                    type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700 disabled:opacity-50"
                    @disabled($this->isRunning || empty($prompt))
                >
                    Send
                </button>
            </form>
        </div>
    </div>
</div>
```

**Step 3: Update Filament page**

Edit `app/Filament/Resources/SiteResource/Pages/SiteChat.php`:

```php
<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Models\Site;
use Filament\Resources\Pages\Page;

class SiteChat extends Page
{
    protected static string $resource = SiteResource::class;

    protected static string $view = 'filament.resources.site-resource.pages.site-chat';

    public Site $record;

    public function mount($record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless($this->record->isActive(), 403, 'Site is not active');
    }

    public function getTitle(): string
    {
        return "Chat - {$this->record->domain}";
    }
}
```

Update `resources/views/filament/resources/site-resource/pages/site-chat.blade.php`:

```blade
<x-filament-panels::page>
    @livewire('site-chat', ['site' => $this->record])
</x-filament-panels::page>
```

**Step 4: Write tests**

Create `tests/Feature/Livewire/SiteChatTest.php`:

```php
<?php

use App\Enums\MessageRole;
use App\Jobs\RunClaudeMessageJob;
use App\Livewire\SiteChat;
use App\Models\Message;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can render chat component', function () {
    $site = Site::factory()->active()->create();

    Livewire::test(SiteChat::class, ['site' => $site])
        ->assertSuccessful()
        ->assertSee('Start a conversation');
});

test('can send a message', function () {
    Queue::fake();

    $site = Site::factory()->active()->create();

    Livewire::test(SiteChat::class, ['site' => $site])
        ->set('prompt', 'Hello Claude!')
        ->call('sendMessage');

    expect(Task::where('site_id', $site->id)->exists())->toBeTrue();
    expect(Message::where('content', 'Hello Claude!')->exists())->toBeTrue();

    Queue::assertPushed(RunClaudeMessageJob::class);
});

test('shows existing messages', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    Message::factory()->user()->create([
        'task_id' => $task->id,
        'content' => 'Test message',
    ]);

    Livewire::test(SiteChat::class, ['site' => $site])
        ->assertSee('Test message');
});

test('can start new chat', function () {
    Queue::fake();

    $site = Site::factory()->active()->create();
    $existingTask = Task::factory()->create(['site_id' => $site->id]);

    Livewire::test(SiteChat::class, ['site' => $site])
        ->call('newChat')
        ->set('prompt', 'New conversation')
        ->call('sendMessage');

    expect(Task::where('site_id', $site->id)->count())->toBe(2);
});

test('can select different task', function () {
    $site = Site::factory()->active()->create();
    $task1 = Task::factory()->create(['site_id' => $site->id]);
    $task2 = Task::factory()->create(['site_id' => $site->id]);

    Message::factory()->user()->create(['task_id' => $task1->id, 'content' => 'Task 1 message']);
    Message::factory()->user()->create(['task_id' => $task2->id, 'content' => 'Task 2 message']);

    Livewire::test(SiteChat::class, ['site' => $site])
        ->call('selectTask', $task1->id)
        ->assertSee('Task 1 message');
});
```

**Step 5: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Livewire/SiteChatTest.php`

Expected: 5 tests pass

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: add SiteChat Livewire component"
```

---

## Phase 7: Polling API

### Task 15: Create Messages API Endpoint

**Files:**
- Create: `app/Http/Controllers/Api/TaskMessagesController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/TaskMessagesTest.php`

**Step 1: Create controller**

Run: `php artisan make:controller Api/TaskMessagesController --no-interaction`

Edit `app/Http/Controllers/Api/TaskMessagesController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskMessagesController extends Controller
{
    public function index(Request $request, Task $task): JsonResponse
    {
        $sinceId = $request->query('since');

        $query = $task->messages()->oldest();

        if ($sinceId) {
            $query->where('id', '>', $sinceId);
        }

        $messages = $query->get()->map(fn ($message) => [
            'id' => $message->id,
            'role' => $message->role->value,
            'content' => $message->content,
            'tool_calls' => $message->tool_calls,
            'tokens_in' => $message->tokens_in,
            'tokens_out' => $message->tokens_out,
            'cost_usd' => $message->cost_usd,
            'created_at' => $message->created_at->toISOString(),
        ]);

        return response()->json([
            'task' => [
                'uuid' => $task->uuid,
                'status' => $task->status->value,
            ],
            'messages' => $messages,
        ]);
    }
}
```

**Step 2: Add route**

Edit `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\TaskMessagesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tasks/{task}/messages', [TaskMessagesController::class, 'index'])
        ->name('api.tasks.messages');
});
```

**Step 3: Write tests**

Create `tests/Feature/Api/TaskMessagesTest.php`:

```php
<?php

use App\Enums\TaskStatus;
use App\Models\Message;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

test('can fetch task messages', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    Message::factory()->user()->create(['task_id' => $task->id]);
    Message::factory()->assistant()->create(['task_id' => $task->id]);

    $response = $this->getJson(route('api.tasks.messages', $task));

    $response->assertOk()
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('task.uuid', $task->uuid);
});

test('can fetch messages since id', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message1 = Message::factory()->create(['task_id' => $task->id]);
    $message2 = Message::factory()->create(['task_id' => $task->id]);

    $response = $this->getJson(route('api.tasks.messages', [
        'task' => $task,
        'since' => $message1->id,
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.id', $message2->id);
});

test('returns task status', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->running()->create(['site_id' => $site->id]);

    $response = $this->getJson(route('api.tasks.messages', $task));

    $response->assertOk()
        ->assertJsonPath('task.status', TaskStatus::Running->value);
});
```

**Step 4: Run tests**

Run: `php artisan migrate:fresh && php artisan test tests/Feature/Api/TaskMessagesTest.php`

Expected: 3 tests pass

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add task messages API endpoint"
```

---

## Final Steps

### Task 16: Run Full Test Suite

**Step 1: Run all tests**

Run: `php artisan migrate:fresh && php artisan test`

Expected: All tests pass

**Step 2: Run Pint**

Run: `vendor/bin/pint`

**Step 3: Commit any fixes**

```bash
git add -A
git commit -m "chore: fix code style with Pint"
```

---

## Summary

This plan implements:

1. **Phase 1**: Core models (GitHubConnection, Repository, Site, Task, Message) with migrations, factories, and tests
2. **Phase 2**: GitHub OAuth via Socialite with settings page
3. **Phase 3**: Repository sync service and Filament resource
4. **Phase 4**: Site provisioning with Ploi CLI placeholder
5. **Phase 5**: Claude Code execution via queue jobs
6. **Phase 6**: Livewire chat interface
7. **Phase 7**: Polling API for real-time updates

Total: 16 tasks, ~140 steps
