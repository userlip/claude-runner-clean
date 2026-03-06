<?php

use App\Filament\Resources\RepositoryResource\Pages\ListRepositories;
use App\Models\GitHubConnection;
use App\Models\Repository;
use App\Models\User;
use App\Services\SecurityManagementService;
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

test('run security action triggers processing using security management service', function () {
    $repo = Repository::factory()->create(['user_id' => $this->user->id]);
    GitHubConnection::factory()->create(['user_id' => $this->user->id]);

    $service = \Mockery::mock(SecurityManagementService::class);
    $service->shouldReceive('processRepository')
        ->once()
        ->with(\Mockery::on(fn ($arg) => $arg instanceof Repository && $arg->is($repo)));

    app()->instance(SecurityManagementService::class, $service);

    Livewire::test(ListRepositories::class)
        ->callTableAction('runSecurity', $repo)
        ->assertNotified('Security check completed');
});
