<?php

use App\Filament\Resources\ProposalResource\Pages\ListProposals;
use App\Filament\Resources\ProposalResource\Pages\ViewProposal;
use App\Models\Persona;
use App\Models\Proposal;
use App\Models\User;
use App\Services\PersonaCycleService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

afterEach(function () {
    if (isset($this->persona)) {
        $path = $this->persona->getStoragePath();
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }
});

test('persona filtering on proposals list works', function () {
    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    $personaProposal = Proposal::factory()->create([
        'persona_id' => $this->persona->id,
        'title' => 'Persona Proposal',
    ]);

    $regularProposal = Proposal::factory()->create([
        'persona_id' => null,
        'title' => 'Regular Proposal',
    ]);

    livewire(ListProposals::class)
        ->assertCanSeeTableRecords([$personaProposal, $regularProposal])
        ->filterTable('persona', $this->persona->id)
        ->assertCanSeeTableRecords([$personaProposal])
        ->assertCanNotSeeTableRecords([$regularProposal]);
});

test('approve subtasks action sets subtasks_approved_at', function () {
    $mockService = Mockery::mock(PersonaCycleService::class);
    $mockService->shouldReceive('startSubtaskExecution')->once()->andReturn(new \App\Models\Task);
    app()->instance(PersonaCycleService::class, $mockService);

    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    $proposal = Proposal::factory()->create([
        'persona_id' => $this->persona->id,
        'subtasks' => [
            ['index' => 0, 'title' => 'First subtask', 'description' => 'Do first thing', 'status' => 'pending'],
            ['index' => 1, 'title' => 'Second subtask', 'description' => 'Do second thing', 'status' => 'pending'],
        ],
        'subtasks_approved_at' => null,
    ]);

    expect($proposal->hasSubtasksPendingApproval())->toBeTrue();
    expect($proposal->areSubtasksApproved())->toBeFalse();

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertActionVisible('approve_subtasks')
        ->callAction('approve_subtasks')
        ->assertNotified('Subtasks approved — execution starting');

    $proposal->refresh();
    expect($proposal->subtasks_approved_at)->not->toBeNull();
    expect($proposal->areSubtasksApproved())->toBeTrue();
    expect($proposal->hasSubtasksPendingApproval())->toBeFalse();
});

test('approve subtasks action hidden when subtasks already approved', function () {
    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    $proposal = Proposal::factory()->create([
        'persona_id' => $this->persona->id,
        'subtasks' => [
            ['index' => 0, 'title' => 'First subtask', 'description' => 'Do thing', 'status' => 'completed'],
        ],
        'subtasks_approved_at' => now(),
    ]);

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertActionHidden('approve_subtasks');
});

test('approve subtasks action hidden when no subtasks exist', function () {
    $proposal = Proposal::factory()->create([
        'subtasks' => null,
    ]);

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertActionHidden('approve_subtasks');
});

test('view page renders data appendix as collapsible section', function () {
    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    $proposal = Proposal::factory()->create([
        'persona_id' => $this->persona->id,
        'data_appendix' => '# Detailed Analysis Report',
    ]);

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertSuccessful();
});

test('view page renders subtask list with status badges', function () {
    $this->persona = Persona::factory()->create(['user_id' => $this->user->id]);

    $proposal = Proposal::factory()->create([
        'persona_id' => $this->persona->id,
        'subtasks' => [
            ['index' => 0, 'title' => 'Completed task', 'description' => 'Done', 'status' => 'completed'],
            ['index' => 1, 'title' => 'Running task', 'description' => 'In progress', 'status' => 'running'],
            ['index' => 2, 'title' => 'Pending task', 'description' => 'Not started', 'status' => 'pending'],
            ['index' => 3, 'title' => 'Failed task', 'description' => 'Error', 'status' => 'failed'],
        ],
        'current_subtask_index' => 1,
    ]);

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Completed task')
        ->assertSee('Running task')
        ->assertSee('Pending task')
        ->assertSee('Failed task');
});

test('proposal model subtask helpers work correctly', function () {
    $proposal = Proposal::factory()->create([
        'subtasks' => [
            ['index' => 0, 'title' => 'First', 'description' => 'A', 'status' => 'completed'],
            ['index' => 1, 'title' => 'Second', 'description' => 'B', 'status' => 'pending'],
            ['index' => 2, 'title' => 'Third', 'description' => 'C', 'status' => 'pending'],
        ],
        'current_subtask_index' => 1,
        'subtasks_approved_at' => null,
    ]);

    // getCurrentSubtask returns the correct subtask
    $current = $proposal->getCurrentSubtask();
    expect($current)->not->toBeNull();
    expect($current['title'])->toBe('Second');

    // hasSubtasksPendingApproval
    expect($proposal->hasSubtasksPendingApproval())->toBeTrue();

    // advanceSubtaskIndex
    $proposal->advanceSubtaskIndex();
    $proposal->refresh();
    expect($proposal->current_subtask_index)->toBe(2);
    expect($proposal->getCurrentSubtask()['title'])->toBe('Third');

    // areSubtasksApproved after setting timestamp
    $proposal->update(['subtasks_approved_at' => now()]);
    expect($proposal->areSubtasksApproved())->toBeTrue();
    expect($proposal->hasSubtasksPendingApproval())->toBeFalse();
});

test('formatForTelegram includes persona name when persona_id set', function () {
    $this->persona = Persona::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'SEO Analyzer',
    ]);

    $proposal = Proposal::factory()->create([
        'persona_id' => $this->persona->id,
        'title' => 'SEO Improvement',
        'project' => 'claude_runner',
    ]);

    $message = $proposal->formatForTelegram();

    expect($message)->toContain('SEO Analyzer');
    expect($message)->toContain('SEO Improvement');
});

test('formatForTelegram uses standard format when no persona', function () {
    $proposal = Proposal::factory()->create([
        'persona_id' => null,
        'title' => 'Regular Proposal',
    ]);

    $message = $proposal->formatForTelegram();

    expect($message)->toContain('New Proposal');
    expect($message)->not->toContain('Persona Proposal');
    expect($message)->not->toContain('Persona:');
});
