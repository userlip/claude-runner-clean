<?php

use App\Enums\PersonaStatus;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Filament\Resources\Personas\Pages\EditPersona;
use App\Filament\Resources\Personas\Pages\ListPersonas;
use App\Jobs\RunPersonaCycleJob;
use App\Jobs\RunScheduledTaskJob;
use App\Models\Persona;
use App\Models\Repository;
use App\Models\TaskSchedule;
use App\Models\User;
use App\Services\PersonaCycleService;
use App\Services\PersonaStorageService;
use App\Services\TelegramService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Queue::fake();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->repository = Repository::factory()->create(['user_id' => $this->user->id]);

    $this->persona = Persona::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'master_prompt' => 'Analyze SEO performance and identify improvement opportunities.',
        'mcp_guidance' => 'Use Google Analytics and Search Console MCPs for data.',
        'description' => 'SEO analysis specialist',
    ]);
});

afterEach(function () {
    if (isset($this->persona)) {
        $path = $this->persona->getStoragePath();
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }
});

test('PersonaCycleService builds state context from persistent files', function () {
    $storageService = app(PersonaStorageService::class);
    $storageService->initializeStorage($this->persona);

    // Write some history
    $historyPath = "{$this->persona->getStoragePath()}/history/2026-03-08-cycle-1.md";
    File::put($historyPath, '# Cycle 1 content');

    // Write a completed plan
    $planPath = "{$this->persona->getStoragePath()}/completed-plans/2026-03-07-seo-fix.md";
    File::put($planPath, '# Previous plan content');

    $cycleService = app(PersonaCycleService::class);
    $state = $cycleService->buildStateContext($this->persona);

    expect($state['context'])->not->toBeNull();
    expect($state['history'])->not->toBeEmpty();
    expect($state['history'][0]['content'])->toContain('Cycle 1 content');
    expect($state['completed_plans'])->not->toBeEmpty();
    expect($state['completed_plans'][0])->toContain('Previous plan content');
});

test('PersonaCycleService builds analysis prompt with all components', function () {
    $storageService = app(PersonaStorageService::class);
    $storageService->initializeStorage($this->persona);

    $cycleService = app(PersonaCycleService::class);
    $prompt = $cycleService->buildAnalysisPrompt($this->persona);

    expect($prompt)->toContain('Analysis Cycle for Persona')
        ->toContain($this->persona->name)
        ->toContain('Master Prompt')
        ->toContain('Analyze SEO performance')
        ->toContain('MCP Tools Guidance')
        ->toContain('Google Analytics')
        ->toContain('EXECUTIVE_SUMMARY_START')
        ->toContain('EXECUTIVE_SUMMARY_END')
        ->toContain('DETAILED_REPORT_START')
        ->toContain('DETAILED_REPORT_END');
});

test('PersonaCycleService parses analysis output with markers', function () {
    $cycleService = app(PersonaCycleService::class);

    $output = <<<'TEXT'
    Some preamble text.

    EXECUTIVE_SUMMARY_START
    The website SEO performance has improved by 15% this month.
    Key areas for improvement include meta descriptions and internal linking.
    EXECUTIVE_SUMMARY_END

    DETAILED_REPORT_START
    ## SEO Analysis Report

    ### Metrics
    - Organic traffic: +15%
    - Bounce rate: -3%

    ### Recommendations
    1. Update meta descriptions on 25 pages
    2. Add internal links to blog posts
    DETAILED_REPORT_END
    TEXT;

    $parsed = $cycleService->parseAnalysisOutput($output);

    expect($parsed['description'])->toContain('website SEO performance has improved')
        ->toContain('meta descriptions and internal linking');
    expect($parsed['data_appendix'])->toContain('SEO Analysis Report')
        ->toContain('Organic traffic: +15%')
        ->toContain('Update meta descriptions on 25 pages');
});

