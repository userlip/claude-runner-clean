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
        ->toContain('PROPOSAL_START')
        ->toContain('PROPOSAL_END')
        ->toContain('TITLE:')
        ->toContain('PRIORITY:')
        ->toContain('DESCRIPTION:')
        ->toContain('DETAILED_REPORT_START')
        ->toContain('DETAILED_REPORT_END');
});

test('PersonaCycleService parses analysis output with markers', function () {
    $cycleService = app(PersonaCycleService::class);

    $output = <<<'TEXT'
    Some preamble text.

    PROPOSAL_START
    TITLE: Update meta descriptions on key landing pages
    PRIORITY: high
    DESCRIPTION: The website SEO performance has improved by 15% this month, but key landing pages still need stronger meta descriptions.
    PROPOSAL_END

    PROPOSAL_START
    TITLE: Add internal links to high-value blog posts
    PRIORITY: medium
    DESCRIPTION: Internal linking remains a clear opportunity across recent blog content.
    PROPOSAL_END

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

    expect($parsed['proposals'])->toHaveCount(2);
    expect($parsed['proposals'][0]['title'])->toBe('Update meta descriptions on key landing pages');
    expect($parsed['proposals'][0]['priority'])->toBe('high');
    expect($parsed['proposals'][0]['description'])->toContain('website SEO performance has improved');
    expect($parsed['proposals'][1]['title'])->toBe('Add internal links to high-value blog posts');
    expect($parsed['data_appendix'])->toContain('SEO Analysis Report')
        ->toContain('Organic traffic: +15%')
        ->toContain('Update meta descriptions on 25 pages');
});

test('PersonaCycleService parses analysis output without markers uses fallback', function () {
    $cycleService = app(PersonaCycleService::class);

    $output = "First paragraph summary.\n\nSecond paragraph with more details.\n\nThird paragraph with data.\n\nFourth paragraph with recommendations.";

    $parsed = $cycleService->parseAnalysisOutput($output);

    expect($parsed['proposals'])->toHaveCount(1);
    expect($parsed['proposals'][0]['title'])->toBe('Analysis cycle completed — review findings');
    expect($parsed['proposals'][0]['description'])->not->toBeEmpty();
    expect($parsed['data_appendix'])->toBe('');
});

test('PersonaCycleService creates proposals from analysis', function () {
    $cycleService = app(PersonaCycleService::class);

    $proposals = $cycleService->createProposalsFromAnalysis(
        $this->persona,
        [
            [
                'title' => 'Update homepage metadata',
                'priority' => 'high',
                'description' => 'SEO improvements needed on the homepage.',
            ],
            [
                'title' => 'Improve blog internal linking',
                'priority' => 'medium',
                'description' => 'Strengthen topical authority with more internal links.',
            ],
        ],
        '## Detailed report data'
    );

    expect($proposals)->toHaveCount(2);
    expect($proposals[0]->id)->not->toBeNull();
    expect($proposals[0]->persona_id)->toBe($this->persona->id);
    expect($proposals[0]->description)->toBe('SEO improvements needed on the homepage.');
    expect($proposals[0]->data_appendix)->toBe('## Detailed report data');
    expect($proposals[0]->type)->toBe(ProposalType::SeoImprovement);
    expect($proposals[0]->status)->toBe(ProposalStatus::Pending);
    expect($proposals[0]->project)->toBe($this->repository->name);
    expect($proposals[0]->title)->toBe('Update homepage metadata');
    expect($proposals[1]->title)->toBe('Improve blog internal linking');
    expect($proposals[1]->data_appendix)->toBeNull();
});

test('PersonaCycleService logs cycle to history', function () {
    $storageService = app(PersonaStorageService::class);
    $storageService->initializeStorage($this->persona);

    $cycleService = app(PersonaCycleService::class);

    $proposals = $cycleService->createProposalsFromAnalysis(
        $this->persona,
        [[
            'title' => 'Test proposal',
            'priority' => 'medium',
            'description' => 'Test summary',
        ]],
        '## Test data'
    );
    $proposal = $proposals[0];

    $cycleService->logCycleToHistory($this->persona, $proposal, 1);

    $historyPath = "{$this->persona->getStoragePath()}/history";
    $files = File::files($historyPath);

    expect($files)->not->toBeEmpty();

    $content = File::get($files[0]->getPathname());
    expect($content)->toContain('Cycle #1')
        ->toContain($this->persona->name)
        ->toContain($proposal->title);
});

