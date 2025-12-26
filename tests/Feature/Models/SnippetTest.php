<?php

use App\Models\Snippet;
use App\Models\User;

test('snippet belongs to a user', function () {
    $user = User::factory()->create();
    $snippet = Snippet::factory()->create(['user_id' => $user->id]);

    expect($snippet->user->id)->toBe($user->id);
});

test('user has many snippets', function () {
    $user = User::factory()->create();
    Snippet::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->snippets)->toHaveCount(3);
});

test('snippets are ordered by sort_order', function () {
    $user = User::factory()->create();
    Snippet::factory()->create(['user_id' => $user->id, 'sort_order' => 2, 'name' => 'Second']);
    Snippet::factory()->create(['user_id' => $user->id, 'sort_order' => 0, 'name' => 'First']);
    Snippet::factory()->create(['user_id' => $user->id, 'sort_order' => 1, 'name' => 'Middle']);

    $snippets = $user->snippets;

    expect($snippets[0]->name)->toBe('First');
    expect($snippets[1]->name)->toBe('Middle');
    expect($snippets[2]->name)->toBe('Second');
});

test('snippet can be created with required fields', function () {
    $user = User::factory()->create();

    $snippet = Snippet::create([
        'user_id' => $user->id,
        'name' => 'Test Snippet',
        'content' => 'This is the snippet content',
    ]);

    expect($snippet->exists)->toBeTrue();
    expect($snippet->name)->toBe('Test Snippet');
    expect($snippet->content)->toBe('This is the snippet content');
    expect($snippet->fresh()->sort_order)->toBe(0);
});

test('deleting user deletes their snippets', function () {
    $user = User::factory()->create();
    Snippet::factory()->count(2)->create(['user_id' => $user->id]);

    expect(Snippet::where('user_id', $user->id)->count())->toBe(2);

    $user->delete();

    expect(Snippet::where('user_id', $user->id)->count())->toBe(0);
});
