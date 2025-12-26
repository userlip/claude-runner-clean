<?php

use App\Livewire\SnippetBrowser;
use App\Models\Snippet;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can render snippet browser component', function () {
    Livewire::test(SnippetBrowser::class)
        ->assertSuccessful()
        ->assertSee('Snippets');
});

test('shows empty state when no snippets', function () {
    Livewire::test(SnippetBrowser::class)
        ->assertSee('No snippets yet')
        ->assertSee('Create one');
});

test('displays user snippets', function () {
    Snippet::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'My Test Snippet',
    ]);

    Livewire::test(SnippetBrowser::class)
        ->assertSee('My Test Snippet');
});

test('does not show other users snippets', function () {
    $otherUser = User::factory()->create();
    Snippet::factory()->create([
        'user_id' => $otherUser->id,
        'name' => 'Other User Snippet',
    ]);

    Livewire::test(SnippetBrowser::class)
        ->assertDontSee('Other User Snippet')
        ->assertSee('No snippets yet');
});

test('dispatches insert-snippet event when clicking snippet', function () {
    $snippet = Snippet::factory()->create([
        'user_id' => $this->user->id,
        'content' => 'This is the snippet content to insert',
    ]);

    Livewire::test(SnippetBrowser::class)
        ->call('insertSnippet', $snippet->id)
        ->assertDispatched('insert-snippet', content: 'This is the snippet content to insert');
});

test('does not dispatch event for other users snippets', function () {
    $otherUser = User::factory()->create();
    $snippet = Snippet::factory()->create([
        'user_id' => $otherUser->id,
        'content' => 'Should not insert this',
    ]);

    Livewire::test(SnippetBrowser::class)
        ->call('insertSnippet', $snippet->id)
        ->assertNotDispatched('insert-snippet');
});

test('snippets are ordered by sort_order', function () {
    Snippet::factory()->create(['user_id' => $this->user->id, 'name' => 'Third', 'sort_order' => 2]);
    Snippet::factory()->create(['user_id' => $this->user->id, 'name' => 'First', 'sort_order' => 0]);
    Snippet::factory()->create(['user_id' => $this->user->id, 'name' => 'Second', 'sort_order' => 1]);

    Livewire::test(SnippetBrowser::class)
        ->assertSeeInOrder(['First', 'Second', 'Third']);
});