test('full analysis cycle with mocked Claude process produces valid proposals', function () {
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
    PROPOSAL_START
    TITLE: Add unique meta descriptions to core pages
    PRIORITY: critical
    DESCRIPTION: Website SEO analysis reveals missing meta descriptions on 40% of pages.
    PROPOSAL_END

    PROPOSAL_START
    TITLE: Implement structured data markup
    PRIORITY: high
    DESCRIPTION: Structured data is missing from high-intent pages and should be added next.
    PROPOSAL_END

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

    $proposals = $cycleService->createProposalsFromAnalysis(
        $this->persona,
        $parsed['proposals'],
        $parsed['data_appendix']
    );
    $proposal = $proposals[0];
    $lastProposal = end($proposals);

    $cycleNumber = ($this->persona->total_runs ?? 0) + 1;
    $cycleService->logCycleToHistory($this->persona, $proposal, $cycleNumber);

    // Update persona stats (same as job does)
    $this->persona->update([
        'last_run_at' => now(),
        'total_runs' => $cycleNumber,
        'total_proposals' => ($this->persona->total_proposals ?? 0) + count($proposals),
        'last_proposal_id' => $lastProposal->id,
        'status' => PersonaStatus::AwaitingApproval,
    ]);

    // Verify proposals
    expect($proposals)->toHaveCount(2);
    expect($proposal->persona_id)->toBe($this->persona->id);
    expect($proposal->status)->toBe(ProposalStatus::Pending);
    expect($proposal->type)->toBe(ProposalType::SeoImprovement);
    expect($proposal->description)->toContain('missing meta descriptions on 40% of pages');
    expect($proposal->data_appendix)->toContain('Missing Meta Descriptions');

    // Verify persona updated
    $this->persona->refresh();
    expect($this->persona->last_run_at)->not->toBeNull();
    expect($this->persona->total_runs)->toBe(1);
    expect($this->persona->total_proposals)->toBe(2);
    expect($this->persona->last_proposal_id)->toBe($lastProposal->id);
    expect($this->persona->status)->toBe(PersonaStatus::AwaitingApproval);

    // Verify history file
    $historyFiles = $storageService->getHistoryFiles($this->persona);
    expect($historyFiles)->not->toBeEmpty();
    expect($historyFiles[0]['content'])->toContain('Cycle #1');
});

test('RunPersonaCycleJob does not duplicate proposals when final result repeats streamed text', function () {
    $job = new RunPersonaCycleJob($this->persona);
    $method = new ReflectionMethod($job, 'mergeResultText');
    $method->setAccessible(true);

    $streamedText = <<<'TEXT'
    PROPOSAL_START
    TITLE: Fix pricing page meta description to improve CTR
    PRIORITY: high
    DESCRIPTION: The pricing page has impressions but no clicks, so the snippet needs a stronger hook.
    PROPOSAL_END

    PROPOSAL_START
    TITLE: Fix duplicate google_single_review URL in sitemap.xml
    PRIORITY: medium
    DESCRIPTION: Removing the duplicate sitemap entry avoids confusing crawlers.
    PROPOSAL_END

    DETAILED_REPORT_START
    Report body
    DETAILED_REPORT_END
    TEXT;

    $mergedText = $method->invoke($job, $streamedText, $streamedText);

    $parsed = app(PersonaCycleService::class)->parseAnalysisOutput($mergedText);

    expect($mergedText)->toBe($streamedText);
    expect($parsed['proposals'])->toHaveCount(2);
    expect($parsed['proposals'][0]['title'])->toBe('Fix pricing page meta description to improve CTR');
    expect($parsed['proposals'][1]['title'])->toBe('Fix duplicate google_single_review URL in sitemap.xml');
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
