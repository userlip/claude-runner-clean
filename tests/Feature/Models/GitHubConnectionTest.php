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
