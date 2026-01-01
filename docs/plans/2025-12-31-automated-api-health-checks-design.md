# Automated API Health Checks Design

## Overview

Periodic automation system that creates Tasks to test Scrappa APIs using GLM (z.ai) provider. Every 2 hours, it randomly selects an API and skill, creates a new Task with fresh workspace, and runs the skill autonomously.

## Goals

1. Automatically test Scrappa APIs on a schedule
2. Use GLM provider (not Claude) for cost efficiency
3. Random selection of API and skill per run
4. Full visibility into automated conversations via Filament
5. RapidAPI updates happen autonomously; code changes create PRs

## Skills Used

| Skill | Purpose |
|-------|---------|
| `scrappa-endpoint-testing` | Tests Scrappa API endpoints (playground, docs, OpenAPI, parameters) |
| `rapidapi-publishing` | Verifies/fixes RapidAPI marketplace listings |

## Data Model

### ScrappApi Model

```php
// app/Models/ScrappApi.php
class ScrappApi extends Model
{
    protected $fillable = [
        'name',              // "Google Maps", "Amazon Search"
        'slug',              // "google-maps", "amazon-search"
        'route_prefix',      // "api/google-maps" - for endpoint testing
        'rapidapi_slug',     // "google-maps-scraper" - for RapidAPI skill
        'is_active',         // Enable/disable from rotation
        'last_tested_at',
        'last_test_result',  // 'passed', 'failed', 'running', 'pending'
        'notes',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'scrapp_api_id');
    }

    public function latestTask(): HasOne
    {
        return $this->hasOne(Task::class, 'scrapp_api_id')->latestOfMany();
    }
}
```

### Migration: scrapp_apis table

```php
Schema::create('scrapp_apis', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('route_prefix');
    $table->string('rapidapi_slug')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_tested_at')->nullable();
    $table->string('last_test_result')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

### Migration: Add scrapp_api_id to tasks

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->foreignId('scrapp_api_id')->nullable()->constrained()->nullOnDelete();
});
```

## Automation Flow

```
Every 2 hours (Laravel Scheduler):
┌─────────────────────────────────────────────────────────────┐
│ 1. Pick random active ScrappApi from database               │
│ 2. Pick random skill: 'scrappa-endpoint-testing' OR         │
│    'rapidapi-publishing'                                    │
│ 3. Get Scrappa Repository (by name)                         │
│ 4. Get GLM AiProvider                                       │
│ 5. Create Task with:                                        │
│    - repository_id = Scrappa repo                           │
│    - ai_provider_id = GLM provider                          │
│    - scrapp_api_id = selected API                           │
│    - title = "Auto: Test {API name} with {skill}"           │
│ 6. Chain jobs:                                              │
│    CloneRepositoryJob → RunApiHealthCheckJob                │
│ 7. RunApiHealthCheckJob sends skill prompt to GLM           │
│ 8. Update ScrappApi.last_tested_at and last_test_result     │
└─────────────────────────────────────────────────────────────┘
```

## Jobs

### RunApiHealthCheckJob

Sends the skill prompt after workspace is ready:

```php
// app/Jobs/RunApiHealthCheckJob.php
class RunApiHealthCheckJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(
        public Task $task,
        public ScrappApi $api,
        public string $skill,
    ) {}

    public function handle(): void
    {
        $prompt = $this->buildSkillPrompt();

        $message = $this->task->messages()->create([
            'role' => 'user',
            'content' => $prompt,
        ]);

        $this->api->update([
            'last_tested_at' => now(),
            'last_test_result' => 'running',
        ]);

        RunClaudeMessageJob::dispatch($this->task, $message);
    }

    protected function buildSkillPrompt(): string
    {
        return match ($this->skill) {
            'scrappa-endpoint-testing' =>
                "Run /scrappa-endpoint-testing for the {$this->api->name} API. " .
                "Route prefix: {$this->api->route_prefix}. " .
                "If you find issues that need code changes, create a PR.",

            'rapidapi-publishing' =>
                "Run /rapidapi-publishing for {$this->api->name}. " .
                "RapidAPI slug: {$this->api->rapidapi_slug}. " .
                "You have full autonomy to update RapidAPI directly.",
        };
    }
}
```

## Artisan Command

