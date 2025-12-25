<?php

use App\Models\GitHubConnection;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('github redirect requires authentication', function () {
    $this->get(route('github.redirect'))
        ->assertStatus(302);
});

test('github redirect redirects to github', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('github.redirect'));

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('github.com');
});

test('github callback creates connection', function () {
    $user = User::factory()->create();

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->token = 'test_token_123';
    $socialiteUser->shouldReceive('getId')->andReturn('12345');
    $socialiteUser->shouldReceive('getNickname')->andReturn('testuser');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $this->actingAs($user)
        ->get(route('github.callback'))
        ->assertRedirect('/admin');

    $this->assertDatabaseHas('github_connections', [
        'user_id' => $user->id,
        'github_user_id' => '12345',
        'github_username' => 'testuser',
    ]);
});

test('github disconnect removes connection', function () {
    $user = User::factory()->create();
    GitHubConnection::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('github.disconnect'))
        ->assertRedirect('/admin');

    $this->assertDatabaseMissing('github_connections', ['user_id' => $user->id]);
});
