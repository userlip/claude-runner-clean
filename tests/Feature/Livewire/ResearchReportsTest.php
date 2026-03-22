<?php

use App\Livewire\ResearchReports\Index;
use App\Livewire\ResearchReports\Show;
use App\Models\ResearchReport;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create();
});

// --- Access control ---

it('redirects unauthenticated users to login for research-reports index', function () {
    $this->get('/app/research-reports')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on research-reports index', function () {
    $this->actingAs($this->user)->get('/app/research-reports')->assertForbidden();
});

it('returns 200 for admin users on research-reports index', function () {
    $this->actingAs($this->admin)->get('/app/research-reports')->assertOk();
});

it('redirects unauthenticated users to login for research-report show', function () {
    $report = ResearchReport::factory()->create();
    $this->get("/app/research-reports/{$report->uuid}")->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on research-report show', function () {
    $report = ResearchReport::factory()->create();
    $this->actingAs($this->user)->get("/app/research-reports/{$report->uuid}")->assertForbidden();
});

it('returns 200 for admin users on research-report show', function () {
    $report = ResearchReport::factory()->create();
    $this->actingAs($this->admin)->get("/app/research-reports/{$report->uuid}")->assertOk();
});

// --- List component ---

it('renders the research reports index with table', function () {
    $report = ResearchReport::factory()->create(['title' => 'Test Research Report']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('Test Research Report');
});

it('shows multiple research reports in the list', function () {
    ResearchReport::factory()->create(['title' => 'Report Alpha']);
    ResearchReport::factory()->create(['title' => 'Report Beta']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSee('Report Alpha')
        ->assertSee('Report Beta');
});

// --- Show component ---

it('renders the research report show page with details', function () {
    $report = ResearchReport::factory()->create([
        'title' => 'Detailed Report',
        'summary' => 'This is the summary.',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(Show::class, ['report' => $report])
        ->assertSuccessful()
        ->assertSee('Detailed Report')
        ->assertSee('This is the summary.');
});