```php
// app/Console/Commands/RunApiHealthCheckCommand.php
class RunApiHealthCheckCommand extends Command
{
    protected $signature = 'scrappa:health-check {--api= : Specific API ID to test}';
    protected $description = 'Run random API health check with GLM';

    public function handle(): int
    {
        // 1. Pick API (specific or random)
        $api = $this->option('api')
            ? ScrappApi::findOrFail($this->option('api'))
            : ScrappApi::where('is_active', true)->inRandomOrder()->first();

        if (!$api) {
            $this->error('No active APIs found');
            return self::FAILURE;
        }

        // 2. Pick random skill
        $skill = collect(['scrappa-endpoint-testing', 'rapidapi-publishing'])->random();

        // 3. Get Scrappa repo & GLM provider
        $repository = Repository::where('name', 'scrappa')->first();
        $glmProvider = AiProvider::where('name', 'glm')->first();

        if (!$repository || !$glmProvider) {
            $this->error('Scrappa repository or GLM provider not found');
            return self::FAILURE;
        }

        // 4. Create task
        $task = Task::create([
            'user_id' => 1,
            'repository_id' => $repository->id,
            'ai_provider_id' => $glmProvider->id,
            'scrapp_api_id' => $api->id,
            'title' => "Auto: {$skill} for {$api->name}",
        ]);

        // 5. Chain jobs: clone workspace, then send skill prompt
        Bus::chain([
            new CloneRepositoryJob($task),
            new RunApiHealthCheckJob($task, $api, $skill),
        ])->dispatch();

        $this->info("Started health check for {$api->name} with {$skill}");

        return self::SUCCESS;
    }
}
```

## Scheduler Entry

```php
// routes/console.php
Schedule::command('scrappa:health-check')->everyTwoHours();
```

## Filament Resource

### ScrappApiResource

```php
// app/Filament/Resources/ScrappApiResource.php
class ScrappApiResource extends Resource
{
    protected static ?string $model = ScrappApi::class;
    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationGroup = 'Automation';
    protected static ?string $navigationLabel = 'API Health Checks';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('last_tested_at')
                    ->dateTime()
                    ->sortable()
                    ->description(fn ($record) => $record->last_tested_at?->diffForHumans()),
                BadgeColumn::make('last_test_result')
                    ->colors([
                        'success' => 'passed',
                        'danger' => 'failed',
                        'warning' => 'running',
                        'gray' => 'pending',
                    ]),
                TextColumn::make('latestTask.title')
                    ->label('Latest Task')
                    ->limit(30)
                    ->url(fn ($record) => $record->latestTask
                        ? TaskResource::getUrl('view', ['record' => $record->latestTask])
                        : null
                    ),
            ])
            ->actions([
                Action::make('view_chat')
                    ->label('View Chat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (ScrappApi $record) => $record->latestTask
                        ? TaskResource::getUrl('view', ['record' => $record->latestTask])
                        : null
                    )
                    ->visible(fn (ScrappApi $record) => $record->latestTask !== null),

                Action::make('test_now')
                    ->label('Test Now')
                    ->icon('heroicon-o-play')
                    ->action(function (ScrappApi $record) {
                        Artisan::call('scrappa:health-check', ['--api' => $record->id]);
                        Notification::make()->success()->title('Health check started')->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('last_tested_at', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('slug')->required()->unique(ignoreRecord: true),
            TextInput::make('route_prefix')->required()->helperText('e.g., api/google-maps'),
            TextInput::make('rapidapi_slug')->helperText('e.g., google-maps-scraper'),
            Toggle::make('is_active')->default(true),
            Textarea::make('notes'),
        ]);
    }
}
```

## Files to Create

| File | Purpose |
|------|---------|
| `app/Models/ScrappApi.php` | Model for APIs in rotation |
| `database/migrations/2025_12_31_000001_create_scrapp_apis_table.php` | Database table |
| `database/migrations/2025_12_31_000002_add_scrapp_api_id_to_tasks_table.php` | Link tasks to APIs |
| `app/Jobs/RunApiHealthCheckJob.php` | Sends skill prompt after workspace ready |
| `app/Console/Commands/RunApiHealthCheckCommand.php` | Artisan command for scheduler |
| `app/Filament/Resources/ScrappApiResource.php` | Admin UI for managing APIs |
| `app/Filament/Resources/ScrappApiResource/Pages/ListScrappApis.php` | List page |
| `app/Filament/Resources/ScrappApiResource/Pages/CreateScrappApi.php` | Create page |
| `app/Filament/Resources/ScrappApiResource/Pages/EditScrappApi.php` | Edit page |
| `database/seeders/ScrappApiSeeder.php` | Initial API data |
| `routes/console.php` | Add scheduler entry |
| `tests/Feature/Jobs/RunApiHealthCheckJobTest.php` | Job tests |
| `tests/Feature/Console/RunApiHealthCheckCommandTest.php` | Command tests |

## Seeder

Discover APIs from Scrappa routes and seed initial data:

```php
// database/seeders/ScrappApiSeeder.php
class ScrappApiSeeder extends Seeder
{
    public function run(): void
    {
        $apis = [
            // To be populated from Scrappa routes discovery
            // Example structure:
            // ['name' => 'Google Maps', 'slug' => 'google-maps', 'route_prefix' => 'api/google-maps', 'rapidapi_slug' => 'google-maps-scraper'],
        ];

        foreach ($apis as $api) {
            ScrappApi::updateOrCreate(
                ['slug' => $api['slug']],
                $api + ['is_active' => true]
            );
        }
    }
}
```

## Future Enhancements

1. **Telegram notifications** - Alert when tests fail
2. **Test result parsing** - Parse GLM output to determine pass/fail automatically
3. **Scheduling flexibility** - Different intervals for different APIs
4. **Test history** - Track all test runs, not just latest