test('PersonaCycleService parses analysis output without markers uses fallback', function () {
    $cycleService = app(PersonaCycleService::class);

    $output = "First paragraph summary.\n\nSecond paragraph with more details.\n\nThird paragraph with data.\n\nFourth paragraph with recommendations.";

    $parsed = $cycleService->parseAnalysisOutput($output);

    expect($parsed['description'])->not->toBeEmpty();
    expect($parsed['data_appendix'])->not->toBeEmpty();
});

test('PersonaCycleService creates proposal from analysis', function () {
    $cycleService = app(PersonaCycleService::class);

    $proposal = $cycleService->createProposalFromAnalysis(
        $this->persona,
        'SEO improvements needed',
        '## Detailed report data'
    );

    expect($proposal->id)->not->toBeNull();
    expect($proposal->persona_id)->toBe($this->persona->id);
    expect($proposal->description)->toBe('SEO improvements needed');
    expect($proposal->data_appendix)->toBe('## Detailed report data');
    expect($proposal->type)->toBe(ProposalType::SeoImprovement);
    expect($proposal->status)->toBe(ProposalStatus::Pending);
    expect($proposal->project)->toBe($this->repository->name);
    expect($proposal->title)->toContain($this->persona->name);
    expect($proposal->title)->toContain('Analysis Cycle #1');
});

test('PersonaCycleService logs cycle to history', function () {
    $storageService = app(PersonaStorageService::class);
    $storageService->initializeStorage($this->persona);

    $cycleService = app(PersonaCycleService::class);

    $proposal = $cycleService->createProposalFromAnalysis(
        $this->persona,
        'Test summary',
        '## Test data'
    );

    $cycleService->logCycleToHistory($this->persona, $proposal, 1);

    $historyPath = "{$this->persona->getStoragePath()}/history";
    $files = File::files($historyPath);

    expect($files)->not->toBeEmpty();

    $content = File::get($files[0]->getPathname());
    expect($content)->toContain('Cycle #1')
        ->toContain($this->persona->name)
        ->toContain($proposal->title);
});

test('full analysis cycle with mocked Claude process produces valid Proposal', function () {
    $storageService = app(PersonaStorageService::class);
    $storageService->initializeStorage($this->persona);

    $telegramService = Mockery::mock(TelegramService::class);
    $telegramService->shouldReceive('sendPersonaCycleNotification')->zeroOrMoreTimes();
    $telegramService->shouldReceive('sendProposalNotification')->zeroOrMoreTimes();
    $telegramService->shouldReceive('sendPlainMessage')->zeroOrMoreTimes();
    app()->instance(TelegramService::class, $telegramService);

    // Test the service methods directly (since the job uses proc_open which we can't mock easily)
    $cycleService = app(PersonaCycleService::class);

    // Simulate what RunPersonaCycleJob does after receiving Claude output
    $mockOutput = <<<'TEXT'
    EXECUTIVE_SUMMARY_START
    Website SEO analysis reveals 3 critical improvements needed.
    Meta descriptions are missing on 40% of pages.
    EXECUTIVE_SUMMARY_END

    DETAILED_REPORT_START
    ## Full Analysis

    ### Missing Meta Descriptions
    - /about: No meta description
    - /services: No meta description
    - /contact: Generic meta description

    ### Recommendations
    1. Add unique meta descriptions to all pages
    2. Implement structured data markup
    3. Optimize internal link structure
    DETAILED_REPORT_END
    TEXT;

    $parsed = $cycleService->parseAnalysisOutput($mockOutput);

    $proposal = $cycleService->createProposalFromAnalysis(
        $this->persona,
        $parsed['description'],
        $parsed['data_appendix']
    );

    $cycleNumber = ($this->persona->total_runs ?? 0) + 1;
    $cycleService->logCycleToHistory($this->persona, $proposal, $cycleNumber);

    // Update persona stats (same as job does)
    $this->persona->update([
        'last_run_at' => now(),
        'total_runs' => $cycleNumber,
        'total_proposals' => ($this->persona->total_proposals ?? 0) + 1,
        'last_proposal_id' => $proposal->id,
        'status' => PersonaStatus::AwaitingApproval,
    ]);

    // Verify proposal
    expect($proposal->persona_id)->toBe($this->persona->id);
    expect($proposal->status)->toBe(ProposalStatus::Pending);
    expect($proposal->type)->toBe(ProposalType::SeoImprovement);
    expect($proposal->description)->toContain('SEO analysis reveals 3 critical improvements');
    expect($proposal->data_appendix)->toContain('Missing Meta Descriptions');

    // Verify persona updated
    $this->persona->refresh();
    expect($this->persona->last_run_at)->not->toBeNull();
    expect($this->persona->total_runs)->toBe(1);
    expect($this->persona->total_proposals)->toBe(1);
    expect($this->persona->last_proposal_id)->toBe($proposal->id);
    expect($this->persona->status)->toBe(PersonaStatus::AwaitingApproval);

    // Verify history file
    $historyFiles = $storageService->getHistoryFiles($this->persona);
    expect($historyFiles)->not->toBeEmpty();
    expect($historyFiles[0]['content'])->toContain('Cycle #1');
});

