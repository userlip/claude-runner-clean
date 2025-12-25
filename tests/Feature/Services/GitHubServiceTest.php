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