test('schedule integration dispatches RunPersonaCycleJob for persona schedules', function () {
    Queue::fake();

    $schedule = TaskSchedule::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'persona_id' => $this->persona->id,
        'cron_expression' => '* * * * *', // every minute (always due)
        'is_active' => true,
        'last_run_at' => null,
    ]);

    $this->artisan('tasks:run-schedules')
        ->assertExitCode(0);

    Queue::assertPushed(RunPersonaCycleJob::class, function (RunPersonaCycleJob $job) {
        return $job->persona->id === $this->persona->id;
    });

    Queue::assertNotPushed(RunScheduledTaskJob::class);
});

test('schedule integration dispatches RunScheduledTaskJob for non-persona schedules', function () {
    Queue::fake();

    $schedule = TaskSchedule::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'persona_id' => null,
        'cron_expression' => '* * * * *',
        'is_active' => true,
        'last_run_at' => null,
    ]);

    $this->artisan('tasks:run-schedules')
        ->assertExitCode(0);

    Queue::assertPushed(RunScheduledTaskJob::class);
    Queue::assertNotPushed(RunPersonaCycleJob::class);
});

test('Run Now action on list page dispatches job', function () {
    Queue::fake();

    livewire(ListPersonas::class)
        ->callTableAction('run_now', $this->persona)
        ->assertNotified('Analysis cycle dispatched');

    Queue::assertPushed(RunPersonaCycleJob::class, function (RunPersonaCycleJob $job) {
        return $job->persona->id === $this->persona->id;
    });
});

test('Run Now action disabled when persona is inactive', function () {
    $this->persona->update([
        'is_active' => false,
        'status' => PersonaStatus::Paused,
    ]);

    livewire(ListPersonas::class)
        ->assertTableActionDisabled('run_now', $this->persona);
});

test('Run Now action disabled when persona is already running', function () {
    $this->persona->update([
        'status' => PersonaStatus::Running,
    ]);

    livewire(ListPersonas::class)
        ->assertTableActionDisabled('run_now', $this->persona);
});

test('Run Now action on edit page dispatches job', function () {
    Queue::fake();

    livewire(EditPersona::class, ['record' => $this->persona->id])
        ->callAction('run_now')
        ->assertNotified('Analysis cycle dispatched');

    Queue::assertPushed(RunPersonaCycleJob::class, function (RunPersonaCycleJob $job) {
        return $job->persona->id === $this->persona->id;
    });
});

test('RunPersonaCycleJob has 3-hour timeout', function () {
    $job = new RunPersonaCycleJob($this->persona);

    expect($job->timeout)->toBe(10800);
});
